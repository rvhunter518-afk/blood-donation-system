<?php
/**
 * feedback_api.php — Handles user feedback submission and AI acknowledgment generation
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');

require_once __DIR__ . '/config/database.php';

try {
    $conn = getDbConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Auto-create feedback table if not exists
$conn->query("
    CREATE TABLE IF NOT EXISTS user_feedback (
        id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id       INT UNSIGNED NULL,
        user_name     VARCHAR(120) NULL,
        user_role     VARCHAR(50) NOT NULL DEFAULT 'user',
        email         VARCHAR(190) NOT NULL,
        rating        TINYINT UNSIGNED NULL DEFAULT 5,
        category      VARCHAR(60) NULL DEFAULT 'General',
        feedback      TEXT NOT NULL,
        ai_response   TEXT NULL,
        created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_role (user_role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$email    = trim($_POST['email'] ?? '');
$feedback = trim($_POST['feedback'] ?? '');
$role     = trim(strtolower($_POST['role'] ?? 'user'));
$rating   = (int)($_POST['rating'] ?? 5);
$category = trim($_POST['category'] ?? 'General Experience');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address']);
    exit;
}

if (empty($feedback) || strlen($feedback) < 5) {
    echo json_encode(['success' => false, 'error' => 'Please write at least 5 characters of feedback']);
    exit;
}

// Get user info if logged in
$userId   = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'Community Member';

if (!$userId && !empty($_SESSION['hospital_id'])) {
    $userId   = $_SESSION['hospital_id'];
    $userName = $_SESSION['hospital_name'] ?? 'Hospital Partner';
    $role     = 'hospital';
}

// AI Acknowledgment Generator based on role, rating, and feedback content
$ticketId = 'LF-FB-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

$fbLower = strtolower($feedback);
$sentiment = 'positive';
if (preg_match('/(slow|bug|error|problem|issue|fail|bad|hard|confus|worst|hate|broken|not working)/i', $fbLower)) {
    $sentiment = 'issue';
} elseif (preg_match('/(suggest|feature|add|hope|could|would like|request|improve)/i', $fbLower)) {
    $sentiment = 'suggestion';
}

$roleTitle = 'Blood Requester';
$roleGreeting = "Thank you for reaching out as a blood recipient/requester.";
if ($role === 'donor') {
    $roleTitle = 'Blood Donor Hero';
    $roleGreeting = "We deeply appreciate your dedication as a registered LifeFlow blood donor.";
} elseif ($role === 'admin') {
    $roleTitle = 'System Administrator';
    $roleGreeting = "Thank you for reviewing system workflows from an administrative standpoint.";
} elseif ($role === 'hospital') {
    $roleTitle = 'Healthcare Institution Partner';
    $roleGreeting = "Thank you for collaborating with our medical network.";
}

$aiMessage = "";

if ($sentiment === 'issue') {
    $aiMessage = "Hello {$userName}! Our AI sentiment analyzer has flagged your feedback regarding a system experience issue. {$roleGreeting} We take these technical and logistical matters with the utmost seriousness. Your report has been dispatched with high priority to our engineering support desk under Reference Ticket #{$ticketId}. We are already looking into this and will reach out to {$email} if further diagnostic details are needed.";
} elseif ($sentiment === 'suggestion') {
    $aiMessage = "Hello {$userName}! Thank you for your insightful suggestion. {$roleGreeting} Innovative ideas like yours help us refine our real-time matching algorithms and streamline donor dispatch workflows. Our product and clinical team has logged your idea under Reference Ticket #{$ticketId} for our upcoming platform roadmap update.";
} else {
    $aiMessage = "Hello {$userName}! Thank you so much for your generous and encouraging feedback! {$roleGreeting} Your kind words motivate our medical staff, volunteer donors, and developers to continue expanding LifeFlow's lifesaving reach. Your submission is recorded under Reference Ticket #{$ticketId}. Have a wonderful day saving lives!";
}

// Save to database
$stmt = $conn->prepare("
    INSERT INTO user_feedback (user_id, user_name, user_role, email, rating, category, feedback, ai_response)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param('isssisss', $userId, $userName, $role, $email, $rating, $category, $feedback, $aiMessage);
$stmt->execute();
$insertId = $stmt->insert_id;
$stmt->close();

echo json_encode([
    'success'     => true,
    'ticket_id'   => $ticketId,
    'user_name'   => $userName,
    'user_role'   => $roleTitle,
    'ai_response' => $aiMessage,
    'message'     => 'Feedback received successfully!'
]);
exit;
