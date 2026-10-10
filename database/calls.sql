-- ============================================================
-- BloodConnect WebRTC In-Website Real-Time Calling System
-- Database Schema for Calls, Signals, and User Presence
-- ============================================================

USE blood_connect;

-- ----------------------------------------------------------
-- 1. TABLE: call_logs
-- Stores all audio and video WebRTC call sessions & history
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS call_logs (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id           VARCHAR(50) NOT NULL UNIQUE COMMENT 'CALL-YYYYMMDD-XXXXXXXX',
    caller_user_id    INT UNSIGNED NOT NULL,
    receiver_user_id  INT UNSIGNED NOT NULL,
    call_type         ENUM('audio', 'video') NOT NULL DEFAULT 'audio',
    status            ENUM(
                          'calling', 'ringing', 'accepted', 'connected',
                          'rejected', 'busy', 'missed', 'ended', 'failed'
                      ) NOT NULL DEFAULT 'calling',
    started_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    answered_at       DATETIME NULL,
    ended_at          DATETIME NULL,
    duration_seconds  INT UNSIGNED NOT NULL DEFAULT 0,
    end_reason        VARCHAR(100) NULL,
    context_type      VARCHAR(50) NOT NULL DEFAULT 'blood_request' 
                      COMMENT 'blood_request|donor_search|emergency_request|general',
    context_id        INT UNSIGNED NULL,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_caller   (caller_user_id, created_at),
    INDEX idx_receiver (receiver_user_id, created_at),
    INDEX idx_status   (status),
    INDEX idx_call_id  (call_id),

    CONSTRAINT fk_webrtc_caller FOREIGN KEY (caller_user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_webrtc_receiver FOREIGN KEY (receiver_user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. TABLE: call_signals
-- Lightweight queue for WebRTC SDP Offer, Answer, and ICE Candidates
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS call_signals (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    call_id           VARCHAR(50) NOT NULL,
    sender_user_id    INT UNSIGNED NOT NULL,
    receiver_user_id  INT UNSIGNED NOT NULL,
    type              ENUM('offer', 'answer', 'candidate', 'ring', 'busy', 'reject', 'end') NOT NULL,
    payload           MEDIUMTEXT NOT NULL COMMENT 'JSON payload containing SDP or ICE candidate',
    is_read           TINYINT(1) NOT NULL DEFAULT 0,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_sig_call     (call_id),
    INDEX idx_sig_receiver (receiver_user_id, is_read),
    INDEX idx_sig_created  (created_at),

    CONSTRAINT fk_sig_sender FOREIGN KEY (sender_user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sig_receiver FOREIGN KEY (receiver_user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. TABLE: user_presence
-- Tracks online/offline/in_call status for real-time indicators
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_presence (
    user_id           INT UNSIGNED PRIMARY KEY,
    status            ENUM('online', 'away', 'offline', 'in_call') NOT NULL DEFAULT 'offline',
    current_call_id   VARCHAR(50) NULL,
    last_seen         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_presence_status (status),
    INDEX idx_presence_seen   (last_seen),

    CONSTRAINT fk_presence_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

