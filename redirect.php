<?php
/**
 * Snip - URL Shortener
 * Redirect handler
 * 
 * Handles redirects from short codes to original URLs.
 * Use with .htaccess for clean URLs.
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

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/UrlShortener.php';

// Get short code from query string or path
$shortCode = $_GET['code'] ?? '';

// If path-based routing is used
if (empty($shortCode)) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '/';
    $path = str_replace($basePath, '', $requestUri);
    $shortCode = explode('?', $path)[0];
    $shortCode = trim($shortCode, '/');
}

// If no code, show main page
if (empty($shortCode)) {
    header('Location: ' . BASE_URL);
    exit;
}

// Ignore static files and known paths
$ignoredPaths = ['api', 'assets', 'index.html', 'admin.html', 'favicon.ico', 'favicon.svg', 'logo.svg'];
foreach ($ignoredPaths as $ignored) {
    if (strpos($shortCode, $ignored) === 0) {
        http_response_code(404);
        exit('Not found');
    }
}

try {
    $shortener = new UrlShortener();
    $originalUrl = $shortener->redirect($shortCode);
    
    if ($originalUrl) {
        // 302 Temporary redirect (allows stats tracking and link changes)
        // Use 301 if you want permanent redirects with browser caching
        header('HTTP/1.1 302 Found');
        header('Location: ' . $originalUrl);
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        exit;
    } else {
        // URL not found
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Link not found - SN/P</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: #ffffff;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #31394E;
                }
                .container {
                    text-align: center;
                    padding: 2rem;
                }
                .logo {
                    font-family: 'Gravesend Sans', 'Arial Black', 'Helvetica Neue', sans-serif;
                    font-weight: 700;
                    font-size: 2rem;
                    color: #31394E;
                    margin-bottom: 2rem;
                }
                h1 {
                    font-size: 6rem;
                    font-weight: 200;
                    opacity: 0.2;
                    margin-bottom: 1rem;
                }
                p {
                    font-size: 1.25rem;
                    opacity: 0.7;
                    margin-bottom: 2rem;
                }
                a {
                    display: inline-block;
                    padding: 0.75rem 2rem;
                    background: #31394E;
                    color: #fff;
                    text-decoration: none;
                    border-radius: 8px;
                    font-weight: 500;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                a:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 30px rgba(49, 57, 78, 0.3);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="logo">SN/P</div>
                <h1>404</h1>
                <p>Sorry, this link does not exist or has expired.</p>
                <a href="<?= htmlspecialchars(BASE_URL) ?>">Go to homepage</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    error_log('Snip Redirect Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'An error occurred. Please try again.';
    exit;
}
