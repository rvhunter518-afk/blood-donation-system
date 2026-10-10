<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode([
    'success' => true,
    'token'   => $_SESSION['csrf_token'],
    'user_id' => $_SESSION['user_id'] ?? null,
    'role'    => $_SESSION['user_role'] ?? null,
]);

