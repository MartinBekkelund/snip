# Security Policy

## Reporting Security Vulnerabilities

If you discover a security vulnerability in Snip, please email security@example.com instead of using the issue tracker. Please do not publicly disclose the vulnerability until it has been fixed.

## Security Features

### Authentication & Authorization

- **Password Hashing:** Admin passwords are hashed using `password_hash()` with bcrypt (PHP's default algorithm)
- **Session Management:** Secure httpOnly, Secure, and SameSite=Strict cookies
- **Token Management:** Session-based authentication with server-side token validation
- **Token Expiry:** Authentication tokens automatically expire after 24 hours (configurable)

### CSRF Protection

- **CSRF Tokens:** All state-changing operations (POST, PUT, DELETE) require valid CSRF tokens
- **Token Validation:** Server validates CSRF tokens for each request
- **Token Rotation:** New CSRF tokens are generated for each session

### CORS & Cross-Origin Security

- **CORS Configuration:** Must be explicitly configured to your domain (not wildcard)
- **Credentials Handling:** API requires httpOnly cookies, not custom headers
- **Same-Origin Enforcement:** Browser enforces same-origin policy for cookie transmission

### Security Headers

The application includes the following security headers:

- `X-Content-Type-Options: nosniff` — Prevents MIME type sniffing
- `X-Frame-Options: SAMEORIGIN` — Prevents clickjacking attacks
- `X-XSS-Protection: 1; mode=block` — XSS protection for older browsers
- `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload` — Forces HTTPS
- `Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; ...` — XSS and injection protection
- `Referrer-Policy: strict-origin-when-cross-origin` — Limits referrer information leakage

### Input Validation

- **URL Validation:** All URLs are validated using `filter_var(FILTER_VALIDATE_URL)`
- **Short Code Validation:** Limited to alphanumeric characters, hyphens, and underscores (3-20 characters)
- **SQL Prepared Statements:** All database queries use parameterized prepared statements (PDO)
- **Output Escaping:** User input is properly escaped in HTML output

### Rate Limiting

- **Login Rate Limit:** 5 attempts per 5 minutes per IP address
- **General Rate Limit:** 10 requests per 60 seconds per IP address (configurable)
- **Admin Operations:** Protected with rate limiting to prevent enumeration

### HTTPS Enforcement

- **Strict-Transport-Security (HSTS):** Enforces HTTPS for 1 year including subdomains
- **HTTPS Redirect:** Can be enabled in `.htaccess` to force HTTPS redirect
- **Secure Cookies:** Cookies are marked as Secure (only sent over HTTPS)

## Security Issues Fixed in v1.0.3

### Critical Fixes

1. **Time-Based Token Bypass** - Replaced predictable date-based tokens with cryptographically secure random tokens
2. **Weak Password Hash** - Added validation to ensure `ADMIN_PASSWORD_HASH` is properly configured
3. **CSRF Protection** - Implemented CSRF token validation for all state-changing operations
4. **Wildcard CORS** - Changed from wildcard `*` to explicit domain configuration

### High Priority Fixes

5. **Token Storage** - Moved from localStorage to secure httpOnly cookies
6. **Error Messages** - Changed to generic "Invalid credentials" message to prevent account enumeration
7. **Rate Limiting** - Extended to admin endpoints to prevent brute-force attacks
8. **Security Headers** - Added comprehensive security headers to all responses

## Installation Security Checklist

After installing Snip, ensure you complete these security steps:

- [ ] **Delete `install.php`** — Remove the installation script from the server
- [ ] **Configure CORS** — Update `CORS_ORIGIN` in `api/config.php` to your specific domain
- [ ] **Enable HTTPS** — Ensure HTTPS is enabled on your domain
- [ ] **Set Admin Password** — Generate a secure password hash and set `ADMIN_PASSWORD_HASH`
  ```bash
  php -r "echo password_hash('your-secure-password', PASSWORD_DEFAULT);"
  ```
- [ ] **Review Rate Limits** — Adjust `RATE_LIMIT_*` settings in `api/config.php` if needed
- [ ] **Database Security** — Use a strong password for the database user with limited privileges
- [ ] **File Permissions** — Set appropriate permissions on configuration files (read-only for web server)

## Configuration

### CORS Origin (Critical)

Edit `api/config.php` and set your actual domain:

```php
// WRONG: Allows any domain to make requests
define('CORS_ORIGIN', '*');

// CORRECT: Restrict to your domain
define('CORS_ORIGIN', 'https://yourdomain.com');

// MULTIPLE DOMAINS: Use comma-separated list
define('CORS_ORIGIN', 'https://yourdomain.com,https://api.yourdomain.com');
```

### HTTPS Enforcement

To force HTTPS redirect, uncomment in `.htaccess`:

```apache
# Enforce HTTPS in production
RewriteCond %{HTTPS} !on
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### Admin Password Hash

Generate a password hash:

```bash
php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
```

Copy the hash to `api/admin.php`:

```php
define('ADMIN_PASSWORD_HASH', '$2y$10$...');  // Replace with your hash
```

### Rate Limiting

Adjust in `api/config.php`:

```php
define('RATE_LIMIT_ENABLED', true);           // Enable/disable
define('RATE_LIMIT_MAX_REQUESTS', 10);        // Requests per window
define('RATE_LIMIT_WINDOW_SECONDS', 60);      // Time window
define('RATE_LIMIT_LOGIN_MAX', 5);            // Login attempts
define('RATE_LIMIT_LOGIN_WINDOW', 300);       // Login window (5 minutes)
```

## Known Limitations

### External JavaScript

The admin panel loads QR code library from CDN:
- `https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js`

Consider hosting this locally for maximum security:
1. Download the file to your server
2. Update the script tag in `admin.html` to load from your domain
3. Update CSP in `.htaccess` to only allow `'self'`

## Security Best Practices

### For Site Administrators

1. **Use Strong Passwords** — Use 16+ character passwords with mixed case, numbers, and symbols
2. **Enable HTTPS** — Always use HTTPS in production, never HTTP
3. **Keep PHP Updated** — Ensure your PHP version is current with security patches
4. **Backup Regularly** — Maintain regular database and file backups
5. **Monitor Access Logs** — Check for suspicious login attempts
6. **Update Regularly** — Keep Snip and all dependencies updated

### For Developers

1. **Never Commit Secrets** — Don't commit `api/config.php` with real credentials to git
2. **Use Environment Variables** — Consider using env variables for sensitive config
3. **Input Validation** — Always validate user input on the server side
4. **Parameterized Queries** — Never concatenate user input into SQL queries
5. **Output Escaping** — Always escape user data when outputting to HTML
6. **Security Audit** — Regularly audit code for security vulnerabilities

## Testing Security

### Test CSRF Protection

```bash
# This should fail (no CSRF token)
curl -X DELETE http://localhost/api/admin.php?id=1 \
  -H "Cookie: PHPSESSID=abc123"
```

### Test Rate Limiting

```bash
# Try login 6 times rapidly (limit is 5 per 5 minutes)
for i in {1..6}; do
  curl -X POST http://localhost/api/admin.php?action=auth \
    -H "Content-Type: application/json" \
    -d '{"password":"test"}'
done
```

### Test CORS

```bash
# Cross-origin request should fail
curl -X POST http://localhost/api/admin.php \
  -H "Origin: http://evil.com" \
  -H "Content-Type: application/json" \
  -d '{}' \
  -v
```

## Version History

### v1.0.3 (Security Release)

**Critical Security Fixes:**
- Replaced time-based tokens with cryptographically secure random tokens
- Implemented CSRF token protection for all state-changing operations
- Changed CORS from wildcard to explicit domain configuration
- Moved authentication tokens from localStorage to secure httpOnly cookies
- Added comprehensive security headers (CSP, HSTS, etc.)
- Rate limiting extended to admin authentication endpoints
- Error messages changed to prevent account enumeration

**High Priority Improvements:**
- Session security enhancements (httpOnly, Secure, SameSite flags)
- HTTPS enforcement with HSTS headers
- Improved input validation and sanitization
- Added CORS credentials handling

See [CHANGELOG.md](CHANGELOG.md) for full version history.

## References

- [OWASP Top 10 2021](https://owasp.org/Top10/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [PHP Password Hashing](https://www.php.net/manual/en/function.password-hash.php)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)
- [CSRF Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)

## Support

For security-related questions or vulnerability reports, please contact: security@example.com

---

Last Updated: 2025-02-13
