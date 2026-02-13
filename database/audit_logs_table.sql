-- Audit Logs Table for SN/P URL Shortener
-- Stores all administrative actions for security and compliance

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'success',
    `resource_id` INT UNSIGNED,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `details` JSON,
    `logged_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for faster queries
    INDEX idx_action_status (`action`, `status`),
    INDEX idx_logged_at (`logged_at`),
    INDEX idx_ip_address (`ip_address`),
    INDEX idx_resource_id (`resource_id`),

    -- Foreign key reference (optional, if strict referential integrity needed)
    -- CONSTRAINT fk_audit_logs_url FOREIGN KEY (`resource_id`) REFERENCES `urls` (`id`) ON DELETE SET NULL

    -- Charset and collation
    CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci
) ENGINE = InnoDB;

-- Create a composite index for common queries
CREATE INDEX idx_action_timestamp ON `audit_logs` (`action`, `logged_at` DESC);

-- Optional: Partition by month for better performance with large audit logs
-- ALTER TABLE `audit_logs` PARTITION BY RANGE (YEAR_MONTH(logged_at)) (
--     PARTITION p_2025_01 VALUES LESS THAN (202502),
--     PARTITION p_2025_02 VALUES LESS THAN (202503),
--     PARTITION p_2025_03 VALUES LESS THAN (202504),
--     PARTITION p_future VALUES LESS THAN MAXVALUE
-- );
