<?php
/**
 * api/get_reading_progress.php
 * GET ?book_id=X — Returns saved reading position for resume prompt.
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
$bookId = (int)($_GET['book_id'] ?? 0);

if ($bookId < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid book_id']);
    exit;
}

$stmt = $conn->prepare(
    'SELECT current_page, total_pages, progress_percentage, status, started_at, completed_at
     FROM book_progress WHERE user_id = ? AND book_id = ?'
);
$stmt->bind_param('ii', $userId, $bookId);
$stmt->execute();
$progress = $stmt->get_result()->fetch_assoc();
$stmt->close();

echo json_encode([
    'success'  => true,
    'progress' => $progress   // null if no saved progress
]);
