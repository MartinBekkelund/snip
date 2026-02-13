# Setting Up Automated Update Checks

This guide explains how to configure cron jobs for automatic update checking and installation.

## Quick Start

### For Most Users (Cron)

1. **Access your hosting control panel** (cPanel, Plesk, etc.)
2. **Find "Cron Jobs" or "Scheduled Tasks"**
3. **Add two cron jobs:**

#### Cron Job 1: Weekly Check (Sunday 2 AM UTC)
```
0 2 * * 0 /usr/bin/php /home/username/public_html/snip/api/scheduler.php check
```

#### Cron Job 2: Weekly Install (Sunday 3 AM UTC)
```
0 3 * * 0 /usr/bin/php /home/username/public_html/snip/api/scheduler.php update
```

**Adjust the path** `/home/username/public_html/snip` to match your installation.

---

## Detailed Setup by Hosting Type

### cPanel

1. Log in to cPanel
2. Find **Cron Jobs** under Advanced
3. Under "Add New Cron Job":
   - **Common Settings:** Select "Once per week"
   - **Day:** Sunday
   - **Time:** 02:00 (2 AM) for check, 03:00 (3 AM) for install
   - **Command:**
     ```
     /usr/bin/php /home/username/public_html/snip/api/scheduler.php check
     ```
4. Click **Add New Cron Job**
5. Repeat for the install cron job with time 03:00

### Plesk

1. Log in to Plesk
2. Go to **Tools & Settings** > **Scheduled Tasks**
3. Click **Add Task**
4. Fill in:
   - **Description:** Update Checker
   - **Run:** `/usr/bin/php /var/www/vhosts/yourdomain.com/snip/api/scheduler.php check`
   - **Recurrence:** Weekly, Sunday, 02:00
5. Click **Add**
6. Repeat for install task with time 03:00

### Linux Command Line

Edit your crontab directly:

```bash
crontab -e
```

Add these lines:

```bash
# Weekly update check - Sunday 2 AM UTC
0 2 * * 0 /usr/bin/php /var/www/snip/api/scheduler.php check

# Weekly auto-install - Sunday 3 AM UTC
0 3 * * 0 /usr/bin/php /var/www/snip/api/scheduler.php update

# Weekly backup only - Saturday 1 AM UTC
0 1 * * 6 /usr/bin/php /var/www/snip/api/scheduler.php backup
```

Save and exit.

---

## Alternative: Web-Based Triggers

If your hosting doesn't support cron, use a web cron service:

1. Go to **EasyCron.com**, **Cron-job.org**, or similar
2. Register and create new scheduled tasks
3. Set URL to:
   - Check: `https://yourdomain.com/api/scheduler.php?action=check`
   - Update: `https://yourdomain.com/api/scheduler.php?action=update`
4. Schedule for Sunday 2 AM and 3 AM (or your timezone)

**Note:** This requires your server to be accessible from the web cron service.

---

## Finding Your PHP Path

If you're unsure of the PHP path, try:

```bash
which php
```

Common paths:
- `/usr/bin/php` (Most common)
- `/usr/local/bin/php` (Some shared hosts)
- `/usr/bin/php7.4` or `/usr/bin/php8.0` (Version-specific)

---

## Verifying the Setup

### Check if Cron Ran
1. Check the log file:
   ```bash
   tail -f storage/logs/scheduler.log
   ```

2. Look at the database `update_history` table to see if checks recorded

3. Check when the cron last ran:
   ```
   crontab -l
   ```

### Manual Testing

To test without waiting for cron:

```bash
# Check for updates manually
php /path/to/snip/api/scheduler.php check

# Install update manually
php /path/to/snip/api/scheduler.php update

# Create backup manually
php /path/to/snip/api/scheduler.php backup
```

---

## Customizing Schedule

### More Frequent Checks
```bash
# Daily check at 2 AM
0 2 * * * /usr/bin/php /path/to/snip/api/scheduler.php check

# Weekly install on Sunday 3 AM
0 3 * * 0 /usr/bin/php /path/to/snip/api/scheduler.php update
```

### Different Timezone
By default, times are UTC. To use your timezone:

1. Check your server timezone:
   ```bash
   date
   ```

2. Calculate offset from UTC
3. Adjust cron times accordingly

Example for EST (UTC-5):
- 2 AM UTC = 9 PM EST (previous day)
- Use `0 21 * * 6` for 9 PM Sunday EST

### Conditional Updates
Only install if a specific time condition is met:

```bash
0 3 * * 0 [ $(date +\%H) -eq 03 ] && /usr/bin/php /path/to/snip/api/scheduler.php update
```

---

## Troubleshooting

### Cron Job Not Running

1. **Verify PHP path:**
   ```bash
   /usr/bin/php -v
   ```
   Should show PHP version

2. **Check permissions:**
   ```bash
   chmod 755 /path/to/snip/api/scheduler.php
   ```

3. **Verify file exists:**
   ```bash
   ls -la /path/to/snip/api/scheduler.php
   ```

4. **Check cron logs** (Linux):
   ```bash
   grep CRON /var/log/syslog
   grep CRON /var/log/cron
   ```

### Update Not Installing

1. Check `storage/logs/scheduler.log` for errors
2. Check `storage/logs/updates.log` for update process errors
3. Verify `AUTO_UPDATE_AUTO_INSTALL` is set to true in `config.php`
4. Ensure GitHub repo is accessible

### Email Notifications Not Working

1. Configure email in `api/scheduler.php` `notifyAdmin()` function
2. Set `UPDATE_ADMIN_EMAIL` in `api/config.php`
3. Test email configuration

---

## Cron Expression Reference

Format: `minute hour day month weekday command`

- `*` = any value
- `*/5` = every 5 units
- `0` = at that unit
- `0-5` = range
- `0,12` = specific values

Examples:
```bash
0 2 * * 0        # Sunday 2 AM
0 2 * * *        # Every day 2 AM
*/30 * * * *     # Every 30 minutes
0 0 1 * *        # First day of month at midnight
0 */4 * * *      # Every 4 hours
```

---

## Best Practices

1. **Schedule checks and installs apart** - Check at 2 AM, install at 3 AM
2. **Use UTC times** - Easier to manage across time zones
3. **Schedule during low traffic** - Sunday early morning is safe
4. **Monitor logs** - Regularly check `storage/logs/scheduler.log`
5. **Set up email alerts** - Know when updates complete
6. **Test manually first** - Before relying on cron

---

## Security Considerations

1. **Restrict direct access** - The scheduler.php is designed for cron only:
   ```bash
   # In api/scheduler.php, web access is blocked:
   if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
       http_response_code(403);
       exit(1);
   }
   ```

2. **Use HTTPS for web cron** - If using web-based cron services, ensure HTTPS
3. **Verify update authenticity** - Updates are verified by SHA256 hash
4. **Keep backups** - Automatic backups created before each update
5. **Log all operations** - All activities logged for auditing

---

## Next Steps

1. Set up your cron jobs using the appropriate method for your hosting
2. Manually test: `php api/scheduler.php check`
3. Check logs: `tail storage/logs/scheduler.log`
4. Verify in admin panel: Dashboard → Updates → Check Now
5. Configure email notifications for peace of mind

---

**Need help?** Check the main `AUTOMATED_UPDATES.md` documentation for more details.
