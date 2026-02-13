# SN/P — URL Shortener

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Version](https://img.shields.io/badge/version-1.0.0-green.svg)](https://github.com/MartinBekkelund/snip/releases)

A minimal and secure URL shortener built with PHP, MariaDB, HTML, CSS, and JavaScript.

## Features

- 🔗 **Fast URL shortening** — Shorten long URLs with one click
- ✨ **Custom short codes** — Choose your own short code (optional)
- 📊 **Statistics** — View click counts and referrer data
- 📱 **QR codes** — Generate QR codes for any short URL
- 🔒 **Admin panel** — Password-protected management with secure authentication
- 🛡️ **Security** — Rate limiting, CSRF protection, secure password hashing
- 🔐 **GDPR compliant** — IP addresses are anonymized by default
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
3. Follow the installation wizard
4. **Delete `install.php`** immediately after installation!

### Option 2: Manual

1. Import `database/schema.sql` into MariaDB/MySQL
2. Copy `api/config.php.example` to `api/config.php` and edit settings
3. Generate a password hash: `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"`
4. Update `ADMIN_PASSWORD_HASH` in `api/admin.php`

## Security

This version includes several security improvements:

- **Password hashing** — Admin passwords are stored using `password_hash()` with bcrypt
- **Secure tokens** — Authentication tokens are cryptographically secure random strings
- **Rate limiting** — Protects against brute-force attacks and DoS
- **IP anonymization** — Last octet of IP addresses is removed (GDPR compliance)
- **Security headers** — X-Frame-Options, X-Content-Type-Options, etc.
- **Secure sessions** — httpOnly, secure, and SameSite cookie flags

### Post-installation checklist

- [ ] Delete `install.php` from server
- [ ] Set `CORS_ORIGIN` to your specific domain in `config.php`
- [ ] Ensure HTTPS is enabled
- [ ] Review rate limit settings

## Admin Panel

**URL:** `https://your-domain.com/admin.html`

Features:
- Shorten URLs
- View all links with statistics
- Edit or delete links
- Generate QR codes
- View referrer statistics

## API

### Shorten URL

```http
POST /api/shorten.php
Content-Type: application/json

{
  "url": "https://example.com/long-url",
  "custom_code": "optional"
}
```

### Get Public Stats

```http
GET /api/stats.php?code=abc123
```

Returns only aggregated statistics (total clicks, creation date). Detailed statistics require admin authentication.

## Migrating from YOURLS

A migration script is included. See `migrate_yourls.php` for instructions.

## Upgrading from Previous Versions

### Option 1: Automatic (recommended)

1. Backup your database and files
2. Upload all new files **except** `api/config.php` and `install.php`
3. Upload `upgrade.php` to your installation folder
4. Open `https://your-domain.com/upgrade.php` in your browser
5. Follow the upgrade wizard
6. **Delete `upgrade.php`** immediately after upgrade!

### Option 2: Manual

1. Backup your database
2. Run `database/upgrade.sql` against your database
3. Manually update `api/config.php` with new constants and functions
4. Generate a password hash: `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"`
5. Update `ADMIN_PASSWORD_HASH` in `api/admin.php`

See the [upgrade guide](https://github.com/MartinBekkelund/snip/wiki/Upgrading) for detailed instructions.

## Custom Short Codes

- Allowed characters: `a-z`, `A-Z`, `0-9`, `_`, `-`
- Length: 1-100 characters
- Reserved codes: `admin`, `api`, `stats`, `login`, `logout`, `install`

## License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

```
Snip - URL Shortener
Copyright (C) 2025 Martin Bekkelund

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
```

## Author

- **Martin Bekkelund** - [GitHub](https://github.com/MartinBekkelund)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
