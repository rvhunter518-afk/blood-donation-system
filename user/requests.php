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

$stmt = $conn->prepare('SELECT id, patient_name, blood_type, units, hospital, city, urgency, status, created_at FROM blood_requests WHERE requester_id = ? ORDER BY created_at DESC LIMIT 30');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Request history is unavailable. Import database/setup.sql in phpMyAdmin first.']);
    $conn->close();
    exit;
}
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = [
        'id' => (int) $row['id'],
        'patient_name' => $row['patient_name'],
        'blood_type' => $row['blood_type'],
        'units' => (int) $row['units'],
        'hospital' => $row['hospital'],
        'city' => $row['city'],
        'urgency' => $row['urgency'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
    ];
}
$stmt->close();
$conn->close();
echo json_encode(['requests' => $requests]);
