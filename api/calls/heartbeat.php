<?php
/**
 * GET /api/calls/heartbeat.php
 *
 * Periodic presence ping and incoming call detector.
 * Also returns online presence for requested user IDs.
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

try {
    $service = new CallService();
    $result  = $service->heartbeat((int)$_SESSION['user_id']);

    // Check if client requested presence info for specific user IDs
    $checkUsers = $_GET['check_users'] ?? '';
    if ($checkUsers) {
        $ids = explode(',', $checkUsers);
        $result['presence_map'] = $service->getUsersPresence($ids);
    }

    echo json_encode($result);
} catch (Throwable $e) {
    error_log('[heartbeat.php] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Heartbeat failed.']);
}

