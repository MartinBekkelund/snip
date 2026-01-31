<?php
/**
 * URL Shortener - Hovedklasse
 * 
 * Håndterer all logikk for forkorting og omdirigering av URLer.
 */

require_once __DIR__ . '/config.php';

class UrlShortener {
    private PDO $db;
    
    public function __construct() {
        $this->db = getDbConnection();
    }
    
    /**
     * Forkorter en URL
     */
    public function shorten(string $url, ?string $customCode = null): array {
        // Valider URL
        $url = $this->validateUrl($url);
        
        // Sjekk om URL allerede eksisterer
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
        
        // Generer eller valider kortkode
        if ($customCode) {
            $shortCode = $this->validateCustomCode($customCode);
        } else {
            $shortCode = $this->generateUniqueCode();
        }
        
        // Lagre i database
        $stmt = $this->db->prepare('
            INSERT INTO urls (short_code, original_url, ip_address, user_agent)
            VALUES (:short_code, :original_url, :ip_address, :user_agent)
        ');
        
        $stmt->execute([
            ':short_code' => $shortCode,
            ':original_url' => $url,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
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
     * Henter original URL fra kortkode
     */
    public function resolve(string $shortCode): ?array {
        $shortCode = $this->sanitizeCode($shortCode);
        
        $stmt = $this->db->prepare('
            SELECT id, short_code, original_url, click_count, created_at
            FROM urls
            WHERE short_code = :short_code AND is_active = TRUE
        ');
        
        $stmt->execute([':short_code' => $shortCode]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Registrerer et klikk og returnerer omdirigerings-URL
     */
    public function redirect(string $shortCode): ?string {
        $url = $this->resolve($shortCode);
        
        if (!$url) {
            return null;
        }
        
        // Oppdater klikkteller
        $this->db->prepare('
            UPDATE urls SET click_count = click_count + 1 WHERE id = :id
        ')->execute([':id' => $url['id']]);
        
        // Logg klikk-statistikk
        $this->logClick($url['id']);
        
        return $url['original_url'];
    }
    
    /**
     * Henter statistikk for en kortkode
     */
    public function getStats(string $shortCode): ?array {
        $url = $this->resolve($shortCode);
        
        if (!$url) {
            return null;
        }
        
        // Hent klikk per dag (siste 30 dager)
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
        
        // Hent siste klikk
        $stmt = $this->db->prepare('
            SELECT clicked_at, country_code, referer
            FROM click_stats
            WHERE url_id = :url_id
            ORDER BY clicked_at DESC
            LIMIT 10
        ');
        $stmt->execute([':url_id' => $url['id']]);
        $recentClicks = $stmt->fetchAll();
        
        return [
            'short_code' => $url['short_code'],
            'original_url' => $url['original_url'],
            'total_clicks' => $url['click_count'],
            'created_at' => $url['created_at'],
            'daily_clicks' => $dailyClicks,
            'recent_clicks' => $recentClicks
        ];
    }
    
    /**
     * Logger et klikk i statistikk-tabellen
     */
    private function logClick(int $urlId): void {
        $stmt = $this->db->prepare('
            INSERT INTO click_stats (url_id, ip_address, user_agent, referer)
            VALUES (:url_id, :ip_address, :user_agent, :referer)
        ');
        
        $stmt->execute([
            ':url_id' => $urlId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':referer' => $_SERVER['HTTP_REFERER'] ?? null
        ]);
    }
    
    /**
     * Validerer og normaliserer en URL
     */
    private function validateUrl(string $url): string {
        $url = trim($url);
        
        // Sjekk om URL er tom
        if (empty($url)) {
            throw new InvalidArgumentException('URL kan ikke være tom');
        }
        
        // Legg til https:// hvis ingen protokoll
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
        
        // Valider URL-format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Ugyldig URL-format');
        }
        
        // Sjekk at URL ikke peker til vår egen tjeneste
        if (stripos($url, BASE_URL) === 0) {
            throw new InvalidArgumentException('Kan ikke forkorte lenker til denne tjenesten');
        }
        
        return $url;
    }
    
    /**
     * Validerer en egendefinert kortkode
     */
    private function validateCustomCode(string $code): string {
        $code = $this->sanitizeCode($code);
        
        // Sjekk lengde
        if (strlen($code) < 3 || strlen($code) > 20) {
            throw new InvalidArgumentException('Egendefinert kode må være mellom 3 og 20 tegn');
        }
        
        // Sjekk mot reserverte koder
        if (in_array(strtolower($code), RESERVED_CODES)) {
            throw new InvalidArgumentException('Denne koden er reservert');
        }
        
        // Sjekk om koden allerede er i bruk
        if ($this->codeExists($code)) {
            throw new InvalidArgumentException('Denne koden er allerede i bruk');
        }
        
        return $code;
    }
    
    /**
     * Saniterer en kortkode
     */
    private function sanitizeCode(string $code): string {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $code);
    }
    
    /**
     * Genererer en unik kortkode
     */
    private function generateUniqueCode(): string {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 10;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $code = '';
            for ($i = 0; $i < SHORT_CODE_LENGTH; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
            
            if (!$this->codeExists($code) && !in_array(strtolower($code), RESERVED_CODES)) {
                return $code;
            }
        }
        
        throw new RuntimeException('Kunne ikke generere unik kode');
    }
    
    /**
     * Sjekker om en kortkode eksisterer
     */
    private function codeExists(string $code): bool {
        $stmt = $this->db->prepare('SELECT 1 FROM urls WHERE short_code = :code');
        $stmt->execute([':code' => $code]);
        return (bool) $stmt->fetch();
    }
    
    /**
     * Finner URL basert på original URL
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
