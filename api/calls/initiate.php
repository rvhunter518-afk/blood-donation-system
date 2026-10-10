<?php
/**
 * POST /api/calls/initiate.php
 *
 * Initiates a browser-to-browser WebRTC call session.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to make a call.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

// CSRF check
$csrf = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security token mismatch. Please refresh the page.']);
    exit;
}

$receiverUserId = filter_var($data['receiver_user_id'] ?? '', FILTER_VALIDATE_INT);
if (!$receiverUserId || $receiverUserId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid receiver specified.']);
    exit;
}

$callType    = in_array($data['call_type'] ?? '', ['audio', 'video'], true) ? $data['call_type'] : 'audio';
$contextType = trim($data['context_type'] ?? 'blood_request');
$contextId   = filter_var($data['context_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->initiateCall(
        (int)$_SESSION['user_id'],
        (int)$receiverUserId,
        $callType,
        $contextType,
        $contextId
    );

    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('[initiate.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while initiating the call.']);
}

