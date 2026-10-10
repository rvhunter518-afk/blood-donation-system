<?php
/**
 * api/get_map_locations.php
 * Provides geo-coordinates and details for:
 * - Donors (for User view and Hospital view)
 * - Blood Requesters / Patients (for Donor view and Hospital view)
 * - Hospitals (for all views)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

$filterType  = trim($_GET['type'] ?? 'all'); // 'all', 'donors', 'requests', 'hospitals'
$filterBlood = trim($_GET['blood_type'] ?? '');
$filterCity  = trim($_GET['city'] ?? '');

$donors    = [];
$requests  = [];
$hospitals = [];

// Fallback coordinate dictionary if lat/long happens to be null
$fallbackCityCoords = [
    'coimbatore' => [11.0168, 76.9558],
    'rs puram'   => [11.0086, 76.9510],
    'gandhipuram'=> [11.0180, 76.9670],
    'peelamedu'  => [11.0315, 77.0175],
    'avinashi'   => [11.1931, 77.2690],
    'tirrupur'   => [11.1085, 77.3411],
    'tiruppur'   => [11.1085, 77.3411],
    'erode'      => [11.3410, 77.7172],
    'salem'      => [11.6643, 78.1460],
    'chennai'    => [13.0827, 80.2707],
    'madurai'    => [9.9252,  78.1198],
    'trichy'     => [10.7905, 78.7047],
    'pollachi'   => [10.6586, 77.0094],
];

function resolveCoord($lat, $lng, $city, $area, $id) {
    global $fallbackCityCoords;
    if (!empty($lat) && !empty($lng) && (float)$lat != 0) {
        return [(float)$lat, (float)$lng];
    }
    $kc = strtolower(trim($city));
    $ka = strtolower(trim($area));
    $base = $fallbackCityCoords[$ka] ?? $fallbackCityCoords[$kc] ?? [11.0168, 76.9558];
    // Deterministic offset per id so multiple markers don't overlap completely
    $offsetLat = (($id % 13) - 6) * 0.0035;
    $offsetLng = (($id % 17) - 8) * 0.0035;
    return [$base[0] + $offsetLat, $base[1] + $offsetLng];
}

// 1. Fetch Donors (available blood donors)
if ($filterType === 'all' || $filterType === 'donors') {
    $where = ["d.availability = 'available'"];
    $params = [];
    $types = '';

    if (!empty($filterBlood)) {
        $where[] = "d.blood_type = ?";
        $params[] = $filterBlood;
        $types .= 's';
    }
    if (!empty($filterCity)) {
        $where[] = "(d.city LIKE ? OR d.area LIKE ?)";
        $params[] = "%$filterCity%";
        $params[] = "%$filterCity%";
        $types .= 'ss';
    }

    $sql = "
        SELECT d.id, d.user_id, u.full_name, d.blood_type, d.city, d.area,
               d.latitude, d.longitude, d.phone, d.availability, d.emergency_availability,
               d.total_donations, d.preferred_language, d.eligibility_status, d.preliminary_result
        FROM donors d
        JOIN users u ON u.id = d.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY d.id DESC LIMIT 100
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        list($lat, $lng) = resolveCoord($row['latitude'], $row['longitude'], $row['city'], $row['area'], $row['id']);
        $donors[] = [
            'id'                     => (int)$row['id'],
            'user_id'                => (int)$row['user_id'],
            'name'                   => $row['full_name'],
            'blood_type'             => $row['blood_type'],
            'city'                   => $row['city'],
            'area'                   => $row['area'] ?: '',
            'latitude'               => $lat,
            'longitude'              => $lng,
            'phone'                  => $row['phone'] ?: '',
            'availability'           => $row['availability'],
            'emergency_availability' => $row['emergency_availability'] ?: 'available',
            'total_donations'        => (int)$row['total_donations'],
            'language'               => $row['preferred_language'] ?: 'English',
            'status'                 => $row['preliminary_result'] ?: 'eligible'
        ];
    }
    $stmt->close();
}

// 2. Fetch Blood Requests / Users in need of blood
if ($filterType === 'all' || $filterType === 'requests') {
    $where = ["r.status IN ('open', 'matched')"];
    $params = [];
    $types = '';

    if (!empty($filterBlood)) {
        $where[] = "r.blood_type = ?";
        $params[] = $filterBlood;
        $types .= 's';
    }
    if (!empty($filterCity)) {
        $where[] = "(r.city LIKE ? OR r.hospital LIKE ?)";
        $params[] = "%$filterCity%";
        $params[] = "%$filterCity%";
        $types .= 'ss';
    }

    $sql = "
        SELECT r.id, r.requester_id, r.patient_name, r.blood_type, r.units,
               r.hospital, r.city, r.area, r.urgency, r.status,
               r.latitude, r.longitude, r.created_at,
               u.phone AS requester_phone, u.full_name AS requester_name
        FROM blood_requests r
        LEFT JOIN users u ON u.id = r.requester_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(r.urgency, 'critical', 'urgent', 'routine'), r.id DESC LIMIT 100
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        list($lat, $lng) = resolveCoord($row['latitude'], $row['longitude'], $row['city'], $row['area'], $row['id']);
        $requests[] = [
            'id'              => (int)$row['id'],
            'requester_id'    => (int)($row['requester_id'] ?? 0),
            'patient_name'    => $row['patient_name'],
            'requester_name'  => $row['requester_name'] ?: 'Family / Attendant',
            'requester_phone' => $row['requester_phone'] ?: '',
            'blood_type'      => $row['blood_type'],
            'units'           => (int)$row['units'],
            'hospital'        => $row['hospital'] ?: 'Hospital Center',
            'city'            => $row['city'],
            'area'            => $row['area'] ?: '',
            'urgency'         => $row['urgency'],
            'status'          => $row['status'],
            'latitude'        => $lat,
            'longitude'       => $lng,
            'created_at'      => $row['created_at']
        ];
    }
    $stmt->close();
}

// 3. Fetch Hospitals
if ($filterType === 'all' || $filterType === 'hospitals') {
    $res = $conn->query("
        SELECT id, hospital_name, hospital_type, address, city,
               contact_phone, emergency_phone, has_blood_bank, latitude, longitude
        FROM hospitals
        WHERE status = 'active'
        ORDER BY id ASC LIMIT 50
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            list($lat, $lng) = resolveCoord($row['latitude'], $row['longitude'], $row['city'], $row['address'], $row['id']);
            $hospitals[] = [
                'id'             => (int)$row['id'],
                'name'           => $row['hospital_name'],
                'type'           => $row['hospital_type'] ?: 'government',
                'city'           => $row['city'],
                'address'        => $row['address'] ?: '',
                'phone'          => $row['contact_phone'] ?: ($row['emergency_phone'] ?: ''),
                'has_blood_bank' => (bool)$row['has_blood_bank'],
                'latitude'       => $lat,
                'longitude'      => $lng
            ];
        }
    }
}

echo json_encode([
    'success'   => true,
    'counts'    => [
        'donors'    => count($donors),
        'requests'  => count($requests),
        'hospitals' => count($hospitals)
    ],
    'donors'    => $donors,
    'requests'  => $requests,
    'hospitals' => $hospitals
]);
