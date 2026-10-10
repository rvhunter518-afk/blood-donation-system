<?php
/**
 * api/get_book_quiz.php
 * GET ?book_id=X
 * Returns quiz questions WITHOUT correct_answer in payload (security).
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
$bookId = (int)($_GET['book_id'] ?? 0);

if ($bookId < 1) {
    echo json_encode(['success' => false, 'error' => 'Invalid book_id']);
    exit;
}

// Verify book exists and is published
$bookStmt = $conn->prepare('SELECT id, title, low_points, medium_points, high_points FROM library_books WHERE id = ? AND published_status = 1');
$bookStmt->bind_param('i', $bookId);
$bookStmt->execute();
$book = $bookStmt->get_result()->fetch_assoc();
$bookStmt->close();

if (!$book) {
    echo json_encode(['success' => false, 'error' => 'Book not found or not available']);
    exit;
}

// Fetch questions — EXCLUDE correct_answer and explanation
$qStmt = $conn->prepare(
    'SELECT id, question_text, option_a, option_b, option_c, option_d
     FROM quiz_questions WHERE book_id = ? ORDER BY id'
);
$qStmt->bind_param('i', $bookId);
$qStmt->execute();
$questions = $qStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$qStmt->close();

if (empty($questions)) {
    echo json_encode(['success' => false, 'error' => 'No quiz questions available for this book']);
    exit;
}

echo json_encode([
    'success'        => true,
    'book_id'        => (int)$book['id'],
    'book_title'     => $book['title'],
    'low_points'     => (int)$book['low_points'],
    'medium_points'  => (int)$book['medium_points'],
    'high_points'    => (int)$book['high_points'],
    'questions'      => $questions
]);
