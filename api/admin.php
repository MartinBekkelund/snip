<?php
/**
 * API Endpoint: Admin-funksjoner
 *
 * GET    /api/admin.php              - List alle URLer
 * GET    /api/admin.php?id=123       - Hent én URL med statistikk
 * PUT    /api/admin.php              - Oppdater URL
 * DELETE /api/admin.php?id=123       - Slett URL
 * POST   /api/admin.php/auth         - Autentisering
 */

require_once __DIR__ . '/config.php';

// Admin-passord (endre dette!)
define('ADMIN_PASSWORD_HASH', '');

// Session for autentisering
session_start();

// Handle preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
    header('Access-Control-Allow-Credentials: true');
    exit;
}

// Sett CORS headers
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Credentials: true');

/**
 * Sjekk autentisering
 */
function isAuthenticated(): bool {
    // Sjekk session
    if (isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true) {
        return true;
    }

    // Sjekk header token
    $token = $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? '';
    if ($token === hash('sha256', ADMIN_PASSWORD . date('Y-m-d'))) {
        return true;
    }

    return false;
}

/**
 * Krev autentisering
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        jsonResponse(['success' => false, 'error' => 'Ikke autorisert'], 401);
    }
}

// Håndter autentisering
$requestUri = $_SERVER['REQUEST_URI'];
if (strpos($requestUri, '/auth') !== false || (isset($_GET['action']) && $_GET['action'] === 'auth')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $password = $input['password'] ?? '';

        if (password_verify($password, ADMIN_PASSWORD_HASH)) {
            $_SESSION['admin_authenticated'] = true;
            $token = hash('sha256', ADMIN_PASSWORD . date('Y-m-d'));
            jsonResponse([
                'success' => true,
                'message' => 'Innlogget',
                'token' => $token
            ]);
        } else {
            jsonResponse(['success' => false, 'error' => 'Feil passord'], 401);
        }
    }
    exit;
}

// Sjekk utlogging
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION['admin_authenticated'] = false;
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logget ut']);
}

// Sjekk auth-status
if (isset($_GET['action']) && $_GET['action'] === 'check') {
    jsonResponse(['success' => true, 'authenticated' => isAuthenticated()]);
}

// Krev autentisering for alle andre operasjoner
requireAuth();

$db = getDbConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Hent én URL med detaljert statistikk
            getUrlDetails((int)$_GET['id']);
        } else {
            // List alle URLer
            listUrls();
        }
        break;

    case 'PUT':
        updateUrl();
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            errorResponse('ID er påkrevd', 400);
        }
        deleteUrl((int)$_GET['id']);
        break;

    default:
        errorResponse('Metode ikke støttet', 405);
}

/**
 * List alle URLer med paginering og søk
 */
function listUrls(): void {
    global $db;

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $sortBy = $_GET['sort'] ?? 'created_at';
    $sortOrder = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

    // Tillatte sorteringskolonner
    $allowedSorts = ['id', 'short_code', 'original_url', 'created_at', 'click_count', 'is_active'];
    if (!in_array($sortBy, $allowedSorts)) {
        $sortBy = 'created_at';
    }

    // Bygg query
    $whereClause = '';
    $params = [];

    if (!empty($search)) {
        $whereClause = 'WHERE short_code LIKE :search OR original_url LIKE :search2';
        $params[':search'] = "%$search%";
        $params[':search2'] = "%$search%";
    }

    // Tell totalt antall
    $countSql = "SELECT COUNT(*) FROM urls $whereClause";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Hent URLer
    $sql = "SELECT id, short_code, original_url, created_at, click_count, is_active, expires_at
            FROM urls
            $whereClause
            ORDER BY $sortBy $sortOrder
            LIMIT :limit OFFSET :offset";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $urls = $stmt->fetchAll();

    // Legg til full short URL
    foreach ($urls as &$url) {
        $url['short_url'] = BASE_URL . $url['short_code'];
    }

    jsonResponse([
        'success' => true,
        'data' => $urls,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Hent detaljer for én URL
 */
function getUrlDetails(int $id): void {
    global $db;

    // Hent URL
    $stmt = $db->prepare('SELECT * FROM urls WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $url = $stmt->fetch();

    if (!$url) {
        errorResponse('URL ikke funnet', 404);
    }

    $url['short_url'] = BASE_URL . $url['short_code'];

    // Hent daglig statistikk (siste 30 dager)
    $stmt = $db->prepare('
        SELECT DATE(clicked_at) as date, COUNT(*) as clicks
        FROM click_stats
        WHERE url_id = :id AND clicked_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(clicked_at)
        ORDER BY date ASC
    ');
    $stmt->execute([':id' => $id]);
    $dailyStats = $stmt->fetchAll();

    // Hent siste klikk
    $stmt = $db->prepare('
        SELECT clicked_at, ip_address, referer, user_agent
        FROM click_stats
        WHERE url_id = :id
        ORDER BY clicked_at DESC
        LIMIT 20
    ');
    $stmt->execute([':id' => $id]);
    $recentClicks = $stmt->fetchAll();

    // Hent referrer-statistikk
    $stmt = $db->prepare('
        SELECT
            CASE
                WHEN referer IS NULL OR referer = "" THEN "Direkte"
                ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referer, "/", 3), "://", -1)
            END as source,
            COUNT(*) as count
        FROM click_stats
        WHERE url_id = :id
        GROUP BY source
        ORDER BY count DESC
        LIMIT 10
    ');
    $stmt->execute([':id' => $id]);
    $referrerStats = $stmt->fetchAll();

    jsonResponse([
        'success' => true,
        'data' => [
            'url' => $url,
            'daily_stats' => $dailyStats,
            'recent_clicks' => $recentClicks,
            'referrer_stats' => $referrerStats
        ]
    ]);
}

/**
 * Oppdater URL
 */
function updateUrl(): void {
    global $db;

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        errorResponse('ID er påkrevd', 400);
    }

    $id = (int)$input['id'];

    // Sjekk at URL eksisterer
    $stmt = $db->prepare('SELECT id FROM urls WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        errorResponse('URL ikke funnet', 404);
    }

    // Bygg oppdaterings-query
    $updates = [];
    $params = [':id' => $id];

    if (isset($input['short_code'])) {
        $newCode = preg_replace('/[^a-zA-Z0-9_-]/', '', $input['short_code']);
        if (strlen($newCode) < 3 || strlen($newCode) > 20) {
            errorResponse('Kortkode må være mellom 3 og 20 tegn', 400);
        }

        // Sjekk at ny kode ikke er i bruk
        $stmt = $db->prepare('SELECT id FROM urls WHERE short_code = :code AND id != :id');
        $stmt->execute([':code' => $newCode, ':id' => $id]);
        if ($stmt->fetch()) {
            errorResponse('Denne kortkoden er allerede i bruk', 400);
        }

        $updates[] = 'short_code = :short_code';
        $params[':short_code'] = $newCode;
    }

    if (isset($input['original_url'])) {
        $url = filter_var($input['original_url'], FILTER_VALIDATE_URL);
        if (!$url) {
            errorResponse('Ugyldig URL', 400);
        }
        $updates[] = 'original_url = :original_url';
        $params[':original_url'] = $url;
    }

    if (isset($input['is_active'])) {
        $updates[] = 'is_active = :is_active';
        $params[':is_active'] = $input['is_active'] ? 1 : 0;
    }

    if (isset($input['expires_at'])) {
        $updates[] = 'expires_at = :expires_at';
        $params[':expires_at'] = $input['expires_at'] ?: null;
    }

    if (empty($updates)) {
        errorResponse('Ingen felt å oppdatere', 400);
    }

    $sql = 'UPDATE urls SET ' . implode(', ', $updates) . ' WHERE id = :id';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    jsonResponse(['success' => true, 'message' => 'URL oppdatert']);
}

/**
 * Slett URL
 */
function deleteUrl(int $id): void {
    global $db;

    // Sjekk at URL eksisterer
    $stmt = $db->prepare('SELECT id FROM urls WHERE id = :id');
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) {
        errorResponse('URL ikke funnet', 404);
    }

    // Slett (cascade sletter også click_stats)
    $stmt = $db->prepare('DELETE FROM urls WHERE id = :id');
    $stmt->execute([':id' => $id]);

    jsonResponse(['success' => true, 'message' => 'URL slettet']);
}
