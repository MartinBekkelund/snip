<?php
/**
 * Automated Update System - Core Updater Class
 *
 * Orchestrates the update process: checking, downloading, installing,
 * and rolling back on failure.
 *
 * @package    Snip
 * @version    1.0.3
 */

class Updater {
    private $db;
    private $versionChecker;
    private $backupManager;
    private $logFile;

    const EXCLUDED_PATHS = [
        'api/config.php',
        'backup/',
        'storage/',
        '.env',
        '.maintenance',
        '.update-in-progress'
    ];

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->logFile = dirname(__DIR__) . '/storage/logs/updates.log';
        @mkdir(dirname($this->logFile), 0755, true);

        require_once __DIR__ . '/VersionChecker.php';
        require_once __DIR__ . '/BackupManager.php';

        $this->versionChecker = new VersionChecker();
        $this->backupManager = new BackupManager($db);
    }

    /**
     * Check for available updates
     */
    public function checkForUpdates(): array {
        try {
            $latest = $this->versionChecker->getLatestVersion();
            $current = $this->getCurrentVersion();

            $this->log("Checking for updates: current=$current, latest=$latest");

            return [
                'update_available' => version_compare($latest, $current, '>'),
                'current_version' => $current,
                'latest_version' => $latest,
                'release_notes' => $this->versionChecker->getReleaseNotes($latest)
            ];
        } catch (Exception $e) {
            $this->log("Update check failed: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Perform update to target version
     */
    public function update(string $targetVersion, bool $createBackup = true): array {
        $startTime = microtime(true);
        $backupId = null;

        try {
            $this->log("Starting update to version $targetVersion");

            // 1. Validate environment
            $this->validateEnvironment();
            $this->log("Environment validation passed");

            // 2. Record update attempt
            $updateId = $this->recordUpdateAttempt($targetVersion, 'in_progress');

            // 3. Create backup if requested
            if ($createBackup) {
                $this->log("Creating backup before update");
                $backupId = $this->backupManager->createBackup($this->getCurrentVersion());
                $this->log("Backup created: $backupId");
            }

            // 4. Enable maintenance mode
            require_once __DIR__ . '/MaintenanceMode.php';
            $maintenanceMode = new MaintenanceMode();
            $maintenanceMode->enable('Updating to version ' . $targetVersion);
            $this->log("Maintenance mode enabled");

            try {
                // 5. Download release
                $this->log("Downloading release $targetVersion from GitHub");
                $releaseFile = $this->downloadRelease($targetVersion);

                // 6. Verify hash
                $this->log("Verifying SHA256 hash");
                $this->verifyReleaseHash($releaseFile, $targetVersion);

                // 7. Extract and update files
                $this->log("Extracting and installing files");
                $this->extractAndInstall($releaseFile);

                // 8. Run migrations
                $this->log("Running database migrations");
                $this->runMigrations();

                // 9. Verify integrity
                $this->log("Verifying update integrity");
                $this->verifyIntegrity();

                // 10. Disable maintenance mode
                $maintenanceMode->disable();
                $this->log("Maintenance mode disabled");

                // 11. Clean up
                @unlink($releaseFile);
                $this->log("Cleanup completed");

                // Record success
                $duration = round(microtime(true) - $startTime);
                $this->recordUpdateSuccess($updateId, $targetVersion, $duration, $backupId);
                $this->log("Update completed successfully in $duration seconds");

                return [
                    'success' => true,
                    'message' => "Successfully updated to version $targetVersion",
                    'version' => $targetVersion,
                    'duration' => $duration,
                    'backup_id' => $backupId
                ];

            } catch (Exception $e) {
                $this->log("Update failed: " . $e->getMessage(), 'ERROR');
                $maintenanceMode->disable();

                // Attempt rollback
                if ($backupId) {
                    $this->log("Rolling back from backup: $backupId", 'ERROR');
                    try {
                        $this->backupManager->restoreBackup($backupId);
                        $this->log("Rollback completed successfully", 'ERROR');
                    } catch (Exception $rollbackError) {
                        $this->log("Rollback failed: " . $rollbackError->getMessage(), 'ERROR');
                        throw new Exception("Update failed and rollback failed: " . $e->getMessage());
                    }
                }

                $duration = round(microtime(true) - $startTime);
                $this->recordUpdateFailure($updateId, $e->getMessage(), $duration);
                throw $e;
            }

        } catch (Exception $e) {
            $this->log("Update process error: " . $e->getMessage(), 'ERROR');
            $duration = round(microtime(true) - $startTime);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'duration' => $duration,
                'backup_id' => $backupId
            ];
        }
    }

    /**
     * Download release from GitHub
     */
    private function downloadRelease(string $version): string {
        $releaseUrl = sprintf(
            'https://github.com/%s/%s/archive/refs/tags/v%s.zip',
            GITHUB_REPO_OWNER,
            GITHUB_REPO_NAME,
            $version
        );

        $tempFile = dirname(__DIR__) . '/storage/' . 'release-' . $version . '-' . time() . '.zip';
        @mkdir(dirname($tempFile), 0755, true);

        $context = stream_context_create([
            'http' => ['timeout' => GITHUB_API_TIMEOUT],
            'https' => ['timeout' => GITHUB_API_TIMEOUT]
        ]);

        $data = @file_get_contents($releaseUrl, false, $context);
        if ($data === false) {
            throw new Exception("Failed to download release from GitHub");
        }

        file_put_contents($tempFile, $data);
        return $tempFile;
    }

    /**
     * Verify release hash
     */
    private function verifyReleaseHash(string $filePath, string $version): void {
        $expectedHash = $this->versionChecker->getReleaseHash($version);

        if (!$expectedHash) {
            $this->log("Warning: No hash found for version $version, skipping verification", 'WARN');
            return;
        }

        $actualHash = hash_file('sha256', $filePath);

        if (!hash_equals($expectedHash, $actualHash)) {
            throw new Exception("SHA256 hash verification failed for version $version");
        }

        $this->log("SHA256 hash verification passed");
    }

    /**
     * Extract and install files
     */
    private function extractAndInstall(string $zipFile): void {
        $zip = new ZipArchive();
        if (!$zip->open($zipFile)) {
            throw new Exception("Failed to open release ZIP file");
        }

        $tempDir = dirname(__DIR__) . '/storage/update-temp-' . time();
        @mkdir($tempDir, 0755, true);

        $zip->extractTo($tempDir);
        $zip->close();

        // Find the extracted directory (typically snip-version/)
        $extracted = glob($tempDir . '/*', GLOB_ONLYDIR);
        if (empty($extracted)) {
            throw new Exception("Invalid release ZIP structure");
        }

        $sourceDir = $extracted[0];
        $appRoot = dirname(__DIR__);

        // Copy files, excluding protected paths
        $this->copyFiles($sourceDir, $appRoot);

        // Cleanup temp dir
        $this->deleteDirectory($tempDir);

        $this->log("Files installed successfully");
    }

    /**
     * Copy files recursively, excluding protected paths
     */
    private function copyFiles(string $from, string $to): void {
        if (!is_dir($from)) {
            return;
        }

        $files = scandir($from);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fromPath = $from . '/' . $file;
            $toPath = $to . '/' . $file;
            $relativePath = $file;

            // Check if this path is protected
            if ($this->isProtectedPath($relativePath)) {
                $this->log("Skipping protected path: $relativePath");
                continue;
            }

            if (is_dir($fromPath)) {
                @mkdir($toPath, 0755, true);
                $this->copyFiles($fromPath, $toPath);
            } else {
                @mkdir(dirname($toPath), 0755, true);
                copy($fromPath, $toPath);
            }
        }
    }

    /**
     * Check if path is protected from updates
     */
    private function isProtectedPath(string $path): bool {
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if (strpos($path, $excluded) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void {
        // Check and create update tracking tables if needed
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS update_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    old_version VARCHAR(20),
                    new_version VARCHAR(20),
                    status VARCHAR(20) DEFAULT 'pending',
                    duration_seconds INT,
                    error_message TEXT,
                    backup_id VARCHAR(50),
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS app_settings (
                    setting_key VARCHAR(100) PRIMARY KEY,
                    setting_value TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                );
            ");

            // Update version in app_settings
            $this->db->prepare("
                INSERT INTO app_settings (setting_key, setting_value)
                VALUES ('app_version', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute([SNIP_VERSION]);

            $this->log("Database migrations completed");
        } catch (Exception $e) {
            $this->log("Migration warning: " . $e->getMessage(), 'WARN');
        }
    }

    /**
     * Verify update integrity
     */
    private function verifyIntegrity(): void {
        // Check that core files exist
        $requiredFiles = [
            'api/admin.php',
            'api/config.php',
            'api/UrlShortener.php',
            'index.html'
        ];

        foreach ($requiredFiles as $file) {
            if (!file_exists(dirname(__DIR__) . '/' . $file)) {
                throw new Exception("Missing required file after update: $file");
            }
        }

        $this->log("Integrity check passed");
    }

    /**
     * Validate system environment
     */
    private function validateEnvironment(): void {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new Exception("PHP 7.4 or higher required");
        }

        // Check write permissions
        $appRoot = dirname(__DIR__);
        if (!is_writable($appRoot)) {
            throw new Exception("Application directory is not writable");
        }

        // Check database connection
        try {
            $this->db->query("SELECT 1");
        } catch (Exception $e) {
            throw new Exception("Database connection failed");
        }

        $this->log("Environment validation successful");
    }

    /**
     * Record update attempt in database
     */
    private function recordUpdateAttempt(string $targetVersion, string $status): ?int {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO update_history (old_version, new_version, status, updated_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$this->getCurrentVersion(), $targetVersion, $status]);
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            $this->log("Could not record update attempt: " . $e->getMessage(), 'WARN');
            return null;
        }
    }

    /**
     * Record successful update
     */
    private function recordUpdateSuccess(?int $updateId, string $version, int $duration, ?string $backupId): void {
        if (!$updateId) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE update_history
                SET status = 'success', duration_seconds = ?, backup_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$duration, $backupId, $updateId]);
        } catch (Exception $e) {
            $this->log("Could not record update success: " . $e->getMessage(), 'WARN');
        }
    }

    /**
     * Record failed update
     */
    private function recordUpdateFailure(?int $updateId, string $error, int $duration): void {
        if (!$updateId) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE update_history
                SET status = 'failed', error_message = ?, duration_seconds = ?
                WHERE id = ?
            ");
            $stmt->execute([substr($error, 0, 500), $duration, $updateId]);
        } catch (Exception $e) {
            $this->log("Could not record update failure: " . $e->getMessage(), 'WARN');
        }
    }

    /**
     * Get update history
     */
    public function getUpdateHistory(int $limit = 50): array {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM update_history
                ORDER BY updated_at DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->log("Could not retrieve update history: " . $e->getMessage(), 'WARN');
            return [];
        }
    }

    /**
     * Get current application version
     */
    public function getCurrentVersion(): string {
        // Try database first
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'app_version' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result) {
                return $result['setting_value'];
            }
        } catch (Exception $e) {
            // Fall through to default
        }

        // Fall back to constant
        return defined('SNIP_VERSION') ? SNIP_VERSION : '1.0.0';
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Log message
     */
    private function log(string $message, string $level = 'INFO'): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message\n";
        @file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
}
