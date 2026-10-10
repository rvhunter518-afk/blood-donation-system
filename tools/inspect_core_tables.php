<?php
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
$tables = ['users', 'donors', 'blood_requests', 'donations', 'notifications', 'call_logs', 'user_presence'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    $res = $conn->query("DESCRIBE $t");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "  {$row['Field']} | {$row['Type']} | Null: {$row['Null']} | Key: {$row['Key']} | Default: {$row['Default']}\n";
        }
    } else {
        echo "  Table does not exist or error: " . $conn->error . "\n";
    }
}
