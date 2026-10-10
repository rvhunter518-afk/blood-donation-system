<?php
/**
 * /api/calls/signal.php
 *
 * WebRTC Signaling Channel:
 * - POST: Send an SDP offer, SDP answer, or ICE candidate
 * - GET: Retrieve pending unread signals for this user & call session
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

require_once __DIR__ . '/../../services/CallService.php';
$service = new CallService();
$userId  = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $callId  = trim($data['call_id'] ?? '');
    $type    = trim($data['type'] ?? $data['signal_type'] ?? '');
    if ($type === 'ice-candidate' || $type === 'ice') {
        $type = 'candidate';
    }
    $rawPayload = $data['payload'] ?? $data['signal_data'] ?? null;
    $payload = is_array($rawPayload) 
               ? json_encode($rawPayload) 
               : (is_string($rawPayload) ? trim($rawPayload) : '');

    if (!$callId || !$type || $payload === '') {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Missing call_id, type, or payload.']);
        exit;
    }

    $validTypes = ['offer', 'answer', 'candidate', 'ring', 'busy', 'reject', 'end'];
    if (!in_array($type, $validTypes, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid signal type: ' . $type]);
        exit;
    }

    try {
        $res = $service->sendSignal($callId, $userId, $type, $payload);
        echo json_encode($res);
    } catch (Throwable $e) {
        error_log('[signal.php POST] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Signaling transmission failed.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $callId = trim($_GET['call_id'] ?? '');
    if (!$callId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Missing call_id.']);
        exit;
    }

    try {
        $res = $service->getSignals($callId, $userId);
        echo json_encode($res);
    } catch (Throwable $e) {
        error_log('[signal.php GET] Error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to retrieve signals.']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed.']);

