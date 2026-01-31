# SN/P — URL Shortener

A minimal and elegant URL shortener built with PHP, MariaDB, HTML, CSS, and JavaScript.

## Features

- 🔗 **Fast URL shortening** — Shorten long URLs with one click
- ✨ **Custom short codes** — Choose your own short code (optional)
- 📊 **Statistics** — View click counts and other details
- 📱 **QR codes** — Generate QR codes for any short URL
- 🔒 **Admin panel** — Password-protected management
- 📱 **Responsive design** — Works on all devices

## Requirements

- PHP 7.4+
- MariaDB 10.3+ or MySQL 5.7+
- Apache with mod_rewrite (or nginx)
- PDO PHP extension

## Installation

### Option 1: Automatic (recommended)

1. Upload files to your web server
2. Open `https://your-domain.com/install.php`
3. Follow the wizard
4. **Delete `install.php`** after installation

### Option 2: Manual

1. Import `database/schema.sql` into MariaDB/MySQL
2. Edit `api/config.php` with your settings
3. Change admin password in `api/admin.php`

## Admin Panel

**URL:** `https://your-domain.com/admin.html`
**Default password:** `admin123`

⚠️ **Change the password immediately after installation!**

Features: Shorten URLs, view all links, statistics, edit, delete, QR codes

## Custom Short Codes

Allowed: `a-z`, `A-Z`, `0-9`, `_`, `-`
Length: 1-100 characters

## API

**Shorten URL:**
```
POST /api/shorten.php
{"url": "https://example.com/long-url", "custom_code": "optional"}
```

**Get Stats:**
```
GET /api/stats.php?code=abc123
```

## Migrating from YOURLS

A migration script is included. See `migrate_yourls.php` for instructions.

## License

MIT License
