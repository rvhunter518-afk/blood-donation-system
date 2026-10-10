<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$bookId = filter_var($payload['book_id'] ?? null, FILTER_VALIDATE_INT);
if (!$bookId || $bookId < 1) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid book.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();
$stmt = $conn->prepare('INSERT INTO book_reads (user_id, book_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE last_read_at = CURRENT_TIMESTAMP');
if (!$stmt) {
    http_response_code(503);
    echo json_encode(['error' => 'Library tracking is not installed.']);
    exit;
}
$userId = (int) $_SESSION['user_id'];
$stmt->bind_param('ii', $userId, $bookId);
$stmt->execute();
$stmt->close();
echo json_encode(['success' => true]);