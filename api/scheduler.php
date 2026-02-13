<?php
/**
 * Automated Update Scheduler
 *
 * Cron-triggered script for checking and installing updates
 *
 * Usage:
 *   php api/scheduler.php check    # Check for updates only
 *   php api/scheduler.php update   # Check and install if available
 *   php api/scheduler.php backup   # Create backup only
 *
 * @package    Snip
 * @version    1.0.3
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    echo "This script can only be run from command line\n";
    exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Updater.php';
require_once __DIR__ . '/BackupManager.php';

// Get action from command line
$action = $argv[1] ?? 'check';

// Setup logging
$logFile = dirname(__DIR__) . '/storage/logs/scheduler.log';
@mkdir(dirname($logFile), 0755, true);

function logScheduler(string $message, string $level = 'INFO'): void {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

try {
    logScheduler("Scheduler started with action: $action");

    $db = getDbConnection();
    $updater = new Updater($db);

    switch ($action) {
        case 'check':
            checkForUpdates($updater);
            break;

        case 'update':
            performUpdate($updater);
            break;

        case 'backup':
            createBackupOnly($updater);
            break;

        default:
            logScheduler("Unknown action: $action", 'ERROR');
            echo "Unknown action: $action\n";
            exit(1);
    }

    logScheduler("Scheduler completed successfully");

} catch (Exception $e) {
    logScheduler("Scheduler error: " . $e->getMessage(), 'ERROR');
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Check for available updates
 */
function checkForUpdates(Updater $updater): void {
    logScheduler('Checking for updates...');

    $result = $updater->checkForUpdates();

    if ($result['update_available'] ?? false) {
        logScheduler("Update available: {$result['latest_version']}");
        echo "Update available: {$result['latest_version']}\n";

        // Notify admin (could send email, webhook, etc.)
        notifyAdmin("Update available: v{$result['latest_version']}", $result);

    } else {
        logScheduler("No update available");
        echo "No update available\n";
    }
}

/**
 * Perform update if available
 */
function performUpdate(Updater $updater): void {
    logScheduler('Checking for updates and installing if available...');

    $checkResult = $updater->checkForUpdates();

    if (!($checkResult['update_available'] ?? false)) {
        logScheduler('No update available');
        echo "No update available\n";
        return;
    }

    $targetVersion = $checkResult['latest_version'];
    logScheduler("Update available: $targetVersion, starting installation...");

    $result = $updater->update($targetVersion, true);

    if ($result['success'] ?? false) {
        logScheduler("Update completed successfully to v$targetVersion");
        echo "Update completed: v$targetVersion\n";

        // Notify admin of successful update
        notifyAdmin("Update successful: v$targetVersion", $result);

    } else {
        logScheduler("Update failed: " . ($result['message'] ?? 'Unknown error'), 'ERROR');
        echo "Update failed: " . ($result['message'] ?? 'Unknown error') . "\n";

        // Notify admin of failed update
        notifyAdmin("Update failed: " . ($result['message'] ?? 'Unknown error'), $result, true);
    }
}

/**
 * Create backup only
 */
function createBackupOnly(Updater $updater): void {
    logScheduler('Creating backup...');

    try {
        $backupManager = new BackupManager(getDbConnection());
        $backupId = $backupManager->createBackup($updater->getCurrentVersion());

        logScheduler("Backup created: $backupId");
        echo "Backup created: $backupId\n";

    } catch (Exception $e) {
        logScheduler("Backup failed: " . $e->getMessage(), 'ERROR');
        echo "Backup failed: " . $e->getMessage() . "\n";
        throw $e;
    }
}

/**
 * Notify admin of update status
 */
function notifyAdmin(string $subject, array $details, bool $isError = false): void {
    // Could implement email notification here
    // For now, just log it

    logScheduler("Admin notification: $subject");

    // Example: Send email to admin
    // if (defined('UPDATE_ADMIN_EMAIL') && !empty(UPDATE_ADMIN_EMAIL)) {
    //     mail(UPDATE_ADMIN_EMAIL, $subject, json_encode($details, JSON_PRETTY_PRINT));
    // }

    // Example: Call webhook
    // callWebhook($subject, $details);
}

exit(0);
