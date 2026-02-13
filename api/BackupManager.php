<?php
/**
 * Backup Manager - File and Database Backup System
 *
 * Handles creation, restoration, and deletion of backups.
 * Supports both file backups (ZIP) and database dumps (SQL).
 *
 * @package    Snip
 * @version    1.0.3
 */

class BackupManager {
    private $db;
    private $backupDir;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->backupDir = dirname(__DIR__) . '/backup';
        @mkdir($this->backupDir, 0755, true);

        // Protect backup directory from web access
        $this->protectBackupDir();
    }

    /**
     * Create backup of database and files
     */
    public function createBackup(string $appVersion): string {
        $backupId = date('Y-m-d_His');
        $backupPath = $this->backupDir . '/' . $backupId;

        try {
            @mkdir($backupPath, 0755, true);

            // Backup database
            $this->backupDatabase($backupPath);

            // Backup files
            $this->backupFiles($backupPath);

            // Create manifest
            $manifest = [
                'backup_id' => $backupId,
                'version' => $appVersion,
                'created_at' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION,
                'file_count' => $this->countFiles($backupPath . '/files.zip'),
                'database_size' => filesize($backupPath . '/database.sql'),
                'integrity_verified' => true
            ];

            file_put_contents($backupPath . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            // Verify backup integrity
            if (!$this->verifyBackup($backupPath)) {
                throw new Exception("Backup verification failed");
            }

            // Cleanup old backups
            $this->cleanupOldBackups();

            return $backupId;

        } catch (Exception $e) {
            // Cleanup on failure
            $this->deleteDirectory($backupPath);
            throw new Exception("Backup creation failed: " . $e->getMessage());
        }
    }

    /**
     * Restore from backup
     */
    public function restoreBackup(string $backupId): void {
        $backupPath = $this->backupDir . '/' . $backupId;

        if (!is_dir($backupPath)) {
            throw new Exception("Backup not found: $backupId");
        }

        try {
            // Restore database first (transactional)
            $this->restoreDatabase($backupPath . '/database.sql');

            // Restore files
            $this->restoreFiles($backupPath . '/files.zip');

            // Update version in app_settings
            $manifest = json_decode(file_get_contents($backupPath . '/manifest.json'), true);
            $this->db->prepare("
                INSERT INTO app_settings (setting_key, setting_value)
                VALUES ('app_version', ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ")->execute([$manifest['version'] ?? '1.0.0']);

        } catch (Exception $e) {
            throw new Exception("Restore failed: " . $e->getMessage());
        }
    }

    /**
     * List available backups
     */
    public function listBackups(): array {
        $backups = [];

        if (!is_dir($this->backupDir)) {
            return $backups;
        }

        $dirs = scandir($this->backupDir, SCANDIR_SORT_DESCENDING);

        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($this->backupDir . '/' . $dir)) {
                continue;
            }

            $manifestPath = $this->backupDir . '/' . $dir . '/manifest.json';
            if (!file_exists($manifestPath)) {
                continue;
            }

            try {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $filesPath = $this->backupDir . '/' . $dir . '/files.zip';
                $dbPath = $this->backupDir . '/' . $dir . '/database.sql';

                $size = 0;
                if (file_exists($filesPath)) {
                    $size += filesize($filesPath);
                }
                if (file_exists($dbPath)) {
                    $size += filesize($dbPath);
                }

                $backups[] = [
                    'backup_id' => $dir,
                    'version' => $manifest['version'] ?? 'unknown',
                    'created_at' => $manifest['created_at'] ?? date('Y-m-d H:i:s'),
                    'size_bytes' => $size,
                    'is_valid' => $manifest['integrity_verified'] ?? false
                ];
            } catch (Exception $e) {
                // Skip invalid backups
                continue;
            }
        }

        return $backups;
    }

    /**
     * Delete backup
     */
    public function deleteBackup(string $backupId): void {
        $backupPath = $this->backupDir . '/' . $backupId;

        if (!is_dir($backupPath)) {
            throw new Exception("Backup not found");
        }

        $this->deleteDirectory($backupPath);
    }

    /**
     * Backup database
     */
    private function backupDatabase(string $backupPath): void {
        // Get database credentials from config
        if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
            throw new Exception("Database configuration not found");
        }

        // Use mysqldump command
        $dumpFile = $backupPath . '/database.sql';
        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s --single-transaction > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($dumpFile)
        );

        $output = null;
        $returnCode = null;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            // Try alternative backup method using PHP
            $this->backupDatabasePHP($backupPath);
        }

        if (!file_exists($dumpFile) || filesize($dumpFile) === 0) {
            throw new Exception("Database backup failed");
        }

        // Optional compression
        if (defined('BACKUP_COMPRESS_DATABASE') && BACKUP_COMPRESS_DATABASE) {
            $this->compressFile($dumpFile);
        }
    }

    /**
     * Backup database using PHP PDO
     */
    private function backupDatabasePHP(string $backupPath): void {
        $dumpFile = $backupPath . '/database.sql';
        $tables = [];

        // Get all tables
        $stmt = $this->db->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $sql = '';

        foreach ($tables as $table) {
            // Get table structure
            $stmt = $this->db->query("SHOW CREATE TABLE `$table`");
            $createRow = $stmt->fetch(PDO::FETCH_NUM);
            $sql .= "\n\n" . $createRow[1] . ";\n";

            // Get table data
            $stmt = $this->db->query("SELECT * FROM `$table`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $columns = implode('`, `', array_keys($row));
                $values = implode("', '", array_map([$this->db, 'quote'], array_values($row)));
                $sql .= "INSERT INTO `$table` (`$columns`) VALUES ('$values');\n";
            }
        }

        file_put_contents($dumpFile, $sql);

        if (filesize($dumpFile) === 0) {
            throw new Exception("PHP database backup failed");
        }
    }

    /**
     * Backup files
     */
    private function backupFiles(string $backupPath): void {
        $appRoot = dirname(__DIR__);
        $zipFile = $backupPath . '/files.zip';

        $zip = new ZipArchive();
        if (!$zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw new Exception("Could not create backup ZIP");
        }

        // Add files recursively, excluding backup and storage dirs
        $this->addFilesToZip($zip, $appRoot, '');

        if (!$zip->close()) {
            throw new Exception("Could not finalize backup ZIP");
        }

        if (!file_exists($zipFile)) {
            throw new Exception("Backup ZIP was not created");
        }
    }

    /**
     * Add files to ZIP recursively
     */
    private function addFilesToZip(ZipArchive $zip, string $dir, string $localPath): void {
        $excludeDirs = ['backup', 'storage', '.git', 'node_modules', '.claude'];

        $files = @scandir($dir);
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if (in_array($file, $excludeDirs)) {
                continue;
            }

            $filePath = $dir . '/' . $file;
            $arcPath = $localPath ? $localPath . '/' . $file : $file;

            if (is_dir($filePath)) {
                $this->addFilesToZip($zip, $filePath, $arcPath);
            } else {
                $zip->addFile($filePath, $arcPath);
            }
        }
    }

    /**
     * Restore database
     */
    private function restoreDatabase(string $sqlFile): void {
        if (!file_exists($sqlFile)) {
            throw new Exception("Database backup file not found");
        }

        // Handle compressed files
        $actualFile = $sqlFile;
        if (substr($sqlFile, -3) === '.gz') {
            $actualFile = $sqlFile . '.uncompressed';
            file_put_contents($actualFile, gzuncompress(file_get_contents($sqlFile)));
        }

        // Read and execute SQL
        $sql = file_get_contents($actualFile);

        // Split by semicolons and execute
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        try {
            $this->db->beginTransaction();

            foreach ($statements as $statement) {
                if (empty($statement)) {
                    continue;
                }
                $this->db->exec($statement);
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();

            // Clean up uncompressed file if created
            if (substr($sqlFile, -3) === '.gz') {
                @unlink($actualFile);
            }

            throw new Exception("Database restore failed: " . $e->getMessage());
        }

        // Clean up uncompressed file if created
        if (substr($sqlFile, -3) === '.gz') {
            @unlink($actualFile);
        }
    }

    /**
     * Restore files
     */
    private function restoreFiles(string $zipFile): void {
        if (!file_exists($zipFile)) {
            throw new Exception("Files backup not found");
        }

        $appRoot = dirname(__DIR__);
        $zip = new ZipArchive();

        if (!$zip->open($zipFile)) {
            throw new Exception("Could not open backup ZIP");
        }

        if (!$zip->extractTo($appRoot)) {
            throw new Exception("Could not extract backup files");
        }

        $zip->close();
    }

    /**
     * Verify backup
     */
    private function verifyBackup(string $backupPath): bool {
        // Check manifest exists
        if (!file_exists($backupPath . '/manifest.json')) {
            return false;
        }

        // Check files backup
        if (!file_exists($backupPath . '/files.zip')) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($backupPath . '/files.zip')) {
            return false;
        }
        $zip->close();

        // Check database backup
        if (!file_exists($backupPath . '/database.sql') && !file_exists($backupPath . '/database.sql.gz')) {
            return false;
        }

        return true;
    }

    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups(): void {
        $backups = $this->listBackups();

        // Keep only BACKUP_RETENTION_COUNT backups
        $keep = defined('BACKUP_RETENTION_COUNT') ? BACKUP_RETENTION_COUNT : 10;
        $maxAge = defined('BACKUP_MAX_AGE_DAYS') ? BACKUP_MAX_AGE_DAYS : 90;

        $cutoffDate = time() - ($maxAge * 86400);

        foreach ($backups as $index => $backup) {
            // Delete if beyond retention count
            if ($index >= $keep) {
                $this->deleteBackup($backup['backup_id']);
                continue;
            }

            // Delete if too old
            $backupTime = strtotime($backup['created_at']);
            if ($backupTime < $cutoffDate) {
                $this->deleteBackup($backup['backup_id']);
            }
        }
    }

    /**
     * Count files in ZIP
     */
    private function countFiles(string $zipFile): int {
        if (!file_exists($zipFile)) {
            return 0;
        }

        $zip = new ZipArchive();
        $count = 0;

        if ($zip->open($zipFile)) {
            $count = $zip->numFiles;
            $zip->close();
        }

        return $count;
    }

    /**
     * Compress file with gzip
     */
    private function compressFile(string $filePath): void {
        if (!file_exists($filePath)) {
            return;
        }

        $gzPath = $filePath . '.gz';
        $content = file_get_contents($filePath);
        file_put_contents($gzPath, gzcompress($content, 9));

        // Replace original with compressed version
        unlink($filePath);
        rename($gzPath, $filePath . '.gz');
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = @scandir($dir);
        if (!$files) {
            return;
        }

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
     * Protect backup directory from web access
     */
    private function protectBackupDir(): void {
        $htaccessPath = $this->backupDir . '/.htaccess';

        if (!file_exists($htaccessPath)) {
            $htaccess = "Deny from all\n";
            @file_put_contents($htaccessPath, $htaccess);
        }
    }
}
