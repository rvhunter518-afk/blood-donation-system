-- ============================================================
-- BloodConnect Advanced Calling System — Database Migration
-- Run this in phpMyAdmin or via MySQL CLI:
--   mysql -u root blood_connect < database/call_logs.sql
-- ============================================================

USE blood_connect;

-- ----------------------------------------------------------
-- TABLE: call_logs
-- Stores every call session record
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS call_logs (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id          VARCHAR(40) NOT NULL UNIQUE COMMENT 'e.g. CALL-20260915-ABC123',
    caller_user_id   INT UNSIGNED NOT NULL,
    receiver_user_id INT UNSIGNED NOT NULL,
    caller_role      ENUM('user','donor') NOT NULL DEFAULT 'user',
    receiver_role    ENUM('user','donor') NOT NULL DEFAULT 'donor',
    context_type     VARCHAR(40) NOT NULL DEFAULT 'donor_search'
                     COMMENT 'blood_request|donor_search|hospital_request|emergency_request|support',
    context_id       INT UNSIGNED NULL COMMENT 'Related blood_request.id, etc.',
    provider         VARCHAR(30) NOT NULL DEFAULT 'test'
                     COMMENT 'twilio|exotel|plivo|test',
    provider_call_id VARCHAR(120) NULL COMMENT 'SID/UUID returned by provider',
    status           ENUM(
                         'initiated','ringing','answered',
                         'busy','rejected','no_answer',
                         'failed','completed','cancelled'
                     ) NOT NULL DEFAULT 'initiated',
    started_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    answered_at      DATETIME NULL,
    ended_at         DATETIME NULL,
    duration_seconds SMALLINT UNSIGNED NULL,
    end_reason       VARCHAR(80) NULL,
    notes            TEXT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_caller   (caller_user_id, created_at),
    INDEX idx_receiver (receiver_user_id, created_at),
    INDEX idx_status   (status),
    INDEX idx_context  (context_type, context_id),

    CONSTRAINT fk_call_caller   FOREIGN KEY (caller_user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_call_receiver FOREIGN KEY (receiver_user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


-- ----------------------------------------------------------
-- TABLE: call_rate_limits
-- Per-user rate limiting window tracker
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS call_rate_limits (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    window_start DATETIME NOT NULL,
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_window (user_id, window_start),
    INDEX idx_rate_user (user_id),

    CONSTRAINT fk_rate_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

