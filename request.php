<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'user') !== 'user') {
    header('Location: login.html?error=role');
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    die('Database connection failed. Import database/setup.sql first.');
}
$conn->set_charset('utf8mb4');

$patientName = trim($_POST['patient_name'] ?? '');
$bloodType = trim($_POST['blood_type'] ?? '');
$units = max(1, min(9, (int) ($_POST['units'] ?? 1)));
$hospital = trim($_POST['hospital'] ?? '');
$city = trim($_POST['city'] ?? '');
$urgency = in_array($_POST['urgency'] ?? '', ['routine', 'urgent', 'critical'], true) ? $_POST['urgency'] : 'urgent';
$phone = trim($_POST['contact_phone'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$validTypes = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

if ($patientName === '' || !in_array($bloodType, $validTypes, true) || $hospital === '' || $city === '' || $phone === '') {
    header('Location: user/user.html?error=missing');
    exit;
}

$stmt = $conn->prepare('INSERT INTO blood_requests (requester_id, patient_name, blood_type, units, hospital, city, urgency, contact_phone, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('ississsss', $_SESSION['user_id'], $patientName, $bloodType, $units, $hospital, $city, $urgency, $phone, $notes);
$stmt->execute();
$requestId = $conn->insert_id;
$stmt->close();
$noticeText = 'A ' . $bloodType . ' blood request is open near ' . $city . '.';
$donorQuery = $conn->prepare('SELECT user_id, id FROM donors WHERE blood_type = ? AND availability = "available"');
$donorQuery->bind_param('s', $bloodType);
$donorQuery->execute();
$donorResult = $donorQuery->get_result();
$notice = $conn->prepare('INSERT INTO notifications (user_id, donor_id, request_id, message) VALUES (?, ?, ?, ?)');
while ($donor = $donorResult->fetch_assoc()) {
    $notice->bind_param('iiis', $donor['user_id'], $donor['id'], $requestId, $noticeText);
    $notice->execute();
}
$notice->close();
$donorQuery->close();
$conn->close();
header('Location: user/user.html?sent=1');
exit;