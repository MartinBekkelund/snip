<?php
/**
 * URL Shortener - Konfigurasjon
 * 
 * Rediger innstillingene nedenfor for å matche ditt miljø.
 */

// Database-innstillinger
define('DB_HOST', 'localhost');
define('DB_NAME', 'url_shortener');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Applikasjonsinnstillinger
define('BASE_URL', 'http://localhost/url-shortener/'); // Endre til din URL
define('SHORT_CODE_LENGTH', 6);
define('ALLOWED_PROTOCOLS', ['http://', 'https://']);

// Sikkerhet
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX_REQUESTS', 10);
define('RATE_LIMIT_WINDOW_SECONDS', 60);

// Reserverte kortkoder (kan ikke brukes)
define('RESERVED_CODES', ['admin', 'api', 'stats', 'login', 'logout']);

// CORS-innstillinger
define('CORS_ORIGIN', '*'); // Endre til spesifikk domene i produksjon

/**
 * Database-tilkobling med PDO
 */
function getDbConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * JSON-respons helper
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Feilhåndtering
 */
function errorResponse(string $message, int $statusCode = 400): void {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}
