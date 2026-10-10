<?php
require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();
echo "--- quiz_completions ---\n";
$res = $conn->query("SHOW COLUMNS FROM quiz_completions");
while ($r = $res->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}
echo "--- gamification_profiles ---\n";
$res2 = $conn->query("SHOW COLUMNS FROM gamification_profiles");
while ($r = $res2->fetch_assoc()) {
    echo $r['Field'] . " (" . $r['Type'] . ")\n";
}

