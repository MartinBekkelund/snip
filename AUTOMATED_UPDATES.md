# Automated Update System - Implementation Guide

This document describes the automated update system for SN/P URL Shortener v1.0.3+.

## Overview

The automated update system provides:
- **Weekly automatic version checks** against GitHub releases
- **Automatic backups** (database + files) before each update
- **Zero-downtime updates** using maintenance mode (<3 minutes)
- **Atomic update process** with automatic rollback on failure
- **Manual update controls** through admin panel
- **Backup management** (restore, delete, list)
- **Update history tracking** for auditing

Works on both **shared hosting** and **dedicated servers**.

## Architecture

### Core Components

#### 1. **Updater.php** (`api/Updater.php`)
Main orchestrator class that coordinates the entire update process.

**Key Methods:**
- `checkForUpdates()` - Checks for available updates from GitHub
- `update($targetVersion, $createBackup)` - Performs the update
- `validateEnvironment()` - Verifies system requirements
- `rollback()` - Restores from backup on failure
- `getUpdateHistory($limit)` - Retrieves update history

**Process Flow (10 steps):**
1. Validate system environment
2. Create backup (if enabled)
3. Enable maintenance mode
4. Download release from GitHub
5. Verify SHA256 hash
6. Extract files to temp directory
7. Run database migrations
8. Verify integrity
9. Clean up temporary files
10. Disable maintenance mode

#### 2. **VersionChecker.php** (`api/VersionChecker.php`)
Handles GitHub Releases API integration for fetching version information.

**Features:**
- Caches version info for 1 hour to reduce API calls
- Extracts SHA256 hashes from release notes or checksum files
- Timeout handling with fallback to cached data
- Validates GitHub API responses

#### 3. **BackupManager.php** (`api/BackupManager.php`)
Complete backup and restore functionality.

**Features:**
- Timestamped backup directories: `backup/YYYY-MM-DD_HHMMSS/`
- Backs up files as `files.zip` and database as `database.sql`
- Creates `manifest.json` with metadata and checksums
- Backup verification before marking as valid
- Enforces retention policy (10 backups, 90 days max)
- Restores in correct order: database first (with transactions), then files
- Web-inaccessible backups (`.htaccess` with `Deny from all`)

#### 4. **MaintenanceMode.php** (`api/MaintenanceMode.php`)
Manages maintenance mode during updates.

**Features:**
- Stores state in `.maintenance` flag file as JSON
- 10-minute safety timeout to auto-disable if stuck
- Allows admin users to bypass for access
- Returns status for maintenance page

#### 5. **scheduler.php** (`api/scheduler.php`)
Cron entry point for automated update checks and installation.

**Usage:**
```bash
php api/scheduler.php check    # Check for updates only
php api/scheduler.php update   # Check and install if available
php api/scheduler.php backup   # Create backup only
```

**Cron Examples:**
```bash
# Check for updates every Sunday at 2 AM (UTC)
0 2 * * 0 /usr/bin/php /path/to/snip/api/scheduler.php check

# Auto-install updates every Sunday at 3 AM (UTC)
0 3 * * 0 /usr/bin/php /path/to/snip/api/scheduler.php update

# Create backup every week
0 1 * * 0 /usr/bin/php /path/to/snip/api/scheduler.php backup
```

### API Endpoints

All endpoints in `api/admin-updates.php`:

#### Check for Updates
```
GET /api/admin-updates.php?action=check-updates
```
**Response:**
```json
{
  "success": true,
  "current_version": "1.0.3",
  "latest_version": "1.0.4",
  "update_available": true,
  "release_notes": "..."
}
```

#### Perform Update
```
POST /api/admin-updates.php?action=update
Content-Type: application/json
X-CSRF-Token: token

{
  "version": "1.0.4"
}
```

#### List Backups
```
GET /api/admin-updates.php?action=backups-list
```
**Response:**
```json
{
  "success": true,
  "backups": [
    {
      "backup_id": "2025-01-15_120000",
      "version": "1.0.3",
      "created_at": "2025-01-15 12:00:00",
      "size_bytes": 5242880,
      "is_valid": true
    }
  ]
}
```

#### Restore Backup
```
POST /api/admin-updates.php?action=backup-restore
Content-Type: application/json
X-CSRF-Token: token

{
  "backup_id": "2025-01-15_120000"
}
```

#### Delete Backup
```
DELETE /api/admin-updates.php?action=backup-delete
Content-Type: application/json
X-CSRF-Token: token

{
  "backup_id": "2025-01-15_120000"
}
```

#### Get Update History
```
GET /api/admin-updates.php?action=update-history&limit=10
```

#### Maintenance Status
```
GET /api/admin-updates.php?action=maintenance-status
```

## Admin Panel Integration

### UI Components

#### Navigation
- Added "Updates" button to sidebar navigation
- Clicking switches to the Updates view

#### Update Status Card
Displays:
- Current version
- Latest available version
- Last checked timestamp
- Current status (Up to Date / Update Available)

#### System Updates Section
- **Check Now** button - Manually check for updates
- **Update Now** button - Trigger manual update (hidden until update available)
- Release notes display
- Update progress indicator

#### Backups Section
Displays all available backups with:
- Version number
- Creation date
- Backup size
- Validity status
- Restore button
- Delete button

#### Update History Section
Displays last 10 updates with:
- Version updated to
- Date of update
- Status (success/failed/rolled_back)
- Duration in seconds
- Error message if failed

### JavaScript Functions

#### View Switching
```javascript
switchView(viewName) // 'dashboard' or 'updates'
```

#### Update Operations
```javascript
checkForUpdates()     // Check for available updates
performUpdate()       // Trigger update with confirmation
loadUpdateStatus()    // Load and display update status
loadBackups()         // Load backup list
loadUpdateHistory()   // Load update history

// Backup Operations
restoreBackup(backupId, version)  // Restore from backup
deleteBackup(backupId, version)   // Delete backup
```

## Configuration

### Required Constants (`api/config.php`)

```php
define('AUTO_UPDATE_ENABLED', true);
define('AUTO_UPDATE_CHECK_FREQUENCY', 'weekly'); // 'weekly'|'monthly'|'manual'
define('AUTO_UPDATE_DAY', 'Sunday');
define('AUTO_UPDATE_HOUR', 3); // UTC hour
define('AUTO_UPDATE_AUTO_INSTALL', true);

define('BACKUP_RETENTION_COUNT', 10);
define('BACKUP_MAX_AGE_DAYS', 90);
define('BACKUP_COMPRESS_DATABASE', true);

define('GITHUB_REPO_OWNER', 'MartinBekkelund');
define('GITHUB_REPO_NAME', 'snip');
define('GITHUB_API_TIMEOUT', 10);

define('UPDATE_TIMEOUT_MINUTES', 10);
define('MAINTENANCE_MODE_TIMEOUT', 600); // 10 minutes
define('UPDATE_ADMIN_EMAIL', ''); // Optional notification email
```

## Database Schema

### update_history
Tracks all update attempts:
- `id` - Primary key
- `old_version` - Version before update
- `new_version` - Target version
- `status` - pending|in_progress|success|failed|rolled_back
- `duration_seconds` - How long the update took
- `error_message` - Error details if failed
- `backup_id` - Reference to backup created
- `updated_at` - Timestamp

### backup_manifests
Stores backup metadata:
- `id` - Primary key
- `backup_id` - Directory name (YYYY-MM-DD_HHMMSS)
- `version` - App version being backed up
- `file_count` - Number of files in backup
- `total_size_bytes` - Backup size
- `is_valid` - Whether backup passed verification
- `expires_at` - When backup should be deleted
- `created_at` - Creation timestamp

### app_migrations
Tracks applied database migrations:
- `id` - Primary key
- `migration_name` - Name of migration file
- `applied_at` - When applied

### app_settings
Stores application configuration:
- `setting_key` - Configuration key
- `setting_value` - Configuration value

## File Structure

```
snip/
├── api/
│   ├── admin-updates.php      # API endpoints
│   ├── Updater.php            # Main update orchestrator
│   ├── VersionChecker.php     # GitHub API integration
│   ├── BackupManager.php      # Backup/restore functionality
│   ├── MaintenanceMode.php    # Maintenance mode management
│   ├── scheduler.php          # Cron entry point
│   └── config.php             # Configuration (updated)
├── database/
│   └── updates_tables.sql     # Database schema
├── maintenance.html           # Maintenance page shown during updates
├── backup/                    # Backup directory (created automatically)
│   └── YYYY-MM-DD_HHMMSS/    # Individual backup folders
│       ├── files.zip         # File backup
│       ├── database.sql      # Database dump
│       ├── manifest.json     # Metadata
│       └── .htaccess         # Web access protection
├── storage/
│   └── logs/
│       ├── updates.log       # Update operation logs
│       └── scheduler.log     # Scheduler operation logs
└── admin.html                # Admin panel (updated with UI)
```

## Setup Instructions

### 1. Create Database Tables
Run the SQL in `database/updates_tables.sql`:
```bash
mysql -u user -p database < database/updates_tables.sql
```

Or execute in your database client.

### 2. Create Backup Directory
```bash
mkdir -p storage/backups
chmod 755 storage/backups
```

### 3. Set Configuration
Edit `api/config.php` and set:
- `GITHUB_REPO_OWNER` - Your GitHub username
- `GITHUB_REPO_NAME` - Your repository name
- Backup retention settings
- Schedule preferences

### 4. Set Up Cron (Optional but Recommended)
```bash
# Edit crontab
crontab -e

# Add these lines
0 2 * * 0 /usr/bin/php /path/to/snip/api/scheduler.php check
0 3 * * 0 /usr/bin/php /path/to/snip/api/scheduler.php update

# Or for shared hosting, use a web-based cron service to call:
# https://yourdomain.com/api/scheduler.php?check
```

### 5. (Optional) Set Up Email Notifications
In `api/scheduler.php`, implement the `notifyAdmin()` function to send emails on update completion.

## Security Features

- **CSRF Protection** - All state-changing operations require CSRF tokens
- **Authentication** - Only authenticated admins can trigger updates
- **Backup Verification** - Checksums verify backup integrity
- **Atomic Updates** - Updates succeed completely or roll back fully
- **Rollback on Failure** - Automatic restoration on any error
- **Maintenance Mode** - Prevents user access during updates
- **File Exclusions** - Protects `config.php`, backups, and storage from overwrite

## Troubleshooting

### Update Stuck in Maintenance Mode
The safety timeout (10 minutes) will automatically disable maintenance mode. Or manually delete `.maintenance` file.

### Backup Space Issues
The system automatically enforces:
- Keep only 10 most recent backups
- Delete backups older than 90 days
Adjust `BACKUP_RETENTION_COUNT` and `BACKUP_MAX_AGE_DAYS`.

### GitHub API Rate Limiting
Version checks are cached for 1 hour to avoid rate limits (60 requests/hour). Cron jobs use their own check, not API.

### Logging
Check these files for detailed information:
- `storage/logs/updates.log` - Update operations
- `storage/logs/scheduler.log` - Cron executions
- Database `update_history` table

## Best Practices

1. **Enable Auto-Install** - Set `AUTO_UPDATE_AUTO_INSTALL` to true for hands-off updates
2. **Schedule Maintenance** - Run updates during low-traffic times
3. **Monitor Backups** - Regularly verify backups are being created
4. **Test First** - Try manual updates before enabling auto-install
5. **Email Notifications** - Implement email alerts for failed updates
6. **Regular Backups** - Consider additional backups beyond update backups

## Limitations & Notes

- Updates only work with tagged releases on GitHub
- Backup creation requires disk space equal to your database + files
- Very large databases (>500MB) may timeout on shared hosting
- Custom code modifications will be overwritten by updates
- All configuration should be in `config.php`, not in code files

## Version History

- **v1.0.3** - Initial release with automated updates system

## Support

For issues or questions:
1. Check `storage/logs/updates.log` for details
2. Verify GitHub repository settings
3. Ensure cron jobs are configured correctly
4. Check database tables are created
5. Verify file permissions on backup directory

---

**Created:** 2025-01-15
**Updated:** 2025-01-15
