<?php
/**
 * Redirect Handler
 * 
 * Håndterer omdirigering fra kortkode til original URL
 * Bruk med .htaccess for clean URLs
 */

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/UrlShortener.php';

// Hent kortkode fra query string eller path
$shortCode = $_GET['code'] ?? '';

// Hvis path-basert routing brukes
if (empty($shortCode)) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '/';
    $path = str_replace($basePath, '', $requestUri);
    $shortCode = explode('?', $path)[0];
    $shortCode = trim($shortCode, '/');
}

// Hvis ingen kode, vis hovedsiden
if (empty($shortCode)) {
    header('Location: ' . BASE_URL);
    exit;
}

// Ignorer statiske filer og kjente stier
$ignoredPaths = ['api', 'assets', 'index.html', 'favicon.ico'];
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
        // 301 Permanent redirect
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $originalUrl);
        header('Cache-Control: no-cache');
        exit;
    } else {
        // URL ikke funnet
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="no">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Lenke ikke funnet</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                }
                .container {
                    text-align: center;
                    padding: 2rem;
                }
                h1 {
                    font-size: 6rem;
                    font-weight: 200;
                    opacity: 0.3;
                    margin-bottom: 1rem;
                }
                p {
                    font-size: 1.25rem;
                    opacity: 0.8;
                    margin-bottom: 2rem;
                }
                a {
                    display: inline-block;
                    padding: 0.75rem 2rem;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #fff;
                    text-decoration: none;
                    border-radius: 50px;
                    font-weight: 500;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                a:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>404</h1>
                <p>Beklager, denne lenken eksisterer ikke eller har utløpt.</p>
                <a href="<?= htmlspecialchars(BASE_URL) ?>">Gå til forsiden</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
} catch (Exception $e) {
    error_log('Redirect Error: ' . $e->getMessage());
    http_response_code(500);
    echo 'En feil oppstod. Vennligst prøv igjen.';
    exit;
}
