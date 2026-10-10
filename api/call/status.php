<?php
/**
 * GET /api/call/status.php?call_id=CALL-...
 *
 * Returns the current status of a call session.
 * Used by the frontend to poll and update the call panel UI.
 *
 * Response JSON:
 *   Success: {
 *     success: true, call_id, status, started_at, answered_at,
 *     ended_at, duration_seconds, end_reason, test_mode
 *   }
 *   Error: { success: false, error: "..." }
 *
 * Security:
 *   - Session authentication required
 *   - Only the caller or receiver may query a call's status (IDOR prevention)
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$callId = trim($_GET['call_id'] ?? '');
if (!preg_match('/^CALL-\d{8}-[A-F0-9]{8}$/i', $callId)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid call ID format.']);
    exit;
}

require_once __DIR__ . '/../../services/CallService.php';

try {
    $service = new CallService();
    $result  = $service->getCallStatus($callId, (int) $_SESSION['user_id']);
} catch (Throwable $e) {
    error_log('[status.php] Exception: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to retrieve call status.']);
    exit;
}

http_response_code($result['success'] ? 200 : 404);
echo json_encode($result);

