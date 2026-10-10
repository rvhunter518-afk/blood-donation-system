<?php
$conn = new mysqli('localhost','root','','blood_connect');
$tables = $conn->query('SHOW TABLES')->fetch_all();
foreach($tables as $t) { echo $t[0] . "\n"; }
echo "\n--- Donors table columns ---\n";
$cols = $conn->query('DESCRIBE donors')->fetch_all(MYSQLI_ASSOC);
foreach($cols as $c) { echo $c['Field'] . ' (' . $c['Type'] . ")\n"; }
echo "\n--- Blood requests columns ---\n";
$cols2 = $conn->query('DESCRIBE blood_requests')->fetch_all(MYSQLI_ASSOC);
foreach($cols2 as $c) { echo $c['Field'] . ' (' . $c['Type'] . ")\n"; }
echo "\n--- Users columns ---\n";
$cols3 = $conn->query('DESCRIBE users')->fetch_all(MYSQLI_ASSOC);
foreach($cols3 as $c) { echo $c['Field'] . ' (' . $c['Type'] . ")\n"; }

