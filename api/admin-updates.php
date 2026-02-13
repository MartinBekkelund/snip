<?php
/**
 * Admin Updates API Endpoints
 *
 * Handles update checking, triggering, and backup management
 *
 * @package    Snip
 * @version    1.1.0
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Updater.php';
require_once __DIR__ . '/BackupManager.php';
require_once __DIR__ . '/MaintenanceMode.php';

// Start session
startSecureSession();

// Handle CORS
header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
header('Access-Control-Allow-Credentials: true');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    exit;
}

// Check maintenance mode
$maintenanceMode = new MaintenanceMode();
if ($maintenanceMode->isEnabled() && !$maintenanceMode->shouldBypass()) {
    // Load maintenance page
    include __DIR__ . '/../maintenance.html';
    exit;
}

// Get action from request
$action = $_GET['action'] ?? '';

// Route to appropriate handler
try {
    switch ($action) {
        case 'check-updates':
            checkUpdates();
            break;

        case 'update':
            performUpdate();
            break;

        case 'backups-list':
            listBackups();
            break;

        case 'backup-restore':
            restoreBackup();
            break;

        case 'backup-delete':
            deleteBackup();
            break;

        case 'update-history':
            getUpdateHistory();
            break;

        case 'maintenance-status':
            getMaintenanceStatus();
            break;

        default:
            jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
    }

} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
}

/**
 * Check for available updates
 */
function checkUpdates(): void {
    // Require admin authentication
    requireAdmin();

    try {
        $db = getDbConnection();
        $updater = new Updater($db);

        $result = $updater->checkForUpdates();

        jsonResponse([
            'success' => true,
            'update_available' => $result['update_available'] ?? false,
            'current_version' => $result['current_version'] ?? '1.0.0',
            'latest_version' => $result['latest_version'] ?? '1.0.0',
            'release_notes' => $result['release_notes'] ?? ''
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Perform update to latest version
 */
function performUpdate(): void {
    // Require admin authentication
    requireAdmin();

    // Check request method (must be POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST required'], 405);
    }

    // Get target version from request
    $input = json_decode(file_get_contents('php://input'), true);
    $targetVersion = $input['version'] ?? null;

    if (!$targetVersion) {
        jsonResponse(['success' => false, 'error' => 'Version required'], 400);
    }

    try {
        $db = getDbConnection();
        $updater = new Updater($db);

        // Check if update already in progress
        if (file_exists(dirname(__DIR__) . '/.update-in-progress')) {
            jsonResponse(['success' => false, 'error' => 'Update already in progress'], 409);
        }

        // Create lock file
        file_put_contents(dirname(__DIR__) . '/.update-in-progress', time());

        try {
            // Perform update
            $result = $updater->update($targetVersion, true);

            jsonResponse([
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'Update failed',
                'duration' => $result['duration'] ?? 0,
                'backup_id' => $result['backup_id'] ?? null
            ]);

        } finally {
            // Remove lock file
            @unlink(dirname(__DIR__) . '/.update-in-progress');
        }

    } catch (Exception $e) {
        @unlink(dirname(__DIR__) . '/.update-in-progress');
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * List available backups
 */
function listBackups(): void {
    // Require admin authentication
    requireAdmin();

    try {
        $db = getDbConnection();
        $backupManager = new BackupManager($db);

        $backups = $backupManager->listBackups();

        jsonResponse([
            'success' => true,
            'backups' => $backups,
            'count' => count($backups)
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Restore from backup
 */
function restoreBackup(): void {
    // Require admin authentication
    requireAdmin();

    // Check request method (must be POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'POST required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $backupId = $input['backup_id'] ?? null;

    if (!$backupId) {
        jsonResponse(['success' => false, 'error' => 'Backup ID required'], 400);
    }

    try {
        $db = getDbConnection();
        $backupManager = new BackupManager($db);

        // Validate backup ID (alphanumeric + underscore + dash)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $backupId)) {
            jsonResponse(['success' => false, 'error' => 'Invalid backup ID'], 400);
        }

        // Enable maintenance mode
        $maintenanceMode = new MaintenanceMode();
        $maintenanceMode->enable('Restoring from backup...');

        try {
            // Restore backup
            $backupManager->restoreBackup($backupId);

            // Disable maintenance mode
            $maintenanceMode->disable();

            jsonResponse([
                'success' => true,
                'message' => 'Successfully restored from backup: ' . $backupId
            ]);

        } catch (Exception $e) {
            $maintenanceMode->disable();
            throw $e;
        }

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Delete backup
 */
function deleteBackup(): void {
    // Require admin authentication
    requireAdmin();

    // Check request method (must be DELETE or POST)
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => 'DELETE or POST required'], 405);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $backupId = $input['backup_id'] ?? $_GET['backup_id'] ?? null;

    if (!$backupId) {
        jsonResponse(['success' => false, 'error' => 'Backup ID required'], 400);
    }

    try {
        $db = getDbConnection();
        $backupManager = new BackupManager($db);

        // Validate backup ID
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $backupId)) {
            jsonResponse(['success' => false, 'error' => 'Invalid backup ID'], 400);
        }

        $backupManager->deleteBackup($backupId);

        jsonResponse([
            'success' => true,
            'message' => 'Backup deleted: ' . $backupId
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Get update history
 */
function getUpdateHistory(): void {
    // Require admin authentication
    requireAdmin();

    try {
        $db = getDbConnection();
        $updater = new Updater($db);

        $limit = (int)($_GET['limit'] ?? 50);
        $limit = min(100, max(10, $limit)); // Constrain between 10-100

        $history = $updater->getUpdateHistory($limit);

        jsonResponse([
            'success' => true,
            'history' => $history,
            'count' => count($history)
        ]);

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
    }
}

/**
 * Get maintenance mode status
 */
function getMaintenanceStatus(): void {
    // This endpoint is public (needed for maintenance.html page)

    $maintenanceMode = new MaintenanceMode();

    if ($maintenanceMode->isEnabled()) {
        $status = $maintenanceMode->getStatus();

        jsonResponse([
            'success' => true,
            'enabled' => true,
            'message' => $status['message'] ?? 'System maintenance in progress',
            'started_at' => $status['started_at'] ?? null,
            'expires_at' => $status['expires_at'] ?? null
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'enabled' => false
        ]);
    }
}

/**
 * Require admin authentication
 */
function requireAdmin(): void {
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
    }
}
