<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    http_response_code(403);
    echo json_encode(['error' => 'Please sign in as a blood requester.']);
    exit;
}

$bloodType = trim($_GET['blood_type'] ?? '');
$city      = trim($_GET['city']       ?? '');

$validTypes = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
if (!in_array($bloodType, $validTypes, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Select a valid blood group.']);
    exit;
}

require_once __DIR__ . '/config/database.php';
$conn = getDbConnection();

// Build query — single SELECT, single ORDER BY
$sql = "SELECT d.id, d.user_id, u.full_name, d.blood_type, d.city, d.availability,
               CASE
                   WHEN up.last_seen >= NOW() - INTERVAL 35 SECOND THEN up.status
                   ELSE 'offline'
               END AS presence_status
        FROM donors d
        JOIN  users u  ON u.id = d.user_id
        LEFT JOIN user_presence up ON up.user_id = d.user_id
        WHERE d.blood_type = ? AND d.availability = 'available'";

$params = [$bloodType];
$types  = 's';

if ($city !== '') {
    $sql   .= ' AND d.city LIKE ?';
    $params[] = '%' . $city . '%';
    $types  .= 's';
}

$sql .= ' ORDER BY (presence_status = "online") DESC, u.full_name ASC LIMIT 50';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Donor search unavailable. Please ensure the database is set up correctly.']);
    $conn->close();
    exit;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not read donor data from database.']);
    $stmt->close();
    $conn->close();
    exit;
}

$donors = [];
while ($row = $result->fetch_assoc()) {
    $donors[] = [
        'id'              => (int) $row['id'],
        'user_id'         => (int) $row['user_id'],
        'name'            => $row['full_name'],
        'blood_type'      => $row['blood_type'],
        'city'            => $row['city'] ?: 'Location not specified',
        'availability'    => 'Available',
        'presence_status' => $row['presence_status'] ?: 'offline',
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['count' => count($donors), 'donors' => $donors]);
