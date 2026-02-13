<?php
/**
 * Snip - URL Shortener
 * Public statistics API endpoint
 * 
 * GET /api/stats.php?code=abc123
 * 
 * Returns only aggregated statistics (total clicks, creation date).
 * For detailed statistics including referrers and daily breakdown,
 * use the admin API endpoint which requires authentication.
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
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Only GET requests are allowed', 405);
}

$shortCode = $_GET['code'] ?? '';

if (empty($shortCode)) {
    errorResponse('Short code is required', 400);
}

try {
    $shortener = new UrlShortener();
    
    // Return only public statistics (no personal data)
    $stats = $shortener->getPublicStats($shortCode);
    
    if ($stats) {
        jsonResponse([
            'success' => true,
            'data' => $stats
        ]);
    } else {
        errorResponse('Short code not found', 404);
    }
} catch (Exception $e) {
    error_log('Snip Stats Error: ' . $e->getMessage());
    errorResponse('An error occurred. Please try again.', 500);
}
