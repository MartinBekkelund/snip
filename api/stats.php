<?php
/**
 * API Endpoint: Hent statistikk
 * 
 * GET /api/stats.php?code=abc123
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/UrlShortener.php';

// Handle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Kun GET-forespørsler
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Kun GET-forespørsler er tillatt', 405);
}

$shortCode = $_GET['code'] ?? '';

if (empty($shortCode)) {
    errorResponse('Kortkode er påkrevd', 400);
}

try {
    $shortener = new UrlShortener();
    $stats = $shortener->getStats($shortCode);
    
    if ($stats) {
        jsonResponse([
            'success' => true,
            'data' => $stats
        ]);
    } else {
        errorResponse('Kortkode ikke funnet', 404);
    }
} catch (Exception $e) {
    error_log('Stats Error: ' . $e->getMessage());
    errorResponse('En feil oppstod. Vennligst prøv igjen.', 500);
}
