# 🚀 Upgrade Guide Index

**Welcome!** This is your central guide for upgrading from **v1.0.0 → v1.0.3** with the new **Automated Update System**.

Since you have **SFTP-only access** (no shell/SSH), all instructions are tailored for using SFTP clients like WinSCP, FileZilla, or Cyberduck.

---

## 📚 Documentation Files

### Quick Start (Read This First!)
**📄 [UPGRADE_SFTP_GUIDE.md](UPGRADE_SFTP_GUIDE.md)** (7 KB)
- ⏱️ 5-10 minute read
- 🎯 Step-by-step instructions
- ✅ All SFTP-based (no shell access)
- 📋 Includes quick checklist
- Perfect for getting started quickly

### Printable Checklist
**📋 [UPGRADE_CHECKLIST.md](UPGRADE_CHECKLIST.md)** (9 KB)
- ✅ Print-friendly format
- ☑️ Check off items as you complete them
- 📅 Organized by day (Day 1, Day 2, etc.)
- 🎯 Success criteria included
- Great for tracking progress

### Detailed Plan
**📋 [Upgrade Plan](../.claude/plans/proud-sleeping-sutherland.md)** (20+ KB)
- 🔍 Comprehensive 7-phase plan
- 🛠️ Every detail covered
- 📖 Reference for questions
- 🆘 Troubleshooting section
- Located in `.claude/plans/` folder

### System Documentation
**📄 [AUTOMATED_UPDATES.md](AUTOMATED_UPDATES.md)** (12 KB)
- 📖 Full system overview
- 🏗️ Architecture details
- 🔧 Configuration reference
- 🐛 Troubleshooting guide
- For understanding how the system works

**📄 [IMPLEMENTATION_COMPLETE.md](IMPLEMENTATION_COMPLETE.md)** (13 KB)
- ✨ What's new in v1.0.3
- 📂 File structure reference
- 🔐 Security features
- 📋 Installation checklist
- Complete implementation details

### Cron Setup Guide
**📄 [SETUP_CRON.md](SETUP_CRON.md)** (6.4 KB)
- ⏰ Cron job configuration
- 🏢 Hosting-specific instructions
- 📋 Examples for cPanel, Plesk, Linux
- 🆘 Common issues
- For setting up automated updates

---

## 🎯 Where to Start

### If you want to upgrade RIGHT NOW:
👉 **Start with [UPGRADE_SFTP_GUIDE.md](UPGRADE_SFTP_GUIDE.md)**
- It's quick and SFTP-focused
- Gives you the 9-step process
- References other docs as needed

### If you want to be THOROUGH:
👉 **Start with this README, then read [UPGRADE_CHECKLIST.md](UPGRADE_CHECKLIST.md)**
- Print the checklist
- Follow day-by-day
- Check off each item
- Reference detailed plan if questions arise

### If you want COMPLETE DETAILS:
👉 **Read [Upgrade Plan](../.claude/plans/proud-sleeping-sutherland.md)**
- 7 detailed phases
- Every consideration covered
- Rollback procedures
- Troubleshooting guide

---

## ⚡ TL;DR - 30 Second Version

1. **Backup** everything (files + database)
2. **Upload** 11 new PHP files via SFTP
3. **Update** admin.html and config.php
4. **Run** database schema in phpMyAdmin
5. **Verify** admin panel shows Updates section
6. **Configure** 2 cron jobs in hosting panel
7. **Done!** Site automatically updates weekly

---

## 📊 What You're Getting

### New Files (11 total)
```
api/
  ├── Updater.php              (500+ lines - main orchestrator)
  ├── VersionChecker.php       (200+ lines - GitHub integration)
  ├── BackupManager.php        (400+ lines - backup/restore)
  ├── MaintenanceMode.php      (150+ lines - maintenance mode)
  ├── scheduler.php            (100+ lines - cron entry point)
  └── admin-updates.php        (300+ lines - REST API)
database/
  └── updates_tables.sql       (database schema)
/
  ├── maintenance.html         (maintenance page)
  ├── AUTOMATED_UPDATES.md     (documentation)
  ├── SETUP_CRON.md            (cron setup guide)
  └── IMPLEMENTATION_COMPLETE.md (implementation details)
```

### New Database Tables (4 total)
- `update_history` - tracks all updates
- `backup_manifests` - backup metadata
- `app_migrations` - database migrations
- `app_settings` - app configuration

### New Features
- ✅ Weekly automatic version checks
- ✅ Automatic backups before updates
- ✅ One-click update button
- ✅ Backup management (list, restore, delete)
- ✅ Update history tracking
- ✅ Maintenance mode during updates
- ✅ Automatic update rollback on failure

---

## 🕐 Time Estimate

| Phase | Task | Time | When |
|-------|------|------|------|
| 1 | Read this guide | 5 min | Now |
| 2 | Backup (SFTP + phpMyAdmin) | 15 min | Day 1 |
| 3 | Upload files (SFTP) | 20 min | Day 1-2 |
| 4 | Update config & database | 15 min | Day 2 |
| 5 | Verify installation | 10 min | Day 2 |
| 6 | Configure cron jobs | 10 min | Day 2-3 |
| 7 | Test everything | 20 min | Day 3 |
| **Total** | **From start to complete** | **≈90 min** | **2-3 days** |

---

## ✅ Pre-Upgrade Checklist

Before you start, verify you have:

- [ ] Access to SFTP client (WinSCP, FileZilla, Cyberduck, etc.)
- [ ] SFTP credentials for your server
- [ ] Access to cPanel or Plesk admin panel
- [ ] Access to phpMyAdmin
- [ ] v1.0.3 release downloaded
- [ ] Backup location (cloud storage/external drive)
- [ ] 2-3 hours available
- [ ] Low-traffic time scheduled (for maintenance window)

---

## 🚨 Important Notes

### About SFTP-Only Access
✅ **Good news:** You can do the entire upgrade via SFTP!
- All file uploads via SFTP client
- Database via phpMyAdmin
- Cron jobs via hosting panel (cPanel/Plesk)
- ❌ No shell/SSH access needed

### First Update is Manual
⚠️ This v1.0.0→v1.0.3 upgrade must be done manually (it's the bootstrapping step).
After v1.0.3 is installed, **all future updates are automatic** (you just click "Update Now" in admin panel).

### Downtime
⏱️ You'll need ~10 minutes of maintenance window when:
- Uploading new files
- Running database schema
- The update system is being activated

---

## 🆘 Need Help?

### Quick Issues
1. Check the troubleshooting section in [UPGRADE_SFTP_GUIDE.md](UPGRADE_SFTP_GUIDE.md)
2. Look at the diagnostic checklist
3. Review [AUTOMATED_UPDATES.md](AUTOMATED_UPDATES.md) troubleshooting

### Complex Issues
1. Gather these files:
   - `storage/logs/updates.log` (via SFTP)
   - `storage/logs/scheduler.log` (via SFTP)
   - phpMyAdmin screenshot showing tables
2. Document what you tried
3. Contact your hosting provider with details

### Rollback Plan
If anything goes wrong:
1. Delete current `snip` folder via SFTP
2. Upload your v1.0.0 backup
3. Restore database from backup
4. You're back to v1.0.0 safely

---

## 📖 File Reference

| File | Purpose | Read Time | When |
|------|---------|-----------|------|
| **This file** | Overview & navigation | 5 min | First |
| UPGRADE_SFTP_GUIDE.md | Quick step-by-step | 10 min | Before upgrade |
| UPGRADE_CHECKLIST.md | Printable checklist | Print & use | During upgrade |
| Upgrade Plan | Complete details | 20 min | Reference |
| AUTOMATED_UPDATES.md | System documentation | 15 min | Questions |
| SETUP_CRON.md | Cron configuration | 10 min | Day 2-3 |

---

## 🎯 Success Criteria

You'll know the upgrade worked when:

- ✅ Admin panel loads normally
- ✅ **"Updates"** appears in left sidebar
- ✅ Current Version shows: **1.0.3**
- ✅ Latest Version fetches from GitHub
- ✅ "Check Now" button works
- ✅ Browser console (F12) shows no red errors
- ✅ phpMyAdmin shows 4 new database tables
- ✅ Cron jobs configured in hosting panel
- ✅ Backups directory created
- ✅ Logs directory created

---

## 🚀 Next Steps

1. **Right now:** Read [UPGRADE_SFTP_GUIDE.md](UPGRADE_SFTP_GUIDE.md) (7 KB, 5-10 min)
2. **Then:** Print [UPGRADE_CHECKLIST.md](UPGRADE_CHECKLIST.md)
3. **Then:** Follow the steps using SFTP
4. **Questions?** Reference [Upgrade Plan](../.claude/plans/proud-sleeping-sutherland.md)
5. **Done!** Celebrate! 🎉

---

## 📞 Quick Reference

**Essential Links:**
- GitHub Repository: https://github.com/MartinBekkelund/snip
- v1.0.3 Release: https://github.com/MartinBekkelund/snip/releases/tag/v1.0.3

**Your Tools:**
- SFTP Client: WinSCP, FileZilla, or Cyberduck
- Database: phpMyAdmin
- Cron Panel: cPanel or Plesk

**Documentation:**
- Quick Start: [UPGRADE_SFTP_GUIDE.md](UPGRADE_SFTP_GUIDE.md)
- Checklist: [UPGRADE_CHECKLIST.md](UPGRADE_CHECKLIST.md)
- Full Plan: `.claude/plans/proud-sleeping-sutherland.md`

---

**Happy upgrading! 🚀**

*Last updated: 2025-02-13*
*Version: v1.0.0 → v1.0.3*
