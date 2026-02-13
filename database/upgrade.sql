-- =====================================================
-- Snip - URL Shortener
-- Database Upgrade Script to v1.0.0
-- 
-- @package    Snip
-- @version    1.0.0
-- @author     Martin Bekkelund
-- @copyright  2025 Martin Bekkelund
-- @license    GPL-3.0-or-later
-- @link       https://github.com/MartinBekkelund/snip
-- 
-- USAGE:
--   mysql -u username -p database_name < upgrade.sql
-- 
-- Or run these commands manually in your MySQL/MariaDB client.
-- =====================================================

-- Create rate_limits table for rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(255) NOT NULL COMMENT 'IP address or action identifier',
    request_count INT UNSIGNED DEFAULT 1,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_rate_key (rate_key),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Create auth_tokens table for secure authentication
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL COMMENT 'SHA256 hash of the actual token',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Update column sizes for better compatibility and GDPR compliance
-- These may fail if columns are already the correct size, which is fine
ALTER TABLE urls MODIFY user_agent VARCHAR(255) NULL;
ALTER TABLE click_stats MODIFY user_agent VARCHAR(255) NULL;
ALTER TABLE click_stats MODIFY referer VARCHAR(500) NULL;

-- =====================================================
-- OPTIONAL: Anonymize existing IP addresses for GDPR
-- Uncomment these lines if you want to anonymize existing data
-- =====================================================

-- Anonymize IPv4 addresses in urls table
-- UPDATE urls 
-- SET ip_address = CONCAT(SUBSTRING_INDEX(ip_address, '.', 3), '.0')
-- WHERE ip_address REGEXP '^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$'
-- AND ip_address NOT LIKE '%.0';

-- Anonymize IPv4 addresses in click_stats table
-- UPDATE click_stats 
-- SET ip_address = CONCAT(SUBSTRING_INDEX(ip_address, '.', 3), '.0')
-- WHERE ip_address REGEXP '^[0-9]+\\.[0-9]+\\.[0-9]+\\.[0-9]+$'
-- AND ip_address NOT LIKE '%.0';

-- =====================================================
-- Maintenance queries (run periodically via cron)
-- =====================================================

-- Clean up expired rate limits
-- DELETE FROM rate_limits WHERE expires_at < NOW();

-- Clean up expired auth tokens
-- DELETE FROM auth_tokens WHERE expires_at < NOW();

-- =====================================================
-- Verification queries
-- =====================================================

-- Check that tables were created
-- SHOW TABLES LIKE 'rate_limits';
-- SHOW TABLES LIKE 'auth_tokens';

-- Check table structure
-- DESCRIBE rate_limits;
-- DESCRIBE auth_tokens;
