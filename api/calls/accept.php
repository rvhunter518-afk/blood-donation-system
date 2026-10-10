<?php
/**
 * POST /api/calls/accept.php
 *
 * Receiver accepts an incoming WebRTC call.
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

// CSRF check
$csrf = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!empty($_SESSION['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security token mismatch.']);
    exit;
}

$callId = trim($data['call_id'] ?? '');
if (!$callId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Missing call_id.']);
    exit;
}

require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->acceptCall($callId, (int)$_SESSION['user_id']);

    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
} catch (Throwable $e) {
    error_log('[accept.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to accept call.']);
}

