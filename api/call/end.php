<?php
/**
 * POST /api/call/end.php
 *
 * Ends an active call session (caller-initiated hang-up).
 *
 * POST body:
 *   call_id    string  Required. e.g. "CALL-20260915-ABCD1234"
 *   csrf_token string  Required.
 *
 * Response JSON:
 *   Success: { success: true, status: "completed"|"cancelled", message: "Call ended." }
 *   Error:   { success: false, error: "..." }
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

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$body = str_contains($contentType, 'application/json')
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_POST;

// CSRF
$csrfToken   = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($sessionCsrf) || !hash_equals($sessionCsrf, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security token mismatch. Please refresh and try again.']);
    exit;
}

$callId = trim($body['call_id'] ?? '');
if (!preg_match('/^CALL-\d{8}-[A-F0-9]{8}$/i', $callId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid call ID.']);
    exit;
}

require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->endCall($callId, (int) $_SESSION['user_id']);
} catch (Throwable $e) {
    error_log('[end.php] Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to end call.']);
    exit;
}

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result);

