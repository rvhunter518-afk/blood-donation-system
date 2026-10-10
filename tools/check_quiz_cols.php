<?php
$conn = new mysqli('localhost', 'root', '', 'blood_connect');
$cols = $conn->query("DESCRIBE quiz_questions")->fetch_all(MYSQLI_ASSOC);
foreach ($cols as $c) {
    echo "{$c['Field']} ({$c['Type']})\n";
}

