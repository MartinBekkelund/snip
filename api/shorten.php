<?php
/**
 * API Endpoint: Forkorte URL
 * 
 * POST /api/shorten.php
 * Body: { "url": "https://example.com", "custom_code": "optional" }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/UrlShortener.php';

// Handle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Kun POST-forespørsler
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Kun POST-forespørsler er tillatt', 405);
}

// Les og dekod JSON-body
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    errorResponse('Ugyldig JSON-format', 400);
}

// Hent URL fra input
$url = $input['url'] ?? '';
$customCode = $input['custom_code'] ?? null;

if (empty($url)) {
    errorResponse('URL er påkrevd', 400);
}

try {
    $shortener = new UrlShortener();
    $result = $shortener->shorten($url, $customCode);
    jsonResponse($result);
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 400);
} catch (Exception $e) {
    error_log('URL Shortener Error: ' . $e->getMessage());
    errorResponse('En feil oppstod. Vennligst prøv igjen.', 500);
}
