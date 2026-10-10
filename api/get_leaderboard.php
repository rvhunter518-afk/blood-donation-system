<?php
/**
 * api/get_leaderboard.php
 * GET — Returns top 20 users by XP. Privacy-safe display names.
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$conn   = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$sql = "SELECT
    CONCAT(u.first_name, ' ', LEFT(u.last_name, 1), '.') AS display_name,
    gp.xp,
    gp.points,
    gp.tier,
    gp.current_streak,
    COALESCE(bp.books_completed, 0)  AS books_completed,
    COALESCE(ub.badges_count, 0)     AS badges_count,
    gp.user_id
FROM gamification_profiles gp
JOIN users u ON u.id = gp.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS books_completed
    FROM book_progress WHERE status = 'completed'
    GROUP BY user_id
) bp ON bp.user_id = gp.user_id
LEFT JOIN (
    SELECT user_id, COUNT(*) AS badges_count
    FROM user_badges
    GROUP BY user_id
) ub ON ub.user_id = gp.user_id
ORDER BY gp.xp DESC
LIMIT 20";

$result = $conn->query($sql);
$rows   = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$leaderboard = [];
foreach ($rows as $i => $row) {
    $leaderboard[] = [
        'rank'            => $i + 1,
        'display_name'    => $row['display_name'],
        'xp'              => (int)$row['xp'],
        'points'          => (int)$row['points'],
        'tier'            => $row['tier'],
        'current_streak'  => (int)$row['current_streak'],
        'books_completed' => (int)$row['books_completed'],
        'badges_count'    => (int)$row['badges_count'],
        'is_me'           => ($row['user_id'] == $userId)
    ];
}

// Current user's rank if not in top 20
$myRank = null;
$rankSql = "SELECT COUNT(*)+1 AS my_rank FROM gamification_profiles WHERE xp > (SELECT xp FROM gamification_profiles WHERE user_id = $userId)";
$rankResult = $conn->query($rankSql);
if ($rankResult) {
    $myRank = (int)$rankResult->fetch_assoc()['my_rank'];
}

echo json_encode([
    'success'     => true,
    'leaderboard' => $leaderboard,
    'my_rank'     => $myRank
]);
