<?php
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
if ($conn->connect_error) {
    echo "DB Connection Error: " . $conn->connect_error . "\n";
    exit(1);
}

$sql = file_get_contents(__DIR__ . '/../database/calls.sql');
if ($conn->multi_query($sql)) {
    do {
        if ($res = $conn->store_result()) {
            $res->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "SUCCESS: calls.sql executed successfully.\n";
} else {
    echo "SQL Error: " . $conn->error . "\n";
}

$conn->close();

