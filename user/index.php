<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.html?error=auth');
    exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
header('Location: user.html');
exit;

