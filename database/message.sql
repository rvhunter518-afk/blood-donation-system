-- ============================================================
-- LifeFlow / BloodConnect Real-Time Messaging System
-- Database Schema for Messages and User Presence
-- ============================================================

USE blood_connect;

-- ------------------------------------------------------------
-- 1. TABLE: blood_messages
-- Stores all chat messages between users, donors, and hospitals
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blood_messages` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sender_id`     INT UNSIGNED NOT NULL COMMENT 'ID of sender from users or hospitals table',
    `sender_type`   ENUM('user', 'hospital') NOT NULL DEFAULT 'user',
    `receiver_id`   INT UNSIGNED NOT NULL COMMENT 'ID of receiver from users or hospitals table',
    `receiver_type` ENUM('user', 'hospital') NOT NULL DEFAULT 'user',
    `message`       TEXT NOT NULL,
    `is_read`       TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = unread, 1 = read',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_conv` (`sender_id`, `sender_type`, `receiver_id`, `receiver_type`),
    INDEX `idx_recv` (`receiver_id`, `receiver_type`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. TABLE: user_presence
-- Tracks online/offline status and heartbeat of active participants
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_presence` (
    `user_id`    INT UNSIGNED PRIMARY KEY,
    `status`     ENUM('online', 'away', 'offline') NOT NULL DEFAULT 'online',
    `last_seen`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_status` (`status`),
    INDEX `idx_last_seen` (`last_seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
