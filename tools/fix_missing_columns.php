<?php
/**
 * tools/fix_missing_columns.php — Add missing columns to gamification_profiles
 * Run once: php fix_missing_columns.php
 */
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) { die("Connect failed: " . $conn->connect_error . "\n"); }

$alterations = [
    "ALTER TABLE gamification_profiles ADD COLUMN IF NOT EXISTS xp INT NOT NULL DEFAULT 0",
    "ALTER TABLE gamification_profiles ADD COLUMN IF NOT EXISTS current_level INT NOT NULL DEFAULT 1",
    "ALTER TABLE gamification_profiles ADD COLUMN IF NOT EXISTS current_streak INT NOT NULL DEFAULT 0",
    "ALTER TABLE gamification_profiles ADD COLUMN IF NOT EXISTS longest_streak INT NOT NULL DEFAULT 0",
    "ALTER TABLE gamification_profiles ADD COLUMN IF NOT EXISTS last_activity_date DATE NULL DEFAULT NULL",
];

foreach ($alterations as $sql) {
    if ($conn->query($sql)) {
        echo "OK: $sql\n";
    } else {
        echo "WARN: " . $conn->error . " → $sql\n";
    }
}

// Show current structure
$cols = $conn->query("DESCRIBE gamification_profiles")->fetch_all(MYSQLI_ASSOC);
echo "\nCurrent columns:\n";
foreach ($cols as $c) { echo "  {$c['Field']} ({$c['Type']})\n"; }

// Check users table has first_name and last_name
echo "\nUsers table columns:\n";
$ucols = $conn->query("DESCRIBE users")->fetch_all(MYSQLI_ASSOC);
foreach ($ucols as $c) { echo "  {$c['Field']} ({$c['Type']})\n"; }

echo "\nDone!\n";

