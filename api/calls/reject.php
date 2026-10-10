<?php
/**
 * POST /api/calls/reject.php
 *
 * Receiver rejects an incoming WebRTC call or reports busy.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
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

$callId = trim($data['call_id'] ?? '');
$reason = trim($data['reason'] ?? 'rejected');

if (!$callId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing call_id.']);
    exit;
}

require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->rejectCall($callId, (int)$_SESSION['user_id'], $reason);

    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('[reject.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to reject call.']);
}

