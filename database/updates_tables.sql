-- Update History and Migrations Tables
-- Created for automated update feature v1.1.0

-- Update History Table
CREATE TABLE IF NOT EXISTS `update_history` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `from_version` VARCHAR(20),
    `to_version` VARCHAR(20) NOT NULL,
    `status` ENUM('pending', 'in_progress', 'success', 'failed', 'rolled_back') DEFAULT 'pending',
    `backup_id` VARCHAR(50),
    `duration_seconds` INT,
    `error_message` LONGTEXT,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    `admin_initiated` BOOLEAN DEFAULT FALSE,

    INDEX idx_status (`status`),
    INDEX idx_completed_at (`completed_at`),
    INDEX idx_backup_id (`backup_id`),
    INDEX idx_attempted_at (`attempted_at`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Backup Manifests Table
CREATE TABLE IF NOT EXISTS `backup_manifests` (
    `id` VARCHAR(50) PRIMARY KEY,
    `backup_path` VARCHAR(255) NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `file_count` INT DEFAULT 0,
    `database_size` BIGINT DEFAULT 0,
    `total_size` BIGINT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    `checksum` VARCHAR(64),
    `status` ENUM('valid', 'corrupted', 'deleted') DEFAULT 'valid',
    `tested` BOOLEAN DEFAULT FALSE,

    INDEX idx_created_at (`created_at`),
    INDEX idx_expires_at (`expires_at`),
    INDEX idx_version (`version`),
    INDEX idx_status (`status`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Application Migrations Table (tracks applied migrations)
CREATE TABLE IF NOT EXISTS `app_migrations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL UNIQUE,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_filename (`filename`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Application Settings Table (for storing version and other settings)
CREATE TABLE IF NOT EXISTS `app_settings` (
    `key` VARCHAR(255) PRIMARY KEY,
    `value` LONGTEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Insert initial version setting if not exists
INSERT INTO `app_settings` (`key`, `value`) VALUES ('version', '1.0.0') ON DUPLICATE KEY UPDATE `key`='version';
