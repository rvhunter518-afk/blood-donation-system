<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'blood_connect';
$user = 'root';
$pass = '';

// Create connection with MySQLi
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Keep older installations usable while the role column is added once.
$roleColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($roleColumn && $roleColumn->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role ENUM('user', 'donor') NOT NULL DEFAULT 'donor'");
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $requestedRole = ($_POST['role'] ?? 'user') === 'donor' ? 'donor' : 'user';

    if (empty($email) || empty($password)) {
        header('Location: login.html?error=missing');
        exit;
    }

    $stmt = $conn->prepare('SELECT id, full_name, password_hash, role FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    // Verify password hash
    if ($user && password_verify($password, $user['password_hash'])) {
        $actualRole = !empty($user['role']) ? $user['role'] : $requestedRole;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_role'] = $actualRole;
        
        $destination = ($actualRole === 'donor') ? 'donor/index.php' : 'user/user.html';
        header('Location: ' . $destination);
        exit;
    } else {
        header('Location: login.html?error=invalid');
        exit;
    }
} elseif ($action === 'register') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $blood_type = trim($_POST['blood_type'] ?? 'O+');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $role = ($_POST['role'] ?? 'user') === 'donor' ? 'donor' : 'user';

    if (empty($full_name) || empty($email) || empty($password)) {
        header('Location: register.html?error=missing');
        exit;
    }

    // Check if email already exists
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->fetch_assoc()) {
        header('Location: register.html?error=exists');
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare('INSERT INTO users (full_name, email, password_hash, blood_type, phone, city, role) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sssssss', $full_name, $email, $hashed, $blood_type, $phone, $city, $role);
    
    if (!$stmt->execute()) {
        header('Location: register.html?error=failed');
        exit;
    }

    $userId = $conn->insert_id;
    if ($role === 'donor') {
        $donorCity = !empty($city) ? $city : 'Unknown';
        $donorBlood = !empty($blood_type) ? $blood_type : 'O+';
        $donorStmt = $conn->prepare('INSERT INTO donors (user_id, blood_type, city, phone) VALUES (?, ?, ?, ?)');
        $donorStmt->bind_param('isss', $userId, $donorBlood, $donorCity, $phone);
        $donorStmt->execute();
        $donorStmt->close();
    }
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $full_name;
    $_SESSION['user_role'] = $role;
    
    $destination = ($role === 'donor') ? 'donor/index.php' : 'user/user.html';
    header('Location: ' . $destination);
    exit;
} else {
    header('Location: login.html');
    exit;
}

$conn->close();
?>