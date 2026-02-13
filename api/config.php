<?php
define('DB_CONFIGURED', true);
// Version
define('SNIP_VERSION', '1.0.0');

define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('BASE_URL', '');
define('SHORT_CODE_LENGTH', 6);
define('ALLOWED_PROTOCOLS', ['http://', 'https://']);
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX_REQUESTS', 10);
define('RATE_LIMIT_WINDOW_SECONDS', 60);
define('RATE_LIMIT_LOGIN_MAX', 5);

define('RATE_LIMIT_LOGIN_WINDOW', 300);

define('ANONYMIZE_IP', true);

define('TOKEN_EXPIRY_HOURS', 24);

define('RESERVED_CODES', ['admin', 'api', 'stats', 'login', 'logout', 'install']);
define('CORS_ORIGIN', '*');
function getDbConnection(): PDO { static $p=null; if($p===null){ $p=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]); } return $p; }
function jsonResponse(array $d,int $c=200):void{http_response_code($c);header('Content-Type:application/json');header('Access-Control-Allow-Origin:'.CORS_ORIGIN);echo json_encode($d);exit;}
function errorResponse(string $m,int $c=400):void{jsonResponse(['success'=>false,'error'=>$m],$c);}

/**
 * Send security headers
 */
function sendSecurityHeaders(): void {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Anonymize IP address for GDPR compliance
 */
function anonymizeIp(string $ip): string {
    if (!ANONYMIZE_IP) return $ip;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return preg_replace('/\.\d+$/', '.0', $ip);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $packed = inet_pton($ip);
        $packed = substr($packed, 0, 6) . str_repeat("\0", 10);
        return inet_ntop($packed);
    }
    return 'unknown';
}

/**
 * Get client IP address (anonymized)
 */
function getClientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    return anonymizeIp($ip);
}

/**
 * Check rate limit
 */
function checkRateLimit(string $key, int $maxRequests = null, int $windowSeconds = null): bool {
    if (!RATE_LIMIT_ENABLED) return true;
    $maxRequests = $maxRequests ?? RATE_LIMIT_MAX_REQUESTS;
    $windowSeconds = $windowSeconds ?? RATE_LIMIT_WINDOW_SECONDS;
    try {
        $db = getDbConnection();
        $db->prepare('DELETE FROM rate_limits WHERE expires_at < NOW()')->execute();
        $stmt = $db->prepare('SELECT request_count FROM rate_limits WHERE rate_key = :key AND expires_at > NOW()');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        if ($row) {
            if ($row['request_count'] >= $maxRequests) return false;
            $db->prepare('UPDATE rate_limits SET request_count = request_count + 1 WHERE rate_key = :key')->execute([':key' => $key]);
        } else {
            $db->prepare('INSERT INTO rate_limits (rate_key, request_count, expires_at) VALUES (:key, 1, DATE_ADD(NOW(), INTERVAL :window SECOND))')->execute([':key' => $key, ':window' => $windowSeconds]);
        }
        return true;
    } catch (Exception $e) {
        error_log('Snip rate limit error: ' . $e->getMessage());
        return true;
    }
}

/**
 * Generate cryptographically secure token
 */
function generateSecureToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Start secure session
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'use_only_cookies' => true
        ]);
    }
}
