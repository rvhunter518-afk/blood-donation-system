<?php
// Hospital Blood Inventory & Deficit API
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli('localhost', 'root', '', 'blood_connect');

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$conn->set_charset('utf8mb4');

$bloodGroups = ['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];

// Aggregate inventory from hospital_blood_inventory table
$query = "SELECT blood_group, SUM(available_units) as total_available, MAX(minimum_units) as target_minimum 
          FROM hospital_blood_inventory 
          GROUP BY blood_group";
$stmt = $conn->query($query);
$dbData = [];
if ($stmt) {
    while ($row = $stmt->fetch_assoc()) {
        $dbData[$row['blood_group']] = [
            'available' => (int)$row['total_available'],
            'minimum' => (int)$row['target_minimum']
        ];
    }
}

// Fallback/baseline minimums if table was partially populated
$baseline = [
    'O+'  => ['available' => 6,  'minimum' => 15],
    'O-'  => ['available' => 2,  'minimum' => 8],
    'A+'  => ['available' => 8,  'minimum' => 12],
    'A-'  => ['available' => 3,  'minimum' => 6],
    'B+'  => ['available' => 11, 'minimum' => 10],
    'B-'  => ['available' => 2,  'minimum' => 5],
    'AB+' => ['available' => 9,  'minimum' => 8],
    'AB-' => ['available' => 1,  'minimum' => 4],
];

$groups = [];
$totalUnits = 0;
$criticalCount = 0;
$lowCount = 0;
$safeCount = 0;

foreach ($bloodGroups as $bg) {
    $avail = isset($dbData[$bg]) ? $dbData[$bg]['available'] : $baseline[$bg]['available'];
    $min   = isset($dbData[$bg]) && $dbData[$bg]['minimum'] > 0 ? $dbData[$bg]['minimum'] : $baseline[$bg]['minimum'];

    $totalUnits += $avail;

    $ratio = $min > 0 ? ($avail / $min) : 1;
    if ($ratio < 0.5) {
        $status = 'critical';
        $badge = 'Critical Deficit';
        $color = '#ef4444';
        $criticalCount++;
    } elseif ($ratio < 1.0) {
        $status = 'low';
        $badge = 'Low Stock';
        $color = '#f59e0b';
        $lowCount++;
    } else {
        $status = 'safe';
        $badge = 'Safe Reserve';
        $color = '#10b981';
        $safeCount++;
    }

    $groups[] = [
        'blood_group' => $bg,
        'available'   => $avail,
        'minimum'     => $min,
        'color'       => $color,
        'status'      => $status,
        'badge'       => $badge
    ];
}

$conn->close();

echo json_encode([
    'success' => true,
    'summary' => [
        'total_units'    => $totalUnits,
        'critical_count' => $criticalCount,
        'low_count'      => $lowCount,
        'safe_count'     => $safeCount
    ],
    'groups' => $groups
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
