<?php
/**
 * menu_profile.php — LifeFlow Profile & Contacts API
 * Serves: ?action=me | ?action=contacts | ?action=update_profile
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

require_once __DIR__ . '/config/database.php';

try {
    $conn = getDbConnection();
} catch (RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

// Auto-create blood_messages table if not exists
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
        INDEX idx_recv (receiver_id, receiver_type, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$action = $_GET['action'] ?? $_POST['action'] ?? 'me';

// Determine who is logged in
$myId   = null;
$myType = null;

if (!empty($_SESSION['user_id'])) {
    $myId   = (int)$_SESSION['user_id'];
    $myType = 'user';
} elseif (!empty($_SESSION['hospital_id'])) {
    $myId   = (int)$_SESSION['hospital_id'];
    $myType = 'hospital';
} else {
    echo json_encode(['success' => false, 'error' => 'not_logged_in']);
    exit;
}

/* ─── ME ─────────────────────────────────────────────────── */
if ($action === 'me') {

    if ($myType === 'hospital') {
        // Hospital profile
        $stmt = $conn->prepare("
            SELECT id, hospital_name AS full_name, official_email AS email, contact_phone AS phone,
                   city, hospital_type AS role, status, created_at
            FROM hospitals WHERE id = ?
        ");
        $stmt->bind_param('i', $myId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) { echo json_encode(['success' => false, 'error' => 'hospital_not_found']); exit; }

        $msgCount = 0;
        $ms = $conn->prepare("SELECT COUNT(*) AS cnt FROM blood_messages WHERE (sender_id=? AND sender_type='hospital') OR (receiver_id=? AND receiver_type='hospital')");
        $ms->bind_param('ii', $myId, $myId);
        $ms->execute();
        $msgCount = (int)($ms->get_result()->fetch_assoc()['cnt']);
        $ms->close();

        $unread = 0;
        $ur = $conn->prepare("SELECT COUNT(*) AS cnt FROM blood_messages WHERE receiver_id=? AND receiver_type='hospital' AND is_read=0");
        $ur->bind_param('i', $myId);
        $ur->execute();
        $unread = (int)($ur->get_result()->fetch_assoc()['cnt']);
        $ur->close();

        echo json_encode([
            'success'         => true,
            'id'              => (int)$row['id'],
            'full_name'       => $row['full_name'] ?: 'Not provided',
            'email'           => $row['email'] ?: 'Not provided',
            'blood_type'      => '—',
            'phone'           => $row['phone'] ?: 'Not provided',
            'city'            => $row['city'] ?: 'Not provided',
            'role'            => 'hospital',
            'created_at'      => $row['created_at'],
            'total_donations' => 0,
            'availability'    => $row['status'],
            'last_donation'   => null,
            'message_count'   => $msgCount,
            'unread_count'    => $unread,
        ]);
        exit;
    }

    // User/Donor profile
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.email, u.blood_type, u.phone, u.city, u.role, u.created_at,
               d.total_donations, d.availability, d.last_donation_date
        FROM users u
        LEFT JOIN donors d ON d.user_id = u.id
        WHERE u.id = ?
    ");
    $stmt->bind_param('i', $myId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) { echo json_encode(['success' => false, 'error' => 'user_not_found']); exit; }

    $ms = $conn->prepare("SELECT COUNT(*) AS cnt FROM blood_messages WHERE (sender_id=? AND sender_type='user') OR (receiver_id=? AND receiver_type='user')");
    $ms->bind_param('ii', $myId, $myId);
    $ms->execute();
    $msgCount = (int)($ms->get_result()->fetch_assoc()['cnt']);
    $ms->close();

    $ur = $conn->prepare("SELECT COUNT(*) AS cnt FROM blood_messages WHERE receiver_id=? AND receiver_type='user' AND is_read=0");
    $ur->bind_param('i', $myId);
    $ur->execute();
    $unread = (int)($ur->get_result()->fetch_assoc()['cnt']);
    $ur->close();

    echo json_encode([
        'success'         => true,
        'id'              => (int)$row['id'],
        'full_name'       => $row['full_name'] ?: 'Not provided',
        'email'           => $row['email'] ?: 'Not provided',
        'blood_type'      => $row['blood_type'] ?: 'Not provided',
        'phone'           => $row['phone'] ?: 'Not provided',
        'city'            => $row['city'] ?: 'Not provided',
        'role'            => $row['role'],
        'created_at'      => $row['created_at'],
        'total_donations' => (int)($row['total_donations'] ?? 0),
        'availability'    => $row['availability'] ?? 'Not provided',
        'last_donation'   => $row['last_donation_date'] ?? null,
        'message_count'   => $msgCount,
        'unread_count'    => $unread,
    ]);
    exit;
}

/* ─── UPDATE PROFILE ─────────────────────────────────────── */
if ($action === 'update_profile' && $myType === 'user') {
    $full_name  = trim($_POST['full_name']  ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $city       = trim($_POST['city']       ?? '');
    $blood_type = trim($_POST['blood_type'] ?? '');

    if (empty($full_name)) {
        echo json_encode(['success' => false, 'error' => 'Name is required']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE users SET full_name=?, phone=?, city=?, blood_type=? WHERE id=?");
    $stmt->bind_param('ssssi', $full_name, $phone, $city, $blood_type, $myId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        // Update session
        $_SESSION['user_name'] = $full_name;
        // Also update donors table if user is a donor
        $chk = $conn->prepare("SELECT id FROM donors WHERE user_id=?");
        $chk->bind_param('i', $myId);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $ds = $conn->prepare("UPDATE donors SET blood_type=?, city=?, phone=? WHERE user_id=?");
            $ds->bind_param('sssi', $blood_type, $city, $phone, $myId);
            $ds->execute();
            $ds->close();
        }
        $chk->close();
        echo json_encode(['success' => true, 'msg' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed: ' . $conn->error]);
    }
    exit;
}

/* ─── CONTACTS ────────────────────────────────────────────── */
if ($action === 'contacts') {
    $contacts = [];

    // All users (excluding self if user session)
    $excludeSelf = ($myType === 'user') ? " AND u.id != $myId" : "";
    $stmt = $conn->prepare("
        SELECT u.id, u.full_name, u.role, u.blood_type, u.city,
               (SELECT m.message FROM blood_messages m
                WHERE ((m.sender_id=u.id AND m.sender_type='user' AND m.receiver_id=? AND m.receiver_type=?)
                    OR (m.sender_id=? AND m.sender_type=? AND m.receiver_id=u.id AND m.receiver_type='user'))
                ORDER BY m.created_at DESC LIMIT 1) AS last_msg,
               (SELECT m.created_at FROM blood_messages m
                WHERE ((m.sender_id=u.id AND m.sender_type='user' AND m.receiver_id=? AND m.receiver_type=?)
                    OR (m.sender_id=? AND m.sender_type=? AND m.receiver_id=u.id AND m.receiver_type='user'))
                ORDER BY m.created_at DESC LIMIT 1) AS last_time,
               (SELECT COUNT(*) FROM blood_messages m
                WHERE m.sender_id=u.id AND m.sender_type='user' AND m.receiver_id=? AND m.receiver_type=? AND m.is_read=0) AS unread
        FROM users u WHERE 1=1 $excludeSelf
        ORDER BY last_time DESC, u.full_name ASC
    ");
    $stmt->bind_param(
        'ssssssssss',
        $myId, $myType, $myId, $myType,
        $myId, $myType, $myId, $myType,
        $myId, $myType
    );
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $contacts[] = [
            'id'         => (int)$row['id'],
            'type'       => 'user',
            'name'       => $row['full_name'] ?: 'Unknown User',
            'role'       => $row['role'],
            'blood_type' => $row['blood_type'] ?? '',
            'city'       => $row['city'] ?? '',
            'last_msg'   => $row['last_msg'] ?? '',
            'last_time'  => $row['last_time'] ?? '',
            'unread'     => (int)$row['unread'],
        ];
    }
    $stmt->close();

    // Hospitals
    $htbl = $conn->query("SHOW TABLES LIKE 'hospitals'");
    if ($htbl && $htbl->num_rows > 0) {
        $excludeHosp = ($myType === 'hospital') ? " AND h.id != $myId" : "";
        $hstmt = $conn->prepare("
            SELECT h.id, h.hospital_name, h.hospital_type, h.city, h.contact_phone,
                   (SELECT m.message FROM blood_messages m
                    WHERE ((m.sender_id=h.id AND m.sender_type='hospital' AND m.receiver_id=? AND m.receiver_type=?)
                        OR (m.sender_id=? AND m.sender_type=? AND m.receiver_id=h.id AND m.receiver_type='hospital'))
                    ORDER BY m.created_at DESC LIMIT 1) AS last_msg,
                   (SELECT m.created_at FROM blood_messages m
                    WHERE ((m.sender_id=h.id AND m.sender_type='hospital' AND m.receiver_id=? AND m.receiver_type=?)
                        OR (m.sender_id=? AND m.sender_type=? AND m.receiver_id=h.id AND m.receiver_type='hospital'))
                    ORDER BY m.created_at DESC LIMIT 1) AS last_time,
                   (SELECT COUNT(*) FROM blood_messages m
                    WHERE m.sender_id=h.id AND m.sender_type='hospital' AND m.receiver_id=? AND m.receiver_type=? AND m.is_read=0) AS unread
            FROM hospitals h WHERE h.status = 'active' $excludeHosp
            ORDER BY last_time DESC, h.hospital_name ASC
        ");
        $hstmt->bind_param(
            'ssssssssss',
            $myId, $myType, $myId, $myType,
            $myId, $myType, $myId, $myType,
            $myId, $myType
        );
        $hstmt->execute();
        $hres = $hstmt->get_result();
        while ($hrow = $hres->fetch_assoc()) {
            $contacts[] = [
                'id'         => (int)$hrow['id'],
                'type'       => 'hospital',
                'name'       => $hrow['hospital_name'] ?: 'Unknown Hospital',
                'role'       => $hrow['hospital_type'],
                'blood_type' => '',
                'city'       => $hrow['city'] ?? '',
                'last_msg'   => $hrow['last_msg'] ?? '',
                'last_time'  => $hrow['last_time'] ?? '',
                'unread'     => (int)$hrow['unread'],
            ];
        }
        $hstmt->close();
    }

    usort($contacts, fn($a, $b) => strcmp($b['last_time'] ?? '', $a['last_time'] ?? ''));
    echo json_encode(['success' => true, 'contacts' => $contacts]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'unknown_action']);
