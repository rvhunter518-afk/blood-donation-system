<?php
/**
 * message/api.php — LifeFlow BloodConnect Messaging API
 * High-performance backend using MySQLi with prepared statements
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $conn = getDbConnection();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'db_error', 'msg' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Auto-create tables if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS blood_messages (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_id     INT UNSIGNED NOT NULL,
        sender_type   ENUM('user','hospital') NOT NULL DEFAULT 'user',
        receiver_id   INT UNSIGNED NOT NULL,
        receiver_type ENUM('user','hospital') NOT NULL DEFAULT 'user',
        message       TEXT NOT NULL,
        is_read       TINYINT(1) NOT NULL DEFAULT 0,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_conv (sender_id, sender_type, receiver_id, receiver_type),
        INDEX idx_recv (receiver_id, receiver_type, is_read),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$conn->query("
    CREATE TABLE IF NOT EXISTS user_presence (
        user_id    INT UNSIGNED PRIMARY KEY,
        status     ENUM('online','away','offline') NOT NULL DEFAULT 'online',
        last_seen  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_last_seen (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper to gather all accounts for quick switcher
function getAllAccounts(mysqli $conn): array {
    $accounts = [];
    $uRes = $conn->query("SELECT id, full_name, role, blood_type FROM users ORDER BY id ASC");
    if ($uRes) {
        while ($u = $uRes->fetch_assoc()) {
            $accounts[] = [
                'id'         => (int)$u['id'],
                'type'       => 'user',
                'name'       => $u['full_name'],
                'role'       => $u['role'],
                'blood_type' => $u['blood_type'] ?? ''
            ];
        }
    }
    $hRes = $conn->query("SELECT id, hospital_name, hospital_type FROM hospitals ORDER BY id ASC");
    if ($hRes) {
        while ($h = $hRes->fetch_assoc()) {
            $accounts[] = [
                'id'         => (int)$h['id'],
                'type'       => 'hospital',
                'name'       => $h['hospital_name'],
                'role'       => 'hospital',
                'blood_type' => 'Hospital'
            ];
        }
    }
    return $accounts;
}

// ── switch_user ──────────────────────────────────────────────
if ($action === 'switch_user') {
    $userId   = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
    $userType = ($_GET['user_type'] ?? $_POST['user_type'] ?? 'user') === 'hospital' ? 'hospital' : 'user';

    if ($userType === 'hospital') {
        $stmt = $conn->prepare("SELECT id, hospital_name, official_email FROM hospitals WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $h = $stmt->get_result()->fetch_assoc();
        if ($h) {
            $_SESSION['hospital_id']   = (int)$h['id'];
            $_SESSION['hospital_name'] = $h['hospital_name'];
            unset($_SESSION['user_id']);
            unset($_SESSION['user_name']);
            $_SESSION['user_role']     = 'hospital';

            $user = [
                'id'         => (int)$h['id'],
                'full_name'  => $h['hospital_name'],
                'email'      => $h['official_email'],
                'blood_type' => '',
                'role'       => 'hospital',
                'user_type'  => 'hospital',
            ];
            echo json_encode(['success' => true, 'user' => $user]);
            exit;
        }
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, blood_type, role FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if ($u) {
            $_SESSION['user_id']   = (int)$u['id'];
            $_SESSION['user_name'] = $u['full_name'];
            $_SESSION['user_role'] = $u['role'];
            unset($_SESSION['hospital_id']);
            unset($_SESSION['hospital_name']);

            $conn->query("INSERT INTO user_presence (user_id, status, last_seen, updated_at) VALUES ({$u['id']}, 'online', NOW(), NOW()) ON DUPLICATE KEY UPDATE status = 'online', last_seen = NOW(), updated_at = NOW()");

            $user = [
                'id'         => (int)$u['id'],
                'full_name'  => $u['full_name'],
                'email'      => $u['email'],
                'blood_type' => $u['blood_type'] ?? '',
                'role'       => $u['role'],
                'user_type'  => 'user',
            ];
            echo json_encode(['success' => true, 'user' => $user]);
            exit;
        }
    }
    echo json_encode(['success' => false, 'error' => 'account_not_found']);
    exit;
}

// ── check_auth ───────────────────────────────────────────────
if ($action === 'check_auth') {
    if (!empty($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT id, full_name, email, blood_type, role FROM users WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['user_id']);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        if ($u) {
            $user = [
                'id'         => (int)$u['id'],
                'full_name'  => $u['full_name'],
                'email'      => $u['email'],
                'blood_type' => $u['blood_type'] ?? '',
                'role'       => $u['role'],
                'user_type'  => 'user',
            ];
            echo json_encode([
                'success'      => true,
                'user'         => $user,
                'all_accounts' => getAllAccounts($conn)
            ]);
            exit;
        }
    }
    if (!empty($_SESSION['hospital_id'])) {
        $stmt = $conn->prepare("SELECT id, hospital_name, official_email FROM hospitals WHERE id = ?");
        $stmt->bind_param('i', $_SESSION['hospital_id']);
        $stmt->execute();
        $h = $stmt->get_result()->fetch_assoc();
        if ($h) {
            $user = [
                'id'         => (int)$h['id'],
                'full_name'  => $h['hospital_name'],
                'email'      => $h['official_email'],
                'blood_type' => '',
                'role'       => 'hospital',
                'user_type'  => 'hospital',
            ];
            echo json_encode([
                'success'      => true,
                'user'         => $user,
                'all_accounts' => getAllAccounts($conn)
            ]);
            exit;
        }
    }

    // Auto-login fallback for direct access
    $firstUser = $conn->query("SELECT id, full_name, email, blood_type, role FROM users ORDER BY id ASC LIMIT 1")->fetch_assoc();
    if ($firstUser) {
        $_SESSION['user_id']   = (int)$firstUser['id'];
        $_SESSION['user_name'] = $firstUser['full_name'];
        $_SESSION['user_role'] = $firstUser['role'];
        $user = [
            'id'         => (int)$firstUser['id'],
            'full_name'  => $firstUser['full_name'],
            'email'      => $firstUser['email'],
            'blood_type' => $firstUser['blood_type'] ?? '',
            'role'       => $firstUser['role'],
            'user_type'  => 'user',
        ];
        echo json_encode([
            'success'      => true,
            'user'         => $user,
            'all_accounts' => getAllAccounts($conn)
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

// ── Require auth for remaining actions ───────────────────────
$myId   = null;
$myType = null;
if (!empty($_SESSION['user_id'])) {
    $myId   = (int)$_SESSION['user_id'];
    $myType = 'user';
} elseif (!empty($_SESSION['hospital_id'])) {
    $myId   = (int)$_SESSION['hospital_id'];
    $myType = 'hospital';
} else {
    $firstUser = $conn->query("SELECT id, full_name, role FROM users ORDER BY id ASC LIMIT 1")->fetch_assoc();
    if ($firstUser) {
        $myId   = (int)$firstUser['id'];
        $myType = 'user';
        $_SESSION['user_id']   = $myId;
        $_SESSION['user_name'] = $firstUser['full_name'];
        $_SESSION['user_role'] = $firstUser['role'];
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'not_logged_in']);
        exit;
    }
}

// ── get_users ────────────────────────────────────────────────
if ($action === 'get_users') {
    $contacts = [];

    $excludeSelf = ($myType === 'user') ? " AND u.id != {$myId}" : "";
    $sql = "
        SELECT u.id,
               u.full_name AS name,
               u.blood_type,
               u.city,
               u.role,
               COALESCE(p.status, 'offline') AS status,
               (SELECT COUNT(*) FROM blood_messages m
                WHERE m.sender_id = u.id AND m.sender_type = 'user'
                  AND m.receiver_id = ? AND m.receiver_type = ? AND m.is_read = 0) AS unread,
               (SELECT m.message FROM blood_messages m
                WHERE (m.sender_id = u.id AND m.sender_type = 'user' AND m.receiver_id = ? AND m.receiver_type = ?)
                   OR (m.sender_id = ? AND m.sender_type = ? AND m.receiver_id = u.id AND m.receiver_type = 'user')
                ORDER BY m.created_at DESC LIMIT 1) AS last_message,
               (SELECT m.created_at FROM blood_messages m
                WHERE (m.sender_id = u.id AND m.sender_type = 'user' AND m.receiver_id = ? AND m.receiver_type = ?)
                   OR (m.sender_id = ? AND m.sender_type = ? AND m.receiver_id = u.id AND m.receiver_type = 'user')
                ORDER BY m.created_at DESC LIMIT 1) AS last_time
        FROM users u
        LEFT JOIN user_presence p ON p.user_id = u.id
        WHERE 1=1 {$excludeSelf}
        ORDER BY last_time DESC, u.full_name ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isisisisis', $myId, $myType, $myId, $myType, $myId, $myType, $myId, $myType, $myId, $myType);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $contacts[] = [
            'id'           => (int)$r['id'],
            'type'         => 'user',
            'name'         => $r['name'],
            'blood_type'   => $r['blood_type'] ?? '',
            'city'         => $r['city'] ?? '',
            'role'         => $r['role'],
            'status'       => $r['status'],
            'unread'       => (int)$r['unread'],
            'last_message' => $r['last_message'] ?? '',
            'last_time'    => $r['last_time'] ?? '',
        ];
    }

    // Hospitals
    $htblCheck = $conn->query("SHOW TABLES LIKE 'hospitals'");
    if ($htblCheck && $htblCheck->num_rows > 0) {
        $excludeSelfHosp = ($myType === 'hospital') ? " AND h.id != {$myId}" : "";
        $hsql = "
            SELECT h.id,
                   h.hospital_name AS name,
                   h.city,
                   h.hospital_type AS role,
                   'offline' AS status,
                   (SELECT COUNT(*) FROM blood_messages m
                    WHERE m.sender_id = h.id AND m.sender_type = 'hospital'
                      AND m.receiver_id = ? AND m.receiver_type = ? AND m.is_read = 0) AS unread,
                   (SELECT m.message FROM blood_messages m
                    WHERE (m.sender_id = h.id AND m.sender_type = 'hospital' AND m.receiver_id = ? AND m.receiver_type = ?)
                       OR (m.sender_id = ? AND m.sender_type = ? AND m.receiver_id = h.id AND m.receiver_type = 'hospital')
                    ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                   (SELECT m.created_at FROM blood_messages m
                    WHERE (m.sender_id = h.id AND m.sender_type = 'hospital' AND m.receiver_id = ? AND m.receiver_type = ?)
                       OR (m.sender_id = ? AND m.sender_type = ? AND m.receiver_id = h.id AND m.receiver_type = 'hospital')
                    ORDER BY m.created_at DESC LIMIT 1) AS last_time
            FROM hospitals h
            WHERE 1=1 {$excludeSelfHosp}
            ORDER BY last_time DESC, h.hospital_name ASC
        ";
        $hstmt = $conn->prepare($hsql);
        $hstmt->bind_param('isisisisis', $myId, $myType, $myId, $myType, $myId, $myType, $myId, $myType, $myId, $myType);
        $hstmt->execute();
        $hres = $hstmt->get_result();
        while ($r = $hres->fetch_assoc()) {
            $contacts[] = [
                'id'           => (int)$r['id'],
                'type'         => 'hospital',
                'name'         => $r['name'],
                'blood_type'   => 'Hospital',
                'city'         => $r['city'] ?? '',
                'role'         => $r['role'],
                'status'       => 'online',
                'unread'       => (int)$r['unread'],
                'last_message' => $r['last_message'] ?? '',
                'last_time'    => $r['last_time'] ?? '',
            ];
        }
    }

    usort($contacts, fn($a, $b) => strcmp($b['last_time'] ?? '', $a['last_time'] ?? ''));

    echo json_encode(['success' => true, 'users' => $contacts]);
    exit;
}

// ── history ──────────────────────────────────────────────────
if ($action === 'history') {
    $withId   = (int)($_GET['with'] ?? 0);
    $withType = in_array($_GET['with_type'] ?? 'user', ['user', 'hospital']) ? ($_GET['with_type'] ?? 'user') : 'user';
    if (!$withId) {
        echo json_encode(['success' => false, 'error' => 'missing_with']);
        exit;
    }

    $mr = $conn->prepare("
        UPDATE blood_messages SET is_read = 1
        WHERE sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = ? AND is_read = 0
    ");
    $mr->bind_param('isis', $withId, $withType, $myId, $myType);
    $mr->execute();

    $st = $conn->prepare("
        SELECT id, sender_id, sender_type, receiver_id, receiver_type, message, is_read, created_at
        FROM blood_messages
        WHERE (sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = ?)
           OR (sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = ?)
        ORDER BY created_at ASC
        LIMIT 300
    ");
    $st->bind_param('isisisis', $myId, $myType, $withId, $withType, $withId, $withType, $myId, $myType);
    $st->execute();
    $res = $st->get_result();
    $msgs = [];
    while ($m = $res->fetch_assoc()) {
        $msgs[] = [
            'id'            => (int)$m['id'],
            'sender_id'     => (int)$m['sender_id'],
            'sender_type'   => $m['sender_type'],
            'receiver_id'   => (int)$m['receiver_id'],
            'receiver_type' => $m['receiver_type'],
            'message'       => $m['message'],
            'is_read'       => (int)$m['is_read'],
            'created_at'    => $m['created_at'],
        ];
    }

    echo json_encode(['success' => true, 'messages' => $msgs]);
    exit;
}

// ── send ─────────────────────────────────────────────────────
if ($action === 'send') {
    $receiverId   = (int)($_POST['receiver_id'] ?? 0);
    $receiverType = in_array($_POST['receiver_type'] ?? 'user', ['user', 'hospital']) ? ($_POST['receiver_type'] ?? 'user') : 'user';
    $message      = trim($_POST['message'] ?? '');

    if (!$receiverId || !$message) {
        echo json_encode(['success' => false, 'error' => 'missing_fields']);
        exit;
    }

    if ($receiverId === $myId && $receiverType === $myType) {
        echo json_encode(['success' => false, 'error' => 'cannot_message_self']);
        exit;
    }

    $st = $conn->prepare("
        INSERT INTO blood_messages (sender_id, sender_type, receiver_id, receiver_type, message, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $st->bind_param('isiss', $myId, $myType, $receiverId, $receiverType, $message);
    $st->execute();
    echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    exit;
}

// ── unread_count ─────────────────────────────────────────────
if ($action === 'unread_count' || $action === 'unread') {
    $st = $conn->prepare("SELECT COUNT(*) AS cnt FROM blood_messages WHERE receiver_id = ? AND receiver_type = ? AND is_read = 0");
    $st->bind_param('is', $myId, $myType);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $cnt = (int)($row['cnt'] ?? 0);
    echo json_encode(['success' => true, 'count' => $cnt, 'unread' => $cnt]);
    exit;
}

// ── heartbeat ────────────────────────────────────────────────
if ($action === 'heartbeat') {
    if ($myType === 'user') {
        $st = $conn->prepare("
            INSERT INTO user_presence (user_id, status, last_seen, updated_at)
            VALUES (?, 'online', NOW(), NOW())
            ON DUPLICATE KEY UPDATE status = 'online', last_seen = NOW(), updated_at = NOW()
        ");
        $st->bind_param('i', $myId);
        $st->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── clear_history ─────────────────────────────────────────────
if ($action === 'clear_history') {
    $withId   = (int)($_POST['with'] ?? 0);
    $withType = in_array($_POST['with_type'] ?? 'user', ['user', 'hospital']) ? ($_POST['with_type'] ?? 'user') : 'user';
    if (!$withId) {
        echo json_encode(['success' => false, 'error' => 'missing_with']);
        exit;
    }

    $st = $conn->prepare("
        DELETE FROM blood_messages
        WHERE (sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = ?)
           OR (sender_id = ? AND sender_type = ? AND receiver_id = ? AND receiver_type = ?)
    ");
    $st->bind_param('isisisis', $myId, $myType, $withId, $withType, $withId, $withType, $myId, $myType);
    $st->execute();
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'unknown_action']);
