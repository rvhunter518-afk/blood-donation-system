<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$quizKey = trim((string) ($payload['quiz_key'] ?? ''));
if (!preg_match('/^weekly-[a-z0-9_-]{1,60}$/', $quizKey)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid weekly quiz.']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();
$userId = (int) $_SESSION['user_id'];
$conn->begin_transaction();
try {
    $completion = $conn->prepare('INSERT IGNORE INTO quiz_completions (user_id, quiz_key) VALUES (?, ?)');
    $completion->bind_param('is', $userId, $quizKey);
    $completion->execute();
    $awarded = $completion->affected_rows === 1;
    $completion->close();
    if ($awarded) {
        $profile = $conn->prepare("INSERT INTO gamification_profiles (user_id, points) VALUES (?, 10) ON DUPLICATE KEY UPDATE points = points + 10, tier = CASE WHEN points + 10 >= 1000 THEN 'Life Saver' WHEN points + 10 >= 500 THEN 'Gold' WHEN points + 10 >= 200 THEN 'Silver' ELSE 'Bronze' END");
        $profile->bind_param('i', $userId);
        $profile->execute();
        $profile->close();
    }
    $conn->commit();
    echo json_encode(['success' => true, 'awarded' => $awarded, 'points' => $awarded ? 10 : 0]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(503);
    echo json_encode(['error' => 'Quiz points are unavailable.']);
}