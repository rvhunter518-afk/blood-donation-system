<?php
/**
 * BloodConnect — Learning Platform Migration Script
 * tools/migrate_learning_platform.php
 *
 * Sets up the complete database schema for 100+ Books, PDF Reader Progress,
 * Quizzes, Score Tiers (Low/Med/High), XP, Streaks, Levels, and Badges.
 */

require_once __DIR__ . '/../config/database.php';
$conn = getDbConnection();

echo "Starting BloodConnect Learning Platform migration...\n";

// 1. Check and upgrade gamification_profiles
$checkCols = $conn->query("SHOW COLUMNS FROM gamification_profiles");
$existingCols = [];
while ($row = $checkCols->fetch_assoc()) {
    $existingCols[] = $row['Field'];
}

if (!in_array('xp', $existingCols)) {
    $conn->query("ALTER TABLE gamification_profiles ADD COLUMN xp INT UNSIGNED NOT NULL DEFAULT 0 AFTER points");
}
if (!in_array('current_level', $existingCols)) {
    $conn->query("ALTER TABLE gamification_profiles ADD COLUMN current_level TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER xp");
}
if (!in_array('current_streak', $existingCols)) {
    $conn->query("ALTER TABLE gamification_profiles ADD COLUMN current_streak INT UNSIGNED NOT NULL DEFAULT 0 AFTER current_level");
}
if (!in_array('longest_streak', $existingCols)) {
    $conn->query("ALTER TABLE gamification_profiles ADD COLUMN longest_streak INT UNSIGNED NOT NULL DEFAULT 0 AFTER current_streak");
}
if (!in_array('last_activity_date', $existingCols)) {
    $conn->query("ALTER TABLE gamification_profiles ADD COLUMN last_activity_date DATE NULL AFTER longest_streak");
}
echo "gamification_profiles columns verified/upgraded.\n";

// 2. Check and upgrade library_books table
$checkBookCols = $conn->query("SHOW COLUMNS FROM library_books");
$existingBookCols = [];
while ($row = $checkBookCols->fetch_assoc()) {
    $existingBookCols[] = $row['Field'];
}

if (!in_array('cover_image', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN cover_image VARCHAR(255) NULL AFTER description");
}
if (!in_array('pdf_path', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN pdf_path VARCHAR(255) NOT NULL DEFAULT '' AFTER cover_image");
}
if (!in_array('difficulty', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN difficulty ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') NOT NULL DEFAULT 'Beginner' AFTER pdf_path");
}
if (!in_array('level', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN level TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER difficulty");
}
if (!in_array('low_points', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN low_points INT UNSIGNED NOT NULL DEFAULT 20 AFTER level");
}
if (!in_array('medium_points', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN medium_points INT UNSIGNED NOT NULL DEFAULT 50 AFTER low_points");
}
if (!in_array('high_points', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN high_points INT UNSIGNED NOT NULL DEFAULT 100 AFTER medium_points");
}
if (!in_array('estimated_reading_time', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN estimated_reading_time VARCHAR(30) NOT NULL DEFAULT '15 mins' AFTER high_points");
}
if (!in_array('page_count', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN page_count INT UNSIGNED NOT NULL DEFAULT 12 AFTER estimated_reading_time");
}
if (!in_array('published_status', $existingBookCols)) {
    $conn->query("ALTER TABLE library_books ADD COLUMN published_status TINYINT(1) NOT NULL DEFAULT 1 AFTER page_count");
}
echo "library_books schema updated.\n";

// 3. Create book_progress table
$conn->query("CREATE TABLE IF NOT EXISTS book_progress (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    current_page INT UNSIGNED NOT NULL DEFAULT 1,
    total_pages INT UNSIGNED NOT NULL DEFAULT 1,
    progress_percentage TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('not_started', 'reading', 'completed') NOT NULL DEFAULT 'not_started',
    last_read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_book_progress (user_id, book_id),
    INDEX idx_bp_user (user_id),
    INDEX idx_bp_status (status),
    CONSTRAINT fk_bp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "book_progress table created.\n";

// 4. Create quiz_questions table
$conn->query("CREATE TABLE IF NOT EXISTS quiz_questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    book_id INT UNSIGNED NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer CHAR(1) NOT NULL,
    explanation TEXT NULL,
    points INT UNSIGNED NOT NULL DEFAULT 10,
    INDEX idx_qq_book (book_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "quiz_questions table created.\n";

// 5. Create quiz_attempts table
$conn->query("CREATE TABLE IF NOT EXISTS quiz_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    book_id INT UNSIGNED NOT NULL,
    score INT UNSIGNED NOT NULL DEFAULT 0,
    total_questions INT UNSIGNED NOT NULL DEFAULT 0,
    percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    score_level ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'low',
    points_earned INT UNSIGNED NOT NULL DEFAULT 0,
    xp_earned INT UNSIGNED NOT NULL DEFAULT 0,
    time_taken INT UNSIGNED NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_qa_user (user_id),
    INDEX idx_qa_book (book_id),
    CONSTRAINT fk_qa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "quiz_attempts table created.\n";

// 6. Create badges and user_badges tables
$conn->query("CREATE TABLE IF NOT EXISTS badges (
    badge_id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon VARCHAR(50) NOT NULL,
    condition_type VARCHAR(50) NOT NULL,
    condition_value INT NOT NULL DEFAULT 1,
    rarity ENUM('Common', 'Rare', 'Epic', 'Legendary') NOT NULL DEFAULT 'Common'
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

$conn->query("CREATE TABLE IF NOT EXISTS user_badges (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    badge_id VARCHAR(50) NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_badge_link (user_id, badge_id),
    INDEX idx_ub_user (user_id),
    CONSTRAINT fk_ub_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "badges & user_badges tables created.\n";

// Populate Standard Badges
$badges = [
    ['first_book', 'First Reader', 'Completed your first learning guide or book', 'fa-book-open', 'books_completed', 1, 'Common'],
    ['bookworm', 'Bookworm', 'Successfully read and completed 5 books', 'fa-book', 'books_completed', 5, 'Rare'],
    ['scholar', 'Avid Scholar', 'Successfully read and completed 15 books', 'fa-graduation-cap', 'books_completed', 15, 'Epic'],
    ['grand_reader', 'Grand Reader', 'Read and completed 50+ books across categories', 'fa-crown', 'books_completed', 50, 'Legendary'],
    ['quiz_novice', 'Quiz Novice', 'Completed your first knowledge quiz', 'fa-brain', 'quizzes_completed', 1, 'Common'],
    ['perfect_score', 'Perfect Score', 'Scored 100% (High Score) on any book quiz', 'fa-bullseye', 'high_score_count', 1, 'Rare'],
    ['quiz_master', 'Quiz Master', 'Achieved High Score on 10 different book quizzes', 'fa-trophy', 'high_score_count', 10, 'Epic'],
    ['streak_master', 'Streak Champion', 'Maintained a 7-day continuous study streak', 'fa-fire', 'streak_days', 7, 'Rare'],
    ['hematology_pro', 'Hematology Specialist', 'Mastered 5 Hematology & Blood Science books', 'fa-droplet', 'category_hematology', 5, 'Epic'],
    ['tech_wizard', 'Tech & Science Wizard', 'Mastered 5 Programming & Computer Science books', 'fa-laptop-code', 'category_tech', 5, 'Epic'],
    ['lifesaver_elite', 'Life Saver Elite', 'Reached 1,000+ total knowledge points', 'fa-medal', 'total_points', 1000, 'Legendary']
];

$bStmt = $conn->prepare("INSERT INTO badges (badge_id, name, description, icon, condition_type, condition_value, rarity) 
    VALUES (?, ?, ?, ?, ?, ?, ?) 
    ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), icon = VALUES(icon), rarity = VALUES(rarity)");
foreach ($badges as $b) {
    $bStmt->bind_param('sssssis', $b[0], $b[1], $b[2], $b[3], $b[4], $b[5], $b[6]);
    $bStmt->execute();
}
$bStmt->close();
echo "Badges catalog seeded.\n";

echo "Database migration completed successfully.\n";

