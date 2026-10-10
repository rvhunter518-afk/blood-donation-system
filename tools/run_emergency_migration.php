<?php
/**
 * tools/run_emergency_migration.php
 * Adds columns for Smart Emergency Matching & Response System
 */
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
$conn->set_charset('utf8mb4');

echo "Running Emergency Matching DB Migrations...\n";

// 1. Update donors table
$donorAlters = [
    "ALTER TABLE donors MODIFY COLUMN availability ENUM('available','temp_unavailable','unavailable') NOT NULL DEFAULT 'available'",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS area VARCHAR(120) NULL",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS location_updated_at DATETIME NULL",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS last_donation_date DATE NULL",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS next_eligible_date DATE NULL",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS eligibility_status ENUM('eligible','ineligible','cooldown') NOT NULL DEFAULT 'eligible'",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS emergency_responses INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS total_requests_received INT UNSIGNED NOT NULL DEFAULT 0",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS response_rate DECIMAL(5,2) NOT NULL DEFAULT 96.00",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS donor_code VARCHAR(30) NULL",
    "ALTER TABLE donors ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) NOT NULL DEFAULT 1"
];

foreach ($donorAlters as $sql) {
    if ($conn->query($sql)) {
        echo "OK: $sql\n";
    } else {
        echo "Note/Warn: " . $conn->error . " -> $sql\n";
    }
}

// 2. Update blood_requests table
$requestAlters = [
    "ALTER TABLE blood_requests MODIFY COLUMN urgency ENUM('critical','emergency','urgent','normal','routine') NOT NULL DEFAULT 'urgent'",
    "ALTER TABLE blood_requests ADD COLUMN IF NOT EXISTS is_emergency TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE blood_requests ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8) NULL",
    "ALTER TABLE blood_requests ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8) NULL",
    "ALTER TABLE blood_requests ADD COLUMN IF NOT EXISTS area VARCHAR(120) NULL",
    "ALTER TABLE blood_requests ADD COLUMN IF NOT EXISTS request_code VARCHAR(30) NULL",
    "ALTER TABLE blood_requests ADD COLUMN IF NOT EXISTS tracking_status ENUM('created','finding_donors','contacted','accepted','in_transit','completed','cancelled') NOT NULL DEFAULT 'created'",
    "ALTER TABLE blood_requests ADD COLUMN IF NOT EXISTS accepted_donor_id INT UNSIGNED NULL"
];

foreach ($requestAlters as $sql) {
    if ($conn->query($sql)) {
        echo "OK: $sql\n";
    } else {
        echo "Note/Warn: " . $conn->error . " -> $sql\n";
    }
}

// 3. Create request_matches table
$sqlMatches = "CREATE TABLE IF NOT EXISTS request_matches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    donor_id INT UNSIGNED NOT NULL,
    match_score DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    distance_km DECIMAL(8, 2) NULL,
    compatibility_score DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
    status ENUM('matched','contacted','accepted','rejected','completed') NOT NULL DEFAULT 'matched',
    notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    INDEX idx_request (request_id),
    INDEX idx_donor (donor_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sqlMatches)) {
    echo "OK: request_matches table ready\n";
} else {
    echo "Error request_matches: " . $conn->error . "\n";
}

// 4. Update mock donors with sample coordinates and realistic response rates
// Set sample coordinates around city center (e.g. 13.0827, 80.2707 / Chennai, or generic metro coordinates with varied distances)
$seedDonors = [
    ['O+', 13.0850, 80.2720, 'Central Area', 8, 6, 96.00, 'BC-DNR-10482', '2026-06-10', '2026-09-08', 'eligible'],
    ['O-', 13.0910, 80.2800, 'North Park', 12, 10, 98.00, 'BC-DNR-10115', '2026-05-15', '2026-08-15', 'eligible'],
    ['A+', 13.0780, 80.2650, 'South Gate', 5, 4, 91.00, 'BC-DNR-10672', '2026-06-01', '2026-08-30', 'eligible'],
    ['B+', 13.0890, 80.2600, 'West End', 6, 5, 94.00, 'BC-DNR-10839', '2026-05-20', '2026-08-20', 'eligible'],
    ['AB+', 13.0950, 80.2750, 'Metro Sector', 9, 8, 95.00, 'BC-DNR-10901', '2026-06-15', '2026-09-13', 'eligible']
];

$donorsList = $conn->query("SELECT id FROM donors ORDER BY id ASC");
if ($donorsList && $donorsList->num_rows > 0) {
    $idx = 0;
    while ($d = $donorsList->fetch_assoc()) {
        $preset = $seedDonors[$idx % count($seedDonors)];
        $lat = $preset[1] + (mt_rand(-15, 15) / 1000.0);
        $lng = $preset[2] + (mt_rand(-15, 15) / 1000.0);
        $code = 'BC-DNR-' . (10400 + (int)$d['id']);
        $conn->query("UPDATE donors SET 
            latitude = $lat, 
            longitude = $lng, 
            area = '{$preset[3]}', 
            total_donations = {$preset[4]},
            emergency_responses = {$preset[5]},
            response_rate = {$preset[6]},
            donor_code = '$code',
            last_donation_date = '{$preset[8]}',
            next_eligible_date = '{$preset[9]}',
            eligibility_status = '{$preset[10]}',
            availability = 'available',
            is_verified = 1
            WHERE id = {$d['id']}");
        $idx++;
    }
    echo "Updated $idx existing donors with coordinates, donor codes, and donation statistics.\n";
}

echo "Emergency Migration Completed Successfully!\n";

