-- ============================================================
-- LifeFlow Hospital Portal — Database Schema
-- Import this file into the blood_connect database
-- Run after setup.sql
-- ============================================================

USE blood_connect;

-- ============================================================
-- 1. HOSPITALS (core hospital record)
-- ============================================================
CREATE TABLE IF NOT EXISTS hospitals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_uid VARCHAR(20) NOT NULL UNIQUE COMMENT 'e.g. HOS-01',
    hospital_name VARCHAR(200) NOT NULL,
    hospital_type ENUM('government','private','trust','medical_college') NOT NULL DEFAULT 'private',
    license_number VARCHAR(100) NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NULL,
    pin_code VARCHAR(10) NULL,
    contact_phone VARCHAR(40) NOT NULL,
    official_email VARCHAR(200) NOT NULL UNIQUE,
    emergency_phone VARCHAR(40) NULL,
    is_24_7 TINYINT(1) NOT NULL DEFAULT 0,
    has_blood_bank TINYINT(1) NOT NULL DEFAULT 0,
    has_blood_storage TINYINT(1) NOT NULL DEFAULT 0,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive','pending') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (city, status),
    INDEX (hospital_type)
) ENGINE=InnoDB;

-- ============================================================
-- 2. BLOOD INVENTORY (per hospital, per blood group)
-- ============================================================
CREATE TABLE IF NOT EXISTS hospital_blood_inventory (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT UNSIGNED NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
    available_units SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    minimum_units SMALLINT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Critical threshold / minimum emergency stock',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hospital_blood (hospital_id, blood_group),
    CONSTRAINT fk_hbi_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. BLOOD COMPONENT INVENTORY
-- ============================================================
CREATE TABLE IF NOT EXISTS hospital_blood_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT UNSIGNED NOT NULL,
    component ENUM('packed_rbc','platelets','ffp','cryoprecipitate') NOT NULL,
    available_units SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hospital_component (hospital_id, component),
    CONSTRAINT fk_hbc_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 4. BLOOD BATCH EXPIRY TRACKER
-- ============================================================
CREATE TABLE IF NOT EXISTS hospital_blood_batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT UNSIGNED NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
    component ENUM('whole_blood','packed_rbc','platelets','ffp','cryoprecipitate') NOT NULL DEFAULT 'whole_blood',
    units SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    collection_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    storage_location VARCHAR(100) NULL,
    batch_uid VARCHAR(100) NOT NULL,
    notes TEXT NULL,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (hospital_id, expiry_date),
    INDEX (blood_group, expiry_date),
    CONSTRAINT fk_hbb_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. HOSPITAL EMERGENCY REQUESTS
-- ============================================================
CREATE TABLE IF NOT EXISTS hospital_emergency_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT UNSIGNED NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','O+','O-','AB+','AB-') NOT NULL,
    required_units SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    required_by DATETIME NULL COMMENT 'Deadline for receiving blood',
    priority ENUM('normal','urgent','critical') NOT NULL DEFAULT 'urgent',
    reason VARCHAR(200) NULL,
    category ENUM('surgery','accident','maternity','cancer','thalassemia','other') NOT NULL DEFAULT 'other',
    status ENUM('open','fulfilled','cancelled') NOT NULL DEFAULT 'open',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (hospital_id, status),
    INDEX (blood_group, status),
    CONSTRAINT fk_her_hospital FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE
) ENGINE=InnoDB;
