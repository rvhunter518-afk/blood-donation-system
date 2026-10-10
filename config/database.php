<?php
/**
 * BloodConnect — Database Connection Configuration
 * config/database.php
 */

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'blood_connect');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

/**
 * Returns a shared or new MySQLi instance.
 */
function getDbConnection(): mysqli {
    static $conn = null;
    if ($conn !== null && @$conn->ping()) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        error_log("[Database Error] MySQLi connection failed: " . $conn->connect_error);
        throw new RuntimeException("Database connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * Returns a shared or new PDO instance.
 */
function getDbPdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("[Database Error] PDO connection failed: " . $e->getMessage());
        throw new RuntimeException("Database connection failed.");
    }
}

