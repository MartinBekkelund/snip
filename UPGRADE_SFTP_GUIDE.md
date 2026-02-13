# Quick Upgrade Guide: v1.0.0 → v1.0.3 (SFTP-Only Access)

**Status:** Ready to upgrade
**Duration:** 2-3 days (mostly waiting for file transfers)
**Downtime:** ~10 minutes (maintenance window)
**Access Method:** SFTP only (no shell/SSH access)

---

## 📋 Quick Checklist

- [ ] **Day 1:** Backup current installation
- [ ] **Day 1:** Download v1.0.3 files
- [ ] **Day 1-2:** Upload files via SFTP
- [ ] **Day 2:** Run database schema
- [ ] **Day 2:** Verify installation works
- [ ] **Day 2-3:** Configure cron jobs
- [ ] **Day 3+:** Test and monitor

---

## 🚀 Step-by-Step Process

### Step 1: Backup Everything

**Backup Files (SFTP):**
1. Open WinSCP, FileZilla, Cyberduck, or similar SFTP client
2. Connect to your server
3. Navigate to your snip installation
4. Right-click the entire `snip` folder → **Download**
5. Save as: `snip-v1.0.0-backup-2025-01-15.zip`
6. Keep in safe location (cloud storage recommended)

**Backup Database:**
1. Log in to cPanel → PHP MyAdmin (or direct phpMyAdmin)
2. Select your database
3. Click "Export"
4. Click "Go"
5. Save file: `snip-database-backup-2025-01-15.sql`
6. Keep in same safe location

---

### Step 2: Download v1.0.3 Files

1. Go to: https://github.com/MartinBekkelund/snip/releases
2. Find v1.0.3 release
3. Click "Download" (ZIP file)
4. Extract to your computer
5. Have ready for uploading

---

### Step 3: Create Required Directories (SFTP)

1. Connect via SFTP
2. Navigate to your snip folder
3. Create these folders:
   - `backup/`
   - `storage/logs/`
   - `storage/cache/`

**Steps:**
- Right-click in snip directory
- "Create Folder" → name it `backup`
- Create `storage/` folder
- Inside `storage/`, create `logs/` and `cache/`

---

### Step 4: Upload New Files (SFTP)

**Upload to `/api/` folder:**
```
Updater.php
VersionChecker.php
BackupManager.php
MaintenanceMode.php
scheduler.php
admin-updates.php
```

**Upload to `/database/` folder:**
```
updates_tables.sql
```

**Upload to root `/` folder:**
```
maintenance.html
AUTOMATED_UPDATES.md
SETUP_CRON.md
IMPLEMENTATION_COMPLETE.md
```

**Steps:**
- Open SFTP client
- Navigate to snip folder
- Drag and drop files from your computer to server
- Wait for upload complete

---

### Step 5: Update Existing Files (SFTP)

**Delete and replace admin.html:**
1. Download current `admin.html` as backup (if desired)
2. Delete current `admin.html`
3. Upload new v1.0.3 `admin.html`

**Update api/config.php:**
1. Download `api/config.php`
2. Open in text editor
3. Add at end of file (before final `?>`):
```php
// AUTO-UPDATE CONFIGURATION
define('SNIP_VERSION', '1.0.3');
define('AUTO_UPDATE_ENABLED', true);
define('AUTO_UPDATE_AUTO_INSTALL', true);
define('GITHUB_REPO_OWNER', 'MartinBekkelund');
define('GITHUB_REPO_NAME', 'snip');
// ... see IMPLEMENTATION_COMPLETE.md for all constants
```
4. Save and upload back via SFTP
5. Delete old version

---

### Step 6: Create Database Tables

1. Log in to phpMyAdmin
2. Select your database
3. Click "SQL" tab
4. Open `database/updates_tables.sql` in text editor
5. Copy entire contents
6. Paste into SQL query box in phpMyAdmin
7. Click "Go"
8. Should see "Query successful" messages

**Verify Tables Created:**
1. In phpMyAdmin, scroll through table list
2. Should see:
   - `update_history`
   - `backup_manifests`
   - `app_settings`
   - `app_migrations`

---

### Step 7: Verify Admin Panel

1. Visit `https://yourdomain.com/admin.html`
2. Log in
3. Look for **"Updates"** in left sidebar
4. Click it
5. Should show:
   - Current Version: 1.0.3
   - Latest Version: (from GitHub)
   - Check Now button

**If "Updates" not showing:**
- Files weren't uploaded correctly
- Go back to Step 4 and verify all files uploaded
- Check SFTP file listing to confirm

---

### Step 8: Configure Cron Jobs

**For cPanel:**
1. Log in to cPanel
2. Find "Cron Jobs"
3. Add Job 1:
   - **Time:** Sunday, 2:00 AM
   - **Command:** `/usr/bin/php /home/username/public_html/snip/api/scheduler.php check`
4. Add Job 2:
   - **Time:** Sunday, 3:00 AM
   - **Command:** `/usr/bin/php /home/username/public_html/snip/api/scheduler.php update`

**For Plesk:**
1. Log in to Plesk
2. Go to Tools & Settings → Scheduled Tasks
3. Add Task 1:
   - **Description:** "SN/P Check Updates"
   - **Run:** `/usr/bin/php /var/www/vhosts/yourdomain.com/snip/api/scheduler.php check`
   - **When:** Sunday 2:00 AM
4. Add Task 2:
   - **Description:** "SN/P Install Updates"
   - **Run:** `/usr/bin/php /var/www/vhosts/yourdomain.com/snip/api/scheduler.php update`
   - **When:** Sunday 3:00 AM

**For Other Hosting:**
- Contact your hosting provider
- Request two cron jobs (give them the commands above)

---

### Step 9: Test Everything

**Via Admin Panel:**
1. Log in to admin.html
2. Click "Updates" → "Check Now"
3. Should complete without errors
4. Check browser console (F12) - no red errors

**Via phpMyAdmin:**
1. Select database
2. Click `update_history` table
3. Should be empty (or show successful check)
4. No error messages

**Via SFTP:**
1. Navigate to `/storage/logs/`
2. Check if files exist:
   - `updates.log` (for update operations)
   - `scheduler.log` (for cron operations)

---

## ✅ Success Indicators

You're done when:
- ✅ Admin panel shows "Updates" in sidebar
- ✅ "Check Now" button works without errors
- ✅ phpMyAdmin shows 4 new tables
- ✅ Cron jobs configured in hosting panel
- ✅ Browser console shows no errors
- ✅ `backup/`, `storage/logs/`, `storage/cache/` folders exist

---

## 🚨 If Something Goes Wrong

### Immediate Fix:
1. Via SFTP, delete `api/Updater.php`
2. System will fall back to old version
3. Try again after reviewing checklist

### Full Rollback:
1. Via SFTP, delete entire `snip/` folder
2. Upload your v1.0.0 backup
3. Via phpMyAdmin, restore database from backup
4. Done - back to v1.0.0

### Get Help:
Gather these files and information:
1. `storage/logs/updates.log` (download via SFTP)
2. `storage/logs/scheduler.log` (download via SFTP)
3. phpMyAdmin screenshot of tables
4. Your hosting provider
5. Error message or issue description

---

## 📚 Important Files to Reference

- **Full Plan:** `/Users/martin/.claude/plans/proud-sleeping-sutherland.md`
- **System Details:** `IMPLEMENTATION_COMPLETE.md`
- **Setup Instructions:** `SETUP_CRON.md`
- **Full Documentation:** `AUTOMATED_UPDATES.md`

---

## 🎯 What Happens Next (Future)

Once v1.0.3 is installed:

1. **Weekly Automatic Updates:** Every Sunday 3 AM, system checks GitHub
2. **Automatic Backups:** Before any update, files and database backed up
3. **Manual Control:** Admin panel lets you check/update anytime
4. **Automatic Cleanup:** Old backups deleted after 90 days
5. **Zero Downtime:** Maintenance mode prevents user access during updates

---

**You're all set! Contact support if you need help.** 🚀
