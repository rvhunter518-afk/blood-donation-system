<?php
/**
 * message/index.php — Blood Connect Messaging Backend
 * GET  ?action=history&with=<id>&with_type=user|hospital  → chat history
 * POST action=send   { receiver_id, receiver_type, message }  → send message
 * GET  ?action=unread  → unread count
 * POST action=mark_read { with_id, with_type }  → mark as read
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}
$conn->set_charset('utf8mb4');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}
$me_id = (int)$_SESSION['user_id'];

// Auto-create table
$conn->query("
    CREATE TABLE IF NOT EXISTS blood_messages (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_id     INT NOT NULL,
        receiver_id   INT NOT NULL,
        message       TEXT NOT NULL,
        is_read       TINYINT(1) NOT NULL DEFAULT 0,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sender_type   ENUM('user','hospital') NOT NULL DEFAULT 'user',
        receiver_type ENUM('user','hospital') NOT NULL DEFAULT 'user',
        INDEX idx_conv (sender_id, receiver_id),
        INDEX idx_recv (receiver_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$method = $_SERVER['REQUEST_METHOD'];
$action = ($method === 'POST') ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

// Parse JSON body if Content-Type is JSON
$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw) { $body = json_decode($raw, true) ?? []; }
    if (!$action) $action = $body['action'] ?? '';
}

/* ─── SEND ────────────────────────────────────────────────── */
if ($action === 'send') {
    $receiver_id   = (int)($body['receiver_id']   ?? $_POST['receiver_id']   ?? 0);
    $receiver_type = in_array($body['receiver_type'] ?? $_POST['receiver_type'] ?? 'user', ['user','hospital'])
                     ? ($body['receiver_type'] ?? $_POST['receiver_type'] ?? 'user') : 'user';
    $message       = trim($body['message'] ?? $_POST['message'] ?? '');

    if (!$receiver_id || !$message) {
        echo json_encode(['success' => false, 'error' => 'missing_params']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO blood_messages (sender_id, receiver_id, message, sender_type, receiver_type)
         VALUES (?, ?, ?, 'user', ?)"
    );
    $stmt->bind_param('iiss', $me_id, $receiver_id, $message, $receiver_type);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => (int)$conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'error' => 'db_error']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

/* ─── HISTORY ─────────────────────────────────────────────── */
if ($action === 'history') {
    $with_id   = (int)($_GET['with'] ?? 0);
    $with_type = in_array($_GET['with_type'] ?? 'user', ['user','hospital']) ? $_GET['with_type'] : 'user';

    if (!$with_id) { echo json_encode(['success' => false, 'error' => 'missing_with']); exit; }

    // Mark incoming messages as read
    $mr = $conn->prepare(
        "UPDATE blood_messages SET is_read=1
         WHERE sender_id=? AND sender_type=? AND receiver_id=? AND is_read=0"
    );
    $mr->bind_param('isi', $with_id, $with_type, $me_id);
    $mr->execute();
    $mr->close();

    // Fetch full history
    $stmt = $conn->prepare(
        "SELECT id, sender_id, receiver_id, message, is_read, created_at, sender_type, receiver_type
         FROM blood_messages
         WHERE (sender_id=? AND sender_type='user'  AND receiver_id=? AND receiver_type=?)
            OR (sender_id=? AND sender_type=?       AND receiver_id=? AND receiver_type='user')
         ORDER BY created_at ASC
         LIMIT 200"
    );
    $stmt->bind_param('iisisi', $me_id, $with_id, $with_type, $with_id, $with_type, $me_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $messages = [];
    while ($row = $res->fetch_assoc()) {
        $messages[] = [
            'id'            => (int)$row['id'],
            'sender_id'     => (int)$row['sender_id'],
            'sender_type'   => $row['sender_type'],
            'receiver_id'   => (int)$row['receiver_id'],
            'receiver_type' => $row['receiver_type'],
            'message'       => $row['message'],
            'is_read'       => (bool)$row['is_read'],
            'created_at'    => $row['created_at'],
        ];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'messages' => $messages]);
    $conn->close();
    exit;
}

/* ─── UNREAD ──────────────────────────────────────────────── */
if ($action === 'unread') {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM blood_messages WHERE receiver_id=? AND is_read=0");
    $stmt->bind_param('i', $me_id);
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt']);
    $stmt->close();
    echo json_encode(['success' => true, 'unread' => $cnt]);
    $conn->close();
    exit;
}

echo json_encode(['success' => false, 'error' => 'unknown_action']);
$conn->close();
