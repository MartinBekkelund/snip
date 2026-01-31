-- =====================================================
-- URL Shortener Database Schema for MariaDB
-- =====================================================

-- Opprett databasen
CREATE DATABASE IF NOT EXISTS url_shortener
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE url_shortener;

-- Hovedtabell for URLer
CREATE TABLE IF NOT EXISTS urls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    short_code VARCHAR(100) NOT NULL UNIQUE,
    original_url TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    click_count INT UNSIGNED DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    
    INDEX idx_short_code (short_code),
    INDEX idx_created_at (created_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Tabell for klikk-statistikk
CREATE TABLE IF NOT EXISTS click_stats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url_id INT UNSIGNED NOT NULL,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    referer TEXT NULL,
    country_code VARCHAR(2) NULL,
    
    FOREIGN KEY (url_id) REFERENCES urls(id) ON DELETE CASCADE,
    INDEX idx_url_id (url_id),
    INDEX idx_clicked_at (clicked_at)
) ENGINE=InnoDB;

-- Tabell for API-nøkler (valgfritt, for fremtidig bruk)
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
