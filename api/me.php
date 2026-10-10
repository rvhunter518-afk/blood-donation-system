<?php
/**
 * api/me.php — Return current authenticated session user profile
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'authenticated' => false,
        'user_id' => null,
        'user_name' => 'Guest',
        'user_role' => 'guest'
    ]);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'blood_connect');
$userData = [
    'authenticated' => true,
    'user_id' => (int)$_SESSION['user_id'],
    'user_name' => $_SESSION['user_name'] ?? 'User',
    'user_role' => $_SESSION['user_role'] ?? 'user',
    'blood_type' => '',
    'phone' => '',
    'city' => ''
];

if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    $stmt = $conn->prepare("SELECT id, full_name, role, blood_type, phone, city FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $userData['user_name'] = $row['full_name'];
            $userData['user_role'] = $row['role'];
            $userData['blood_type'] = $row['blood_type'];
            $userData['phone'] = $row['phone'];
            $userData['city'] = $row['city'];
        }
        $stmt->close();
    }
    $conn->close();
}

echo json_encode($userData);

