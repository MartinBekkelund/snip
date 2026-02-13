<?php
/**
 * Maintenance Mode Manager
 *
 * Enables/disables maintenance mode during updates with automatic
 * timeout to prevent being stuck in maintenance mode.
 *
 * @package    Snip
 * @version    1.0.3
 */

class MaintenanceMode {
    private $flagFile;
    private $timeout;

    public function __construct() {
        $this->flagFile = dirname(__DIR__) . '/.maintenance';
        $this->timeout = defined('MAINTENANCE_MODE_TIMEOUT') ? MAINTENANCE_MODE_TIMEOUT : 600; // 10 minutes
    }

    /**
     * Enable maintenance mode
     */
    public function enable(string $message = 'System maintenance in progress', int $expiresInSeconds = null): void {
        $expiresAt = time() + ($expiresInSeconds ?? $this->timeout);

        $data = [
            'enabled' => true,
            'message' => $message,
            'started_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            'expires_timestamp' => $expiresAt
        ];

        @file_put_contents($this->flagFile, json_encode($data));
        @chmod($this->flagFile, 0644);
    }

    /**
     * Disable maintenance mode
     */
    public function disable(): void {
        @unlink($this->flagFile);
    }

    /**
     * Check if maintenance mode is enabled
     */
    public function isEnabled(): bool {
        if (!file_exists($this->flagFile)) {
            return false;
        }

        $data = $this->getData();

        // Check if expired
        if (isset($data['expires_timestamp']) && $data['expires_timestamp'] < time()) {
            $this->disable();
            return false;
        }

        return $data['enabled'] ?? false;
    }

    /**
     * Check if user should bypass maintenance mode
     */
    public function shouldBypass(): bool {
        // Check if admin is authenticated
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
    }

    /**
     * Get maintenance status
     */
    public function getStatus(): array {
        if (!$this->isEnabled()) {
            return [
                'enabled' => false
            ];
        }

        $data = $this->getData();

        return [
            'enabled' => true,
            'message' => $data['message'] ?? 'System maintenance in progress',
            'started_at' => $data['started_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'expires_timestamp' => $data['expires_timestamp'] ?? time()
        ];
    }

    /**
     * Get data from maintenance flag file
     */
    private function getData(): array {
        if (!file_exists($this->flagFile)) {
            return [];
        }

        try {
            $content = file_get_contents($this->flagFile);
            $data = json_decode($content, true);
            return is_array($data) ? $data : [];
        } catch (Exception $e) {
            return [];
        }
    }
}
