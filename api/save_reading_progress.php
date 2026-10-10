<?php
/**
 * api/save_reading_progress.php
 * POST: {book_id, current_page, total_pages}
 * Upserts book_progress. Awards +20 XP on first completion.
 */
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();
$userId = (int)$_SESSION['user_id'];

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$bookId     = (int)($data['book_id']      ?? 0);
$currentPage = (int)($data['current_page'] ?? 1);
$totalPages  = (int)($data['total_pages']  ?? 1);

if ($bookId < 1 || $currentPage < 1 || $totalPages < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$percentage = min(100, (int)round(($currentPage / $totalPages) * 100));
$status     = ($percentage >= 100) ? 'completed' : 'reading';

// Check existing progress to detect first-time completion
$existingStmt = $conn->prepare('SELECT status FROM book_progress WHERE user_id = ? AND book_id = ?');
$existingStmt->bind_param('ii', $userId, $bookId);
$existingStmt->execute();
$existing = $existingStmt->get_result()->fetch_assoc();
$existingStmt->close();

$isNewCompletion = ($status === 'completed' && (!$existing || $existing['status'] !== 'completed'));
$completedAt     = ($status === 'completed') ? date('Y-m-d H:i:s') : null;

// Upsert progress (never downgrade from completed)
$sql = 'INSERT INTO book_progress 
          (user_id, book_id, current_page, total_pages, progress_percentage, status, started_at, completed_at)
        VALUES (?,?,?,?,?,?,NOW(),?)
        ON DUPLICATE KEY UPDATE
          current_page        = VALUES(current_page),
          total_pages         = VALUES(total_pages),
          progress_percentage = VALUES(progress_percentage),
          status              = IF(status = "completed", "completed", VALUES(status)),
          completed_at        = IF(status = "completed", completed_at, IF(VALUES(status) = "completed", VALUES(completed_at), completed_at))';

$stmt = $conn->prepare($sql);
$stmt->bind_param('iiiiiss', $userId, $bookId, $currentPage, $totalPages, $percentage, $status, $completedAt);
$stmt->execute();
$stmt->close();

// Award XP on first completion
$xpAwarded = 0;
if ($isNewCompletion) {
    $xpAwarded = 20;
    $conn->query("UPDATE gamification_profiles SET xp = xp + 20, last_activity_date = CURDATE() WHERE user_id = $userId");
}

echo json_encode([
    'success'     => true,
    'percentage'  => $percentage,
    'status'      => $status,
    'xp_awarded'  => $xpAwarded,
    'completed'   => $isNewCompletion
]);
