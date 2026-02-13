<?php
/**
 * Snip - URL Shortener
 * Main URL shortener class
 * 
 * Handles all URL shortening, resolution, and statistics logic.
 * 
 * @package    Snip
 * @version    1.0.0
 * @author     Martin Bekkelund
 * @copyright  2025 Martin Bekkelund
 * @license    GPL-3.0-or-later
 * @link       https://github.com/MartinBekkelund/snip
 * 
 * This file is part of Snip.
 * 
 * Snip is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * Snip is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Snip. If not, see <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/config.php';

class UrlShortener {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Shorten a URL
     * 
     * @param string $url The URL to shorten
     * @param string|null $customCode Optional custom short code
     * @return array Result with short code and URLs
     * @throws InvalidArgumentException If URL is invalid
     */
    public function shorten(string $url, ?string $customCode = null): array {
        // Validate URL
        $url = $this->validateUrl($url);
        
        // Check if URL already exists
        $existing = $this->findByOriginalUrl($url);
        if ($existing) {
            return [
                'success' => true,
                'short_code' => $existing['short_code'],
                'short_url' => BASE_URL . $existing['short_code'],
                'original_url' => $existing['original_url'],
                'is_existing' => true
            ];
        }
        
        // Generate or validate short code
        if ($customCode) {
            $shortCode = $this->validateCustomCode($customCode);
        } else {
            $shortCode = $this->generateUniqueCode();
        }
        
        // Save to database (IP is anonymized for GDPR compliance)
        $stmt = $this->db->prepare('
            INSERT INTO urls (short_code, original_url, ip_address, user_agent)
            VALUES (:short_code, :original_url, :ip_address, :user_agent)
        ');
        
        $stmt->execute([
            ':short_code' => $shortCode,
            ':original_url' => $url,
            ':ip_address' => getClientIp(),
            ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
        
        return [
            'success' => true,
            'short_code' => $shortCode,
            'short_url' => BASE_URL . $shortCode,
            'original_url' => $url,
            'is_existing' => false
        ];
    }
    
    /**
     * Resolve a short code to its original URL
     * 
     * @param string $shortCode The short code to resolve
     * @return array|null URL data or null if not found
     */
    public function resolve(string $shortCode): ?array {
        $shortCode = $this->sanitizeCode($shortCode);
        
        $stmt = $this->db->prepare('
            SELECT id, short_code, original_url, click_count, created_at
            FROM urls
            WHERE short_code = :short_code 
            AND is_active = TRUE
            AND (expires_at IS NULL OR expires_at > NOW())
        ');
        
        $stmt->execute([':short_code' => $shortCode]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Register a click and return the redirect URL
     * 
     * @param string $shortCode The short code
     * @return string|null The original URL or null if not found
     */
    public function redirect(string $shortCode): ?string {
        $url = $this->resolve($shortCode);
        
        if (!$url) {
            return null;
        }
        
        // Update click counter
        $this->db->prepare('
            UPDATE urls SET click_count = click_count + 1 WHERE id = :id
        ')->execute([':id' => $url['id']]);
        
        // Log click statistics
        $this->logClick($url['id']);
        
        return $url['original_url'];
    }
    
    /**
     * Get public statistics for a short code
     * Only returns aggregated data, no personal information
     * 
     * @param string $shortCode The short code
     * @return array|null Statistics or null if not found
     */
    public function getPublicStats(string $shortCode): ?array {
        $url = $this->resolve($shortCode);
        
        if (!$url) {
            return null;
        }
        
        return [
            'short_code' => $url['short_code'],
            'short_url' => BASE_URL . $url['short_code'],
            'total_clicks' => (int)$url['click_count'],
            'created_at' => $url['created_at']
        ];
    }
    
    /**
     * Get detailed statistics for a short code (admin only)
     * Includes daily clicks and referrer data
     * 
     * @param string $shortCode The short code
     * @return array|null Detailed statistics or null if not found
     */
    public function getDetailedStats(string $shortCode): ?array {
        $url = $this->resolve($shortCode);
        
        if (!$url) {
            return null;
        }
        
        // Get clicks per day (last 30 days)
        $stmt = $this->db->prepare('
            SELECT DATE(clicked_at) as date, COUNT(*) as clicks
            FROM click_stats
            WHERE url_id = :url_id
            AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(clicked_at)
            ORDER BY date ASC
        ');
        $stmt->execute([':url_id' => $url['id']]);
        $dailyClicks = $stmt->fetchAll();
        
        // Get referrer statistics (aggregated, no personal data)
        $stmt = $this->db->prepare('
            SELECT 
                CASE 
                    WHEN referer IS NULL OR referer = "" THEN "Direct"
                    ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, "/", 3), "://", -1)
                END as source,
                COUNT(*) as count
            FROM click_stats
            WHERE url_id = :url_id
            GROUP BY source
            ORDER BY count DESC
            LIMIT 10
        ');
        $stmt->execute([':url_id' => $url['id']]);
        $referrerStats = $stmt->fetchAll();
        
        return [
            'short_code' => $url['short_code'],
            'short_url' => BASE_URL . $url['short_code'],
            'original_url' => $url['original_url'],
            'total_clicks' => (int)$url['click_count'],
            'created_at' => $url['created_at'],
            'daily_clicks' => $dailyClicks,
            'referrer_stats' => $referrerStats
        ];
    }
    
    /**
     * Log a click in the statistics table
     * IP addresses are anonymized for GDPR compliance
     * 
     * @param int $urlId The URL ID
     */
    private function logClick(int $urlId): void {
        $stmt = $this->db->prepare('
            INSERT INTO click_stats (url_id, ip_address, user_agent, referer)
            VALUES (:url_id, :ip_address, :user_agent, :referer)
        ');
        
        $stmt->execute([
            ':url_id' => $urlId,
            ':ip_address' => getClientIp(), // Anonymized IP
            ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ':referer' => substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500)
        ]);
    }
    
    /**
     * Validate and normalize a URL
     * 
     * @param string $url The URL to validate
     * @return string The validated URL
     * @throws InvalidArgumentException If URL is invalid
     */
    private function validateUrl(string $url): string {
        $url = trim($url);
        
        // Check if URL is empty
        if (empty($url)) {
            throw new InvalidArgumentException('URL cannot be empty');
        }
        
        // Check max length
        if (strlen($url) > 2048) {
            throw new InvalidArgumentException('URL is too long (max 2048 characters)');
        }
        
        // Add https:// if no protocol
        $hasProtocol = false;
        foreach (ALLOWED_PROTOCOLS as $protocol) {
            if (stripos($url, $protocol) === 0) {
                $hasProtocol = true;
                break;
            }
        }
        
        if (!$hasProtocol) {
            $url = 'https://' . $url;
        }
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid URL format');
        }
        
        // Check that URL doesn't point to our own service (prevent loops)
        if (stripos($url, BASE_URL) === 0) {
            throw new InvalidArgumentException('Cannot shorten links to this service');
        }
        
        return $url;
    }
    
    /**
     * Validate a custom short code
     * 
     * @param string $code The code to validate
     * @return string The validated code
     * @throws InvalidArgumentException If code is invalid
     */
    private function validateCustomCode(string $code): string {
        $code = $this->sanitizeCode($code);
        
        // Check length (allow 1-100 characters)
        if (strlen($code) < 1 || strlen($code) > 100) {
            throw new InvalidArgumentException('Custom code must be between 1 and 100 characters');
        }
        
        // Check against reserved codes
        if (in_array(strtolower($code), array_map('strtolower', RESERVED_CODES))) {
            throw new InvalidArgumentException('This code is reserved');
        }
        
        // Check if code is already in use
        if ($this->codeExists($code)) {
            throw new InvalidArgumentException('This code is already in use');
        }
        
        return $code;
    }
    
    /**
     * Sanitize a short code
     * 
     * @param string $code The code to sanitize
     * @return string The sanitized code
     */
    private function sanitizeCode(string $code): string {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
    }
    
    /**
     * Generate a unique short code
     * 
     * @return string A unique short code
     * @throws RuntimeException If unable to generate unique code
     */
    private function generateUniqueCode(): string {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 10;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < SHORT_CODE_LENGTH; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            if (!$this->codeExists($code) && !in_array(strtolower($code), array_map('strtolower', RESERVED_CODES))) {
                return $code;
            }
        }
        
        throw new RuntimeException('Unable to generate unique code');
    }
    
    /**
     * Check if a short code exists
     * 
     * @param string $code The code to check
     * @return bool True if exists
     */
    private function codeExists(string $code): bool {
        $stmt = $this->db->prepare('SELECT 1 FROM urls WHERE short_code = :code');
        $stmt->execute([':code' => $code]);
        return (bool) $stmt->fetch();
    }
    
    /**
     * Find URL by original URL
     * 
     * @param string $url The original URL
     * @return array|null URL data or null if not found
     */
    private function findByOriginalUrl(string $url): ?array {
        $stmt = $this->db->prepare('
            SELECT short_code, original_url
            FROM urls
            WHERE original_url = :url AND is_active = TRUE
            LIMIT 1
        ');
        $stmt->execute([':url' => $url]);
        return $stmt->fetch() ?: null;
    }
}
