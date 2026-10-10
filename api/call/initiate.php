<?php
/**
 * POST /api/call/initiate.php
 *
 * Initiates a call from the authenticated user to a receiver.
 *
 * Accepted POST body (JSON or form-encoded):
 *   receiver_user_id  int     Required. Target user's DB id.
 *   receiver_type     string  Required. 'user' | 'donor'
 *   context_type      string  Optional. 'blood_request'|'donor_search'|'emergency_request'|'support'
 *   context_id        int     Optional. Related blood_request.id etc.
 *
 * Response JSON:
 *   Success: { success: true, call_id: "CALL-...", status: "initiated", test_mode: bool, message: "..." }
 *   Error:   { success: false, error: "..." }
 *
 * Security:
 *   - Session authentication required
 *   - CSRF token checked
 *   - Phone numbers fetched server-side ONLY — never from client
 *   - Rate limiting enforced
 *   - Input validated and sanitized with prepared statements
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Authentication check ──────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Please log in to use the calling feature.']);
    exit;
}

// ── Only POST allowed ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── Parse request body (supports both JSON and form-encoded) ──
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $body = $_POST;
}

// ── CSRF check ────────────────────────────────────────────────
$csrfToken   = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($sessionCsrf) || !hash_equals($sessionCsrf, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security token mismatch. Please refresh and try again.']);
    exit;
}

// ── Input validation ──────────────────────────────────────────
$receiverUserId = filter_var($body['receiver_user_id'] ?? '', FILTER_VALIDATE_INT);
$receiverType   = in_array($body['receiver_type'] ?? '', ['user','donor'], true)
                    ? $body['receiver_type']
                    : 'user';
$contextType    = in_array($body['context_type'] ?? '', [
                    'blood_request','donor_search','hospital_request',
                    'emergency_request','support','general'
                  ], true) ? $body['context_type'] : 'donor_search';
$contextId      = filter_var($body['context_id'] ?? '', FILTER_VALIDATE_INT) ?: null;

if ($receiverUserId === false || $receiverUserId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid receiver specified.']);
    exit;
}

// ── Dispatch to CallService ───────────────────────────────────
require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->initiateCall(
        (int) $_SESSION['user_id'],
        (int) $receiverUserId,
        $receiverType,
        $contextType,
        $contextId ? (int)$contextId : null
    );
} catch (Throwable $e) {
    error_log('[initiate.php] Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An unexpected error occurred. Please try again.']);
    exit;
}

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result);

