<?php
session_start();
require_once '../config/database.php';
$conn = getDbConnection();

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
           (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $hospital_uid = trim(strtoupper($_POST['hospital_uid']));
    $password = trim($_POST['password']);

    if (empty($hospital_uid) || empty($password)) {
        if ($is_ajax) {
            echo json_encode(['success' => false, 'message' => 'Please provide both Hospital UID and password']);
            exit;
        }
        header("Location: ../login.html?error=hospital_empty");
        exit;
    }

    $stmt = $conn->prepare("SELECT id, hospital_uid, hospital_name, password_hash, status FROM hospitals WHERE hospital_uid = ? AND status = 'active'");
    $stmt->bind_param("s", $hospital_uid);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            // Success
            $_SESSION['hospital_id'] = $row['id'];
            $_SESSION['hospital_name'] = $row['hospital_name'];
            $_SESSION['hospital_uid'] = $row['hospital_uid'];
            $_SESSION['user_role'] = 'hospital';

            if ($is_ajax) {
                echo json_encode(['success' => true, 'redirect' => 'hospital/dashboard.php']);
                exit;
            }
            header("Location: dashboard.php");
            exit;
        }
    }

    // Failure
    if ($is_ajax) {
        echo json_encode(['success' => false, 'message' => 'Invalid Hospital UID or password, or account is not active']);
        exit;
    }
    header("Location: ../login.html?error=hospital_invalid");
    exit;
}

if ($is_ajax) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}
header("Location: ../login.html");
exit;
?>
