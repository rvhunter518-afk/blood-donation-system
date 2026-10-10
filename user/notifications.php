<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed.']);
    exit;
}
$conn->set_charset('utf8mb4');

$stmt = $conn->prepare('SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Notifications are unavailable. Import database/setup.sql in phpMyAdmin first.']);
    $conn->close();
    exit;
}
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => (int) $row['id'],
        'message' => $row['message'],
        'is_read' => (bool) $row['is_read'],
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();
$conn->close();
echo json_encode(['notifications' => $notifications]);
