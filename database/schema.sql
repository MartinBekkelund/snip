-- =====================================================
-- Snip - URL Shortener
-- Database Schema for MariaDB/MySQL
-- 
-- @package    Snip
-- @version    1.0.0
-- @author     Martin Bekkelund
-- @copyright  2025 Martin Bekkelund
-- @license    GPL-3.0-or-later
-- @link       https://github.com/MartinBekkelund/snip
-- 
-- This file is part of Snip.
-- 
-- Snip is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
-- 
-- Snip is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
-- GNU General Public License for more details.
-- 
-- You should have received a copy of the GNU General Public License
-- along with Snip. If not, see <https://www.gnu.org/licenses/>.
-- =====================================================

-- Create database
CREATE DATABASE IF NOT EXISTS url_shortener
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE url_shortener;

-- Main table for URLs
CREATE TABLE IF NOT EXISTS urls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_code VARCHAR(100) NOT NULL UNIQUE,
    original_url TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    click_count INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    ip_address VARCHAR(45) NULL COMMENT 'Anonymized IP address',
    user_agent VARCHAR(255) NULL,
    
    INDEX idx_short_code (short_code),
    INDEX idx_created_at (created_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Table for click statistics
-- Note: IP addresses are stored anonymized (last octet zeroed for IPv4)
CREATE TABLE IF NOT EXISTS click_stats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_id INT UNSIGNED NOT NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL COMMENT 'Anonymized IP address',
    user_agent VARCHAR(255) NULL,
    referer VARCHAR(500) NULL,
    country_code VARCHAR(2) NULL,
    
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE,
    INDEX idx_url_id (url_id),
    INDEX idx_clicked_at (clicked_at)
) ENGINE=InnoDB;

-- Table for rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(255) NOT NULL COMMENT 'IP address or action identifier',
    request_count INT UNSIGNED DEFAULT 1,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_rate_key (rate_key),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Table for authentication tokens
CREATE TABLE IF NOT EXISTS auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL COMMENT 'SHA256 hash of the actual token',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE INDEX idx_token (token),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB;

-- Table for API keys (optional, for future use)
CREATE TABLE IF NOT EXISTS api_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    rate_limit INT UNSIGNED DEFAULT 100,
    
    INDEX idx_api_key (api_key)
) ENGINE=InnoDB;

-- Clean up expired rate limits (run periodically via cron or similar)
-- DELETE FROM rate_limits WHERE expires_at < NOW();

-- Clean up expired auth tokens (run periodically via cron or similar)
-- DELETE FROM auth_tokens WHERE expires_at < NOW();
