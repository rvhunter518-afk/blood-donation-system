CREATE DATABASE IF NOT EXISTS blood_connect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE blood_connect;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    blood_type VARCHAR(5) NULL,
    phone VARCHAR(40) NULL,
    city VARCHAR(100) NULL,
    role ENUM('user', 'donor') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('user', 'donor') NOT NULL DEFAULT 'donor';
CREATE TABLE IF NOT EXISTS blood_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_id INT UNSIGNED NOT NULL,
    patient_name VARCHAR(120) NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    units TINYINT UNSIGNED NOT NULL DEFAULT 1,
    hospital VARCHAR(180) NOT NULL,
    city VARCHAR(100) NOT NULL,
    urgency ENUM('routine', 'urgent', 'critical') NOT NULL DEFAULT 'urgent',
    contact_phone VARCHAR(40) NOT NULL,
    notes TEXT NULL,
    donor_id INT UNSIGNED NULL,
    status ENUM('open', 'matched', 'fulfilled', 'cancelled') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (blood_type, city, status),
    INDEX (requester_id),
    INDEX (donor_id)
);

CREATE TABLE IF NOT EXISTS donors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    blood_type VARCHAR(5) NOT NULL,
    city VARCHAR(100) NOT NULL,
    phone VARCHAR(40) NULL,
    availability ENUM('available', 'unavailable') NOT NULL DEFAULT 'available',
    last_donation_date DATE NULL,
    total_donations SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (blood_type, city, availability),
    CONSTRAINT fk_donors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO donors (user_id, blood_type, city, phone)
SELECT id, blood_type, city, phone FROM users
WHERE role = 'donor' AND blood_type IN ('O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-');

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    donor_id INT UNSIGNED NULL,
    request_id INT UNSIGNED NULL,
    message VARCHAR(500) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS donations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    donor_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED NULL,
    donation_date DATE NOT NULL,
    units TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (donor_id, donation_date),
    CONSTRAINT fk_donations_donor FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
    CONSTRAINT fk_donations_request FOREIGN KEY (request_id) REFERENCES blood_requests(id) ON DELETE SET NULL
) ENGINE=InnoDB;
