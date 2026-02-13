# Upgrade Checklist: v1.0.0 → v1.0.3

Print this page and check off items as you complete them.

---

## 📅 DAY 1: Backup & Prepare

### Morning - Create Backups

**Files Backup (SFTP):**
- [ ] Open SFTP client (WinSCP/FileZilla/Cyberduck)
- [ ] Connect to server
- [ ] Navigate to snip installation
- [ ] Download entire `snip` folder
- [ ] Save as: `snip-v1.0.0-backup-[DATE].zip`
- [ ] Store in safe location (cloud storage)

**Database Backup (phpMyAdmin):**
- [ ] Log in to phpMyAdmin
- [ ] Select your database
- [ ] Click "Export"
- [ ] Save as: `snip-database-[DATE].sql`
- [ ] Store in same safe location

### Afternoon - Get Files Ready

**Download v1.0.3:**
- [ ] Go to GitHub: https://github.com/MartinBekkelund/snip/releases
- [ ] Find v1.0.3 release
- [ ] Download ZIP file
- [ ] Extract to your computer
- [ ] Keep extracted folder accessible

**Prepare Directories:**
- [ ] Via SFTP, create `backup/` folder
- [ ] Via SFTP, create `storage/` folder
- [ ] Via SFTP, create `storage/logs/` subfolder
- [ ] Via SFTP, create `storage/cache/` subfolder

---

## 📤 DAY 2: Upload & Configure

### Morning - Upload Files

**Upload New Files to /api/:**
- [ ] Updater.php
- [ ] VersionChecker.php
- [ ] BackupManager.php
- [ ] MaintenanceMode.php
- [ ] scheduler.php
- [ ] admin-updates.php

**Upload New Files to /database/:**
- [ ] updates_tables.sql

**Upload New Files to Root:**
- [ ] maintenance.html
- [ ] AUTOMATED_UPDATES.md
- [ ] SETUP_CRON.md
- [ ] IMPLEMENTATION_COMPLETE.md

**Replace Existing Files:**
- [ ] Delete old `admin.html`
- [ ] Upload new `admin.html`

### Afternoon - Update Configuration

**Update api/config.php:**
- [ ] Download `api/config.php`
- [ ] Open in text editor
- [ ] Find the GITHUB constants section
- [ ] Verify or add:
  - `SNIP_VERSION = '1.0.3'`
  - `AUTO_UPDATE_ENABLED = true`
  - `AUTO_UPDATE_AUTO_INSTALL = true`
  - `GITHUB_REPO_OWNER = 'MartinBekkelund'`
  - `GITHUB_REPO_NAME = 'snip'`
- [ ] Save file
- [ ] Delete old `api/config.php` from server
- [ ] Upload updated `api/config.php`

### Evening - Create Database Tables

**Run SQL Schema:**
- [ ] Log in to phpMyAdmin
- [ ] Select your database
- [ ] Click "SQL" tab
- [ ] Open `database/updates_tables.sql` locally
- [ ] Copy entire SQL content
- [ ] Paste into phpMyAdmin query box
- [ ] Click "Go"
- [ ] Verify success message

**Verify Tables Created:**
- [ ] Refresh table list in phpMyAdmin
- [ ] Check for: `update_history` ✅
- [ ] Check for: `backup_manifests` ✅
- [ ] Check for: `app_migrations` ✅
- [ ] Check for: `app_settings` ✅

---

## ✅ DAY 2/3: Verification

### Test Admin Panel

**Access Application:**
- [ ] Visit: `https://yourdomain.com/admin.html`
- [ ] Log in with admin credentials
- [ ] Should load normally without errors

**Check for Updates Section:**
- [ ] Look in left sidebar
- [ ] Should see **"Updates"** button
- [ ] Click on it

**Verify Update UI:**
- [ ] Current Version: should show **1.0.3**
- [ ] Latest Version: should show GitHub version
- [ ] System Updates section visible
- [ ] Backups section visible
- [ ] Update History section visible
- [ ] "Check Now" button present
- [ ] No error messages in browser (F12)

### Create Test Directories (SFTP)

**Verify Directory Structure:**
- [ ] Via SFTP, check `/backup/` folder exists
- [ ] Via SFTP, check `/storage/logs/` folder exists
- [ ] Via SFTP, check `/storage/cache/` folder exists
- [ ] All folders should be empty

### Test Version Check

**Via Admin Panel:**
- [ ] Click "Updates" → "Check Now"
- [ ] Should complete in <10 seconds
- [ ] Should show version numbers
- [ ] No error messages
- [ ] Browser console (F12) has no red errors

**Via phpMyAdmin:**
- [ ] Select database
- [ ] Click `update_history` table
- [ ] Browse rows (if any entries)
- [ ] Should show successful operations

---

## ⏰ DAY 3: Configure Automation

### Set Up Cron Jobs

**Choose Your Hosting Type:**

#### If cPanel:
- [ ] Log in to cPanel
- [ ] Find "Cron Jobs" (Advanced section)
- [ ] Add New Cron Job #1:
  - [ ] Common Settings: "Once per week"
  - [ ] Specific time: **Sunday, 02:00** (2 AM)
  - [ ] Command: `/usr/bin/php /home/username/public_html/snip/api/scheduler.php check`
  - [ ] Click "Add New Cron Job"
- [ ] Add New Cron Job #2:
  - [ ] Common Settings: "Once per week"
  - [ ] Specific time: **Sunday, 03:00** (3 AM)
  - [ ] Command: `/usr/bin/php /home/username/public_html/snip/api/scheduler.php update`
  - [ ] Click "Add New Cron Job"

#### If Plesk:
- [ ] Log in to Plesk
- [ ] Go to Tools & Settings → Scheduled Tasks
- [ ] Click "Add Task" for Task #1:
  - [ ] Description: "SN/P Check Updates"
  - [ ] Run: `/usr/bin/php /var/www/vhosts/yourdomain.com/snip/api/scheduler.php check`
  - [ ] When: **Sunday, 02:00** (2 AM)
  - [ ] Click "Add"
- [ ] Click "Add Task" for Task #2:
  - [ ] Description: "SN/P Install Updates"
  - [ ] Run: `/usr/bin/php /var/www/vhosts/yourdomain.com/snip/api/scheduler.php update`
  - [ ] When: **Sunday, 03:00** (3 AM)
  - [ ] Click "Add"

#### If Other Hosting:
- [ ] Contact hosting provider
- [ ] Request 2 cron jobs added:
  - Job 1: Check command at Sunday 2 AM
  - Job 2: Update command at Sunday 3 AM

### Optional: Configure Email Notifications

**Update api/config.php:**
- [ ] Download `api/config.php`
- [ ] Add this line:
  ```php
  define('UPDATE_ADMIN_EMAIL', 'your-email@example.com');
  ```
- [ ] Save and upload back

**Update api/scheduler.php:**
- [ ] Download `api/scheduler.php`
- [ ] Find `notifyAdmin()` function
- [ ] Implement mail() call
- [ ] Save and upload back

**Test Email:**
- [ ] Wait for first cron run (Sunday 2 AM), or
- [ ] Check inbox for test message

---

## 🧪 DAY 3-5: Testing & Monitoring

### Functional Tests

**Manual Update Check:**
- [ ] Log in to admin panel
- [ ] Click "Updates" → "Check Now"
- [ ] Should complete successfully
- [ ] Version numbers should display
- [ ] No error messages

**Check Logs (SFTP):**
- [ ] Download `storage/logs/updates.log`
- [ ] Open in text editor
- [ ] Look for error messages
- [ ] Note any warnings
- [ ] Should show timestamps

**Database Verification:**
- [ ] Log in to phpMyAdmin
- [ ] Click `update_history` table
- [ ] Browse data
- [ ] Check for successful records
- [ ] Check `app_settings` table

### Security Tests

**CSRF Protection:**
- [ ] Open browser console (F12)
- [ ] Try fetching without CSRF token
- [ ] Should return error ✅
- [ ] Indicates security working

**Authentication:**
- [ ] Log out from admin panel
- [ ] Try accessing: `yourdomain.com/api/admin-updates.php?action=check-updates`
- [ ] Should not show data ✅
- [ ] Should redirect or show error ✅

**Backup Directory Protection:**
- [ ] Try accessing: `yourdomain.com/backup/`
- [ ] Should show "Forbidden" or 403 ✅
- [ ] Should NOT show directory listing ✅

### Performance Tests

**Speed Checks:**
- [ ] "Check Now" completes in **<10 seconds** ✅
- [ ] Admin panel loads normally
- [ ] No timeouts or delays
- [ ] Network requests complete quickly

### Monitor First Cron Run

**After Sunday 3 AM (if time has passed):**
- [ ] Check admin panel → Updates
- [ ] Look at "Last Checked" time
- [ ] Should show recent timestamp
- [ ] Check `update_history` table in phpMyAdmin
- [ ] Should have new entry
- [ ] Status should be "success" or "pending"

---

## 📋 Final Verification

### Pre-Production Checklist

- [ ] All files uploaded to server
- [ ] Database tables created
- [ ] admin.html updated with Updates section
- [ ] config.php updated with version
- [ ] Backup directories created
- [ ] Cron jobs configured
- [ ] Update panel shows no errors
- [ ] Browser console clear of errors
- [ ] "Check Now" button works
- [ ] Database shows tables
- [ ] Email notifications working (if configured)

### Go-Live Readiness

- [ ] All testing passed ✅
- [ ] Backups stored safely
- [ ] Rollback plan documented
- [ ] Admin team trained
- [ ] Monitoring setup complete
- [ ] Support contacts ready

---

## 🎉 Success Criteria

Check these final items:

- [ ] ✅ Admin panel shows "Updates" in sidebar
- [ ] ✅ Current Version displays as 1.0.3
- [ ] ✅ Latest Version fetches from GitHub
- [ ] ✅ Check Now button functions
- [ ] ✅ No JavaScript errors in console
- [ ] ✅ Database tables exist and are accessible
- [ ] ✅ Cron jobs configured in hosting panel
- [ ] ✅ Log files can be downloaded via SFTP
- [ ] ✅ Backup directories exist
- [ ] ✅ Site functions normally

---

## 📞 Troubleshooting

If anything fails, refer to:
1. **UPGRADE_SFTP_GUIDE.md** - Common issues section
2. **Diagnostic Checklist** - Step through verification
3. **Rollback Procedure** - Restore from backup
4. **Contact Hosting Provider** - If cron issues

---

**Date Started:** _______________
**Date Completed:** _______________
**Notes:**
```
_______________________________________________________
_______________________________________________________
_______________________________________________________
_______________________________________________________
```

---

**Print this checklist and keep for your records!**
