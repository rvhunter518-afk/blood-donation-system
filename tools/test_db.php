<?php
$pdo = new PDO('mysql:host=localhost;dbname=blood_connect;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
echo 'Database connection successful!';
?>
