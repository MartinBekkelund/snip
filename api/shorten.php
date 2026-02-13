<?php
/**
 * Snip - URL Shortener
 * Shorten URL API endpoint
 * 
 * POST /api/shorten.php
 * Body: { "url": "https://example.com", "custom_code": "optional" }
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
require_once __DIR__ . '/UrlShortener.php';

// Send security headers
sendSecurityHeaders();

// Handle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $origin = CORS_ORIGIN;
    if ($origin === '*') {
        header('Access-Control-Allow-Origin: *');
    } else {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Only POST requests are allowed', 405);
}

// Check rate limit
$clientIp = getClientIp();
if (!checkRateLimit('shorten:' . $clientIp)) {
    errorResponse('Too many requests. Please try again later.', 429);
}

// Read and decode JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    errorResponse('Invalid JSON format', 400);
}

// Get URL from input
$url = $input['url'] ?? '';
$customCode = $input['custom_code'] ?? null;

if (empty($url)) {
    errorResponse('URL is required', 400);
}

try {
    $shortener = new UrlShortener();
    $result = $shortener->shorten($url, $customCode);
    jsonResponse($result);
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 400);
} catch (Exception $e) {
    error_log('Snip URL Shortener Error: ' . $e->getMessage());
    errorResponse('An error occurred. Please try again.', 500);
}
