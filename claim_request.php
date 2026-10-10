<?php
session_start();
header('Cache-Control: no-cache, no-store, must-revalidate');

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
          || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'donor') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Please log in as a verified donor.']);
        exit;
    }
    header('Location: login.html?error=role');
    exit;
}

$requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
if (!$requestId) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        exit;
    }
    header('Location: donor/index.php?error=request');
    exit;
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
        exit;
    }
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');

// 1. Get donor user details
$donorStmt = $conn->prepare('SELECT u.id as user_id, u.full_name, u.phone, u.blood_type, d.id as donor_id FROM users u LEFT JOIN donors d ON d.user_id = u.id WHERE u.id = ?');
$donorStmt->bind_param('i', $_SESSION['user_id']);
$donorStmt->execute();
$donor = $donorStmt->get_result()->fetch_assoc();
$donorStmt->close();

if (!$donor) {
    $conn->close();
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Donor account not found.']);
        exit;
    }
    header('Location: donor/index.php?error=donor-not-found');
    exit;
}

// Ensure donor record exists in `donors` table
$donorId = $donor['donor_id'];
if (empty($donorId)) {
    $ins = $conn->prepare('INSERT INTO donors (user_id, blood_type, phone, availability, created_at) VALUES (?, ?, ?, "Available", NOW())');
    $ins->bind_param('iss', $donor['user_id'], $donor['blood_type'], $donor['phone']);
    if ($ins->execute()) {
        $donorId = $conn->insert_id;
    }
    $ins->close();
}

// 2. Fetch blood request details
$reqStmt = $conn->prepare('SELECT id, requester_id, patient_name, blood_type, units, hospital, city, status FROM blood_requests WHERE id = ?');
$reqStmt->bind_param('i', $requestId);
$reqStmt->execute();
$request = $reqStmt->get_result()->fetch_assoc();
$reqStmt->close();

if (!$request) {
    $conn->close();
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Blood request not found.']);
        exit;
    }
    header('Location: donor/index.php?error=not-found');
    exit;
}

if ($request['status'] !== 'open') {
    $conn->close();
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'This blood request has already been matched or fulfilled.']);
        exit;
    }
    header('Location: donor/index.php?error=already-claimed');
    exit;
}

$conn->begin_transaction();
$success = false;

// 3. Update blood request to matched
$upd = $conn->prepare('UPDATE blood_requests SET status = "matched", donor_id = ? WHERE id = ? AND status = "open"');
$upd->bind_param('ii', $donorId, $requestId);
$upd->execute();
$success = ($upd->affected_rows === 1);
$upd->close();

if ($success) {
    // 4. Send high-priority notification to the requester
    $donorName   = $donor['full_name'] ?? 'A verified donor';
    $donorPhone  = !empty($donor['phone']) ? $donor['phone'] : 'Check Call Directory';
    $donorBlood  = $donor['blood_type'] ?? 'O+';
    $patientName = $request['patient_name'] ?? 'Patient';
    $hospital    = $request['hospital'] ?? 'Hospital';
    $units       = (int)($request['units'] ?? 1);

    $notifTitle   = "Emergency Donor Match: " . $donorName;
    $notifMessage = "Donor " . $donorName . " (" . $donorBlood . ", Tel: " . $donorPhone . ") has responded 'I CAN HELP' for " . $patientName . " (" . $units . " units at " . $hospital . "). You can call them immediately.";

    $notify = $conn->prepare('INSERT INTO notifications (user_id, donor_id, request_id, title, type, priority, icon, message, is_read, created_at) VALUES (?, ?, ?, ?, "donor_help", "critical", "fa-hand-holding-heart", ?, 0, NOW())');
    if ($notify) {
        $notify->bind_param('iiiss', $request['requester_id'], $donorId, $requestId, $notifTitle, $notifMessage);
        $notify->execute();
        $notify->close();
    }

    $conn->commit();
} else {
    $conn->rollback();
}

$conn->close();

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $success ? 'Notification sent successfully to the requester!' : 'Unable to claim request. It may have already been matched.'
    ]);
    exit;
}

header('Location: donor/index.php?' . ($success ? 'claimed=1' : 'error=already-claimed'));
exit;
