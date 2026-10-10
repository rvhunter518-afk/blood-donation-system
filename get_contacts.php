<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_OFF);

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Please sign in to access the emergency call directory.']);
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Please ensure MySQL is running.']);
    exit;
}
$conn->set_charset('utf8mb4');

$currentUserId = (int) $_SESSION['user_id'];
$currentUserRole = $_SESSION['user_role'] ?? 'user';
$typeFilter = trim($_GET['type'] ?? 'all'); // 'all', 'users', 'donors'
$search = trim($_GET['search'] ?? '');

// Fetch open requests to attach to users if they need blood
$openRequests = [];
$reqSql = "SELECT id, requester_id, patient_name, blood_type, units, hospital, city, urgency, contact_phone, created_at 
           FROM blood_requests 
           WHERE status = 'open' 
           ORDER BY FIELD(urgency, 'critical', 'urgent', 'routine'), created_at DESC";
$reqResult = $conn->query($reqSql);
if ($reqResult) {
    while ($rRow = $reqResult->fetch_assoc()) {
        $reqId = (int) $rRow['requester_id'];
        if (!isset($openRequests[$reqId])) {
            $openRequests[$reqId] = [];
        }
        $openRequests[$reqId][] = [
            'request_id' => (int) $rRow['id'],
            'patient_name' => $rRow['patient_name'],
            'blood_type' => $rRow['blood_type'],
            'units' => (int) $rRow['units'],
            'hospital' => $rRow['hospital'],
            'city' => $rRow['city'],
            'urgency' => $rRow['urgency'],
            'contact_phone' => $rRow['contact_phone'] ?: '',
            'created_at' => $rRow['created_at']
        ];
    }
}

// Fetch ONLY registered users/donors who have a valid phone number
$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.blood_type, u.city, u.role, u.created_at, 
               d.availability, d.total_donations, d.last_donation_date
        FROM users u
        LEFT JOIN donors d ON d.user_id = u.id
        WHERE u.phone IS NOT NULL AND TRIM(u.phone) != ''";

$params = [];
$types = '';

if ($typeFilter === 'users') {
    $sql .= " AND u.role = 'user'";
} elseif ($typeFilter === 'donors') {
    $sql .= " AND u.role = 'donor'";
}

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.city LIKE ? OR u.blood_type LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

// Order: Put opposite role first if viewer is donor or user, then by name
if ($currentUserRole === 'donor') {
    $sql .= " ORDER BY (u.role = 'user') DESC, u.full_name ASC";
} else {
    $sql .= " ORDER BY (u.role = 'donor') DESC, u.full_name ASC";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare contacts query.']);
    $conn->close();
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$contacts = [];
$stats = [
    'total' => 0,
    'donors_count' => 0,
    'users_count' => 0
];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $uId = (int) $row['id'];
        $role = $row['role'];
        $stats['total']++;
        if ($role === 'donor') {
            $stats['donors_count']++;
        } else {
            $stats['users_count']++;
        }

        $activeReqs = $openRequests[$uId] ?? [];
        $hasActiveNeed = !empty($activeReqs);

        // Format registered date
        $regTime = strtotime($row['created_at']);
        $regDate = $regTime ? date('M j, Y', $regTime) : 'Registered Member';

        $contacts[] = [
            'id' => $uId,
            'name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'blood_type' => $row['blood_type'] ?: 'Unknown',
            'city' => $row['city'] ?: 'Location not specified',
            'role' => $role,
            'role_label' => $role === 'donor' ? 'Registered Donor' : 'Registered Blood Requester',
            'availability' => $row['availability'] ?? 'available',
            'total_donations' => (int) ($row['total_donations'] ?? 0),
            'registered_date' => $regDate,
            'is_self' => ($uId === $currentUserId),
            'has_active_need' => $hasActiveNeed,
            'active_requests' => $activeReqs
        ];
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'current_user_id' => $currentUserId,
    'current_user_role' => $currentUserRole,
    'stats' => $stats,
    'contacts' => $contacts
]);
