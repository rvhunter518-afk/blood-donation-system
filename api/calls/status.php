<?php
/**
 * GET /api/calls/status.php?call_id=CALL-...
 *
 * Polls the current status of an ongoing or completed call session.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

$callId = trim($_GET['call_id'] ?? '');
if (!$callId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing call_id.']);
    exit;
}

require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->getCallStatus($callId, (int)$_SESSION['user_id']);

    http_response_code($result['success'] ? 200 : 404);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('[status.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to get status.']);
}

