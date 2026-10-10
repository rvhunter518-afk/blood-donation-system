<?php
/**
 * api/submit_quiz.php
 * POST: {book_id, answers:[{question_id, selected_answer}]}
 * Evaluates answers server-side, awards points/XP, saves to quiz_attempts,
 * updates streaks, checks badge conditions.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$conn   = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$data    = json_decode(file_get_contents('php://input'), true) ?? [];
$bookId  = (int)($data['book_id'] ?? 0);
$answers = $data['answers'] ?? [];

if ($bookId < 1 || empty($answers) || !is_array($answers)) {
    echo json_encode(['success' => false, 'error' => 'Invalid submission data']);
    exit;
}

// Fetch book info
$bookStmt = $conn->prepare('SELECT id, title, low_points, medium_points, high_points FROM library_books WHERE id = ? AND published_status = 1');
$bookStmt->bind_param('i', $bookId);
$bookStmt->execute();
$book = $bookStmt->get_result()->fetch_assoc();
$bookStmt->close();

if (!$book) {
    echo json_encode(['success' => false, 'error' => 'Book not found or not published']);
    exit;
}

// Extract and validate question IDs
$questionIds = [];
foreach ($answers as $ans) {
    $qid = (int)($ans['question_id'] ?? 0);
    if ($qid > 0) $questionIds[] = $qid;
}

if (empty($questionIds)) {
    echo json_encode(['success' => false, 'error' => 'No valid question IDs in submission']);
    exit;
}

// Fetch correct answers from DB (secure — never trust client)
$placeholders = implode(',', array_fill(0, count($questionIds), '?'));
$types        = str_repeat('i', count($questionIds));
$allTypes     = 'i' . $types;
$bindVals     = array_merge([$bookId], $questionIds);

$qStmt = $conn->prepare("SELECT id, question_text, correct_answer, explanation FROM quiz_questions WHERE book_id = ? AND id IN ($placeholders)");
$refs  = [&$allTypes];
foreach ($bindVals as &$v) { $refs[] = &$v; }
call_user_func_array([$qStmt, 'bind_param'], $refs);
$qStmt->execute();
$correctRows = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qStmt->close();

// Map question_id => {correct, exp, text}
$correctMap = [];
foreach ($correctRows as $row) {
    $correctMap[(int)$row['id']] = [
        'correct' => strtoupper(trim($row['correct_answer'])),
        'exp'     => $row['explanation'],
        'text'    => $row['question_text']
    ];
}

// Evaluate submissions
$score        = 0;
$total        = count($correctMap);
$explanations = [];

foreach ($answers as $ans) {
    $qid      = (int)($ans['question_id'] ?? 0);
    $selected = strtoupper(trim($ans['selected_answer'] ?? ''));
    if (!isset($correctMap[$qid])) continue;

    $isCorrect = ($selected === $correctMap[$qid]['correct']);
    if ($isCorrect) $score++;

    $explanations[] = [
        'question_id'   => $qid,
        'question_text' => $correctMap[$qid]['text'],
        'selected'      => $selected ?: '—',
        'correct'       => $correctMap[$qid]['correct'],
        'is_correct'    => $isCorrect,
        'explanation'   => $correctMap[$qid]['exp']
    ];
}

if ($total === 0) {
    echo json_encode(['success' => false, 'error' => 'No matching questions found for this book']);
    exit;
}

$percentage = (int)round(($score / $total) * 100);

// Determine score level and rewards
if ($percentage >= 80) {
    $scoreLevel = 'high';
    $points     = (int)$book['high_points'];
    $xp         = ($percentage === 100) ? 100 : 75;
} elseif ($percentage >= 50) {
    $scoreLevel = 'medium';
    $points     = (int)$book['medium_points'];
    $xp         = 30;
} else {
    $scoreLevel = 'low';
    $points     = (int)$book['low_points'];
    $xp         = 10;
}

// Save quiz attempt (single insert with correct schema columns: points_earned, xp_earned, attempted_at)
$insStmt = $conn->prepare(
    'INSERT INTO quiz_attempts (user_id, book_id, score, total_questions, percentage, score_level, points_earned, xp_earned, attempted_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
);
$insStmt->bind_param('iiiissii', $userId, $bookId, $score, $total, $percentage, $scoreLevel, $points, $xp);
$insStmt->execute();
$insStmt->close();

// Mark book progress as completed if passed (>= 50%)
if ($percentage >= 50) {
    $conn->query(
        "INSERT INTO book_progress (user_id, book_id, current_page, progress_percentage, status, updated_at) 
         VALUES ($userId, $bookId, 10, 100, 'completed', NOW()) 
         ON DUPLICATE KEY UPDATE status = 'completed', progress_percentage = 100, updated_at = NOW()"
    );
}

// Update/create gamification profile with streak logic
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

$profResult = $conn->query("SELECT last_activity_date, current_streak, longest_streak FROM gamification_profiles WHERE user_id = $userId");
$prof = $profResult ? $profResult->fetch_assoc() : null;

$newStreak = 1;
if ($prof) {
    $lastDate  = $prof['last_activity_date'] ?? '';
    $oldStreak = (int)$prof['current_streak'];
    if ($lastDate === $today) {
        $newStreak = $oldStreak;                  // Already active today
    } elseif ($lastDate === $yesterday) {
        $newStreak = $oldStreak + 1;              // Consecutive day
    } else {
        $newStreak = 1;                           // Streak broken
    }
}

$conn->query(
    "INSERT INTO gamification_profiles (user_id, points, xp, current_streak, longest_streak, last_activity_date)
     VALUES ($userId, $points, $xp, $newStreak, $newStreak, '$today')
     ON DUPLICATE KEY UPDATE
       points             = points + $points,
       xp                 = xp + $xp,
       current_streak     = $newStreak,
       longest_streak     = GREATEST(longest_streak, $newStreak),
       last_activity_date = '$today'"
);

// ── Badge Check ──
$badgesEarned = [];

$badgeRes = $conn->query(
    "SELECT b.* FROM badges b
     WHERE b.badge_id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = $userId)"
);
$allBadges = $badgeRes ? $badgeRes->fetch_all(MYSQLI_ASSOC) : [];

if (!empty($allBadges)) {
    // Get updated stats for badge condition evaluation
    $updProf = $conn->query("SELECT points, xp, current_streak FROM gamification_profiles WHERE user_id = $userId")->fetch_assoc();
    
    $qaStats = $conn->query("SELECT 
        COUNT(*) AS quiz_count,
        COUNT(CASE WHEN score_level = 'high' THEN 1 END) AS high_score_count,
        COUNT(CASE WHEN percentage = 100 THEN 1 END) AS perfect_count
        FROM quiz_attempts WHERE user_id = $userId")->fetch_assoc();
    $quizCount      = (int)($qaStats['quiz_count'] ?? 0);
    $highScoreCount = (int)($qaStats['high_score_count'] ?? 0);
    $perfectCount   = (int)($qaStats['perfect_count'] ?? 0);

    $booksCompleted = (int)($conn->query("SELECT COUNT(*) as c FROM book_progress WHERE user_id = $userId AND status = 'completed'")->fetch_assoc()['c'] ?? 0);

    foreach ($allBadges as $badge) {
        $earn = false;
        $condType  = $badge['condition_type'] ?? '';
        $condVal   = (int)($badge['condition_value'] ?? 1);
        switch ($condType) {
            case 'quizzes_completed':
            case 'first_quiz':
            case 'quiz_count':
                $earn = ($quizCount >= max(1, $condVal));
                break;
            case 'high_score_count':
            case 'high_score':
                $earn = ($highScoreCount >= max(1, $condVal));
                break;
            case 'perfect_score':
                $earn = ($perfectCount >= max(1, $condVal));
                break;
            case 'books_completed':
            case 'first_book':
                $earn = ($booksCompleted >= max(1, $condVal));
                break;
            case 'streak_days':
            case 'streak':
                $earn = ($newStreak >= max(1, $condVal));
                break;
            case 'total_points':
            case 'points_total':
                $earn = ((int)($updProf['points'] ?? 0) >= $condVal);
                break;
            case 'xp_total':
                $earn = ((int)($updProf['xp'] ?? 0) >= $condVal);
                break;
        }

        if ($earn) {
            $badgeId   = $badge['badge_id'];
            $badgePts  = (int)($badge['points_reward'] ?? 0);
            $stmtBadge = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id, unlocked_at) VALUES (?, ?, NOW())");
            if ($stmtBadge) {
                $stmtBadge->bind_param('is', $userId, $badgeId);
                $stmtBadge->execute();
                if ($stmtBadge->affected_rows > 0) {
                    if ($badgePts > 0) {
                        $conn->query("UPDATE gamification_profiles SET points = points + $badgePts WHERE user_id = $userId");
                    }
                    $badgesEarned[] = [
                        'badge_id'    => $badgeId,
                        'name'        => $badge['name'],
                        'description' => $badge['description'],
                        'icon'        => $badge['icon'],
                        'points'      => $badgePts,
                        'rarity'      => $badge['rarity'] ?? 'Common'
                    ];
                }
                $stmtBadge->close();
            }
        }
    }
}

echo json_encode([
    'success'        => true,
    'score'          => $score,
    'total'          => $total,
    'percentage'     => $percentage,
    'score_level'    => $scoreLevel,
    'points_awarded' => $points,
    'xp_awarded'     => $xp,
    'is_perfect'     => ($percentage === 100),
    'explanations'   => $explanations,
    'badges_earned'  => $badgesEarned
]);
