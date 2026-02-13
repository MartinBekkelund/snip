# Security Fixes Applied - v1.0.3

## Overview

This document summarizes all security vulnerabilities fixed in version 1.0.3. The release addresses **5 critical/high-severity vulnerabilities** and improves overall security posture with comprehensive security headers, CSRF protection, and improved session management.

---

## Critical Vulnerabilities Fixed

### 1. ⚠️ Time-Based Token Bypass → FIXED

**Status:** ✅ FIXED

**File(s):** `api/admin.php`

**What Was Wrong:**
- Authentication tokens were generated as `hash('sha256', ADMIN_PASSWORD . date('Y-m-d'))`
- This made tokens predictable and only changed once per day
- Tokens could be brute-forced knowing just the current date

**What Changed:**
```php
// BEFORE (INSECURE)
$token = hash('sha256', ADMIN_PASSWORD . date('Y-m-d'));

// AFTER (SECURE)
$token = generateSecureToken(32);
$tokenHash = password_hash($token, PASSWORD_BCRYPT);
$_SESSION['admin_token'] = $tokenHash;
$_SESSION['admin_token_expires'] = time() + (TOKEN_EXPIRY_HOURS * 3600);
```

**Key Improvements:**
- Tokens are now cryptographically secure random 64-character hex strings
- Tokens are hashed before storage (using bcrypt) via `password_hash()`
- Server-side validation using `password_verify()`
- Token expiration is enforced server-side

**Testing:**
```bash
# Verify tokens are now unpredictable
php -r "echo bin2hex(random_bytes(32));"  # Different every time
```

---

### 2. ⚠️ Empty ADMIN_PASSWORD_HASH → FIXED

**Status:** ✅ FIXED

**File(s):** `api/admin.php`

**What Was Wrong:**
- Default value was empty string: `define('ADMIN_PASSWORD_HASH', '');`
- `password_verify()` would never match against empty string
- Could potentially allow authentication bypass

**What Changed:**
```php
// BEFORE
if (password_verify($password, ADMIN_PASSWORD_HASH)) {
    // Always false when ADMIN_PASSWORD_HASH is empty
}

// AFTER
if (!empty(ADMIN_PASSWORD_HASH) && password_verify($password, ADMIN_PASSWORD_HASH)) {
    // Now requires hash to be configured
}
```

**Key Improvements:**
- Explicit check for empty hash
- Login fails safely if password is not configured
- Documentation updated to show setup process

**Setup Instructions:**
```bash
# Generate a password hash
php -r "echo password_hash('your-secure-password', PASSWORD_DEFAULT);"

# Copy the output to api/admin.php:
# define('ADMIN_PASSWORD_HASH', '$2y$10$...');
```

---

### 3. ⚠️ Tokens in localStorage → FIXED

**Status:** ✅ FIXED

**File(s):** `admin.html`, `api/admin.php`

**What Was Wrong:**
- Authentication tokens stored in `localStorage`
- Vulnerable to XSS attacks - any XSS could steal tokens
- No automatic cleanup on token expiry

**What Changed:**
```javascript
// BEFORE (INSECURE)
let authToken = localStorage.getItem('adminToken') || '';
localStorage.setItem('adminToken', authToken);

// AFTER (SECURE)
let csrfToken = '';  // Only CSRF token in memory
// Authentication happens via secure httpOnly cookie
```

**Key Improvements:**
- Removed localStorage usage entirely
- Authentication now uses secure httpOnly cookies (set by PHP session)
- CSRF tokens are kept in memory only
- Cookies automatically expire (server-side)
- XSS attacks cannot steal httpOnly cookies

**Security Benefits:**
- JavaScript cannot access authentication cookies (httpOnly flag)
- HTTPS required (Secure flag)
- Not sent to different sites (SameSite=Strict flag)

---

### 4. ⚠️ Wildcard CORS → FIXED

**Status:** ✅ FIXED

**File(s):** `api/config.php`, `.htaccess`

**What Was Wrong:**
- CORS configured as `*` (wildcard)
- Allowed requests from ANY domain
- Combined with `credentials: include`, violated CORS security

**What Changed:**
```php
// BEFORE (INSECURE)
define('CORS_ORIGIN', '*');

// AFTER (SECURE)
define('CORS_ORIGIN', 'https://yourdomain.com');
```

**Key Improvements:**
- CORS restricted to specific domains
- Must explicitly configure your domain
- Multiple domains supported (comma-separated)
- API now rejects requests from unauthorized origins

**Configuration Example:**
```php
// Single domain
define('CORS_ORIGIN', 'https://yourdomain.com');

// Multiple domains
define('CORS_ORIGIN', 'https://yourdomain.com,https://api.yourdomain.com');
```

---

### 5. ⚠️ Missing CSRF Protection → FIXED

**Status:** ✅ FIXED

**File(s):** `api/admin.php`, `admin.html`

**What Was Wrong:**
- No CSRF tokens on state-changing operations (PUT, DELETE)
- Attacker could craft requests to delete/modify URLs
- User's browser would automatically send credentials

**What Changed:**
```php
// BEFORE (INSECURE)
function handleDelete() {
    const r = await fetch(`/api/admin.php?id=${id}`, {
        method: 'DELETE',
        credentials: 'include'
    });
}

// AFTER (SECURE)
function handleDelete() {
    const r = await fetch(`/api/admin.php?id=${id}`, {
        method: 'DELETE',
        credentials: 'include',
        headers: { 'X-CSRF-Token': csrfToken }  // CSRF token required
    });
}

// Server-side validation
function requireCsrfToken(): void {
    $method = $_SERVER['REQUEST_METHOD'];
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        if (!validateCsrfToken()) {
            jsonResponse(['success' => false, 'error' => 'Invalid request'], 403);
        }
    }
}
```

**Key Improvements:**
- CSRF tokens generated per session
- Required for all state-changing operations
- Server validates token on every request
- Prevents cross-origin attacks (CSRF)

**How It Works:**
1. User logs in → server generates CSRF token
2. Frontend receives CSRF token in login response
3. Frontend includes CSRF token in all state-changing requests
4. Server validates CSRF token matches session
5. Request succeeds only if token is valid

---

## High Priority Improvements

### 6. ✅ Rate Limiting Extended

**File(s):** `api/admin.php`

**What Was Changed:**
- Rate limiting now applied to login endpoint
- Prevents brute-force password attempts
- Config: 5 attempts per 5 minutes per IP

```php
if (!checkRateLimit('login_' . $clientIp, RATE_LIMIT_LOGIN_MAX, RATE_LIMIT_LOGIN_WINDOW)) {
    jsonResponse(['success' => false, 'error' => 'Invalid credentials'], 401);
}
```

### 7. ✅ Security Headers Added

**File(s):** `.htaccess`

**Headers Added:**
```apache
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
Header set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
Header set Content-Security-Policy "default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com; ..."
```

**Protections:**
- Prevents MIME type sniffing
- Prevents clickjacking
- XSS protection for older browsers
- HSTS forces HTTPS
- CSP prevents inline scripts/injection attacks

### 8. ✅ HTTPS Enforcement

**File(s):** `.htaccess`

**What Changed:**
- Added HTTPS redirect rules (commented, for easy activation)
- HSTS header forces browsers to use HTTPS
- Secure flag on cookies

```apache
# Uncomment to enforce HTTPS
RewriteCond %{HTTPS} !on
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 9. ✅ Error Message Improvement

**File(s):** `api/admin.php`, `admin.html`

**What Was Changed:**
```php
// BEFORE (Reveals information)
jsonResponse(['success' => false, 'error' => 'Feil passord'], 401);

// AFTER (Generic message)
jsonResponse(['success' => false, 'error' => 'Invalid credentials'], 401);
```

**Benefit:** Prevents account enumeration attacks

### 10. ✅ Session Security Enhanced

**File(s):** `api/config.php`, `api/admin.php`

**Improvements:**
- httpOnly flag prevents JavaScript access
- Secure flag ensures HTTPS-only transmission
- SameSite=Strict prevents CSRF attacks
- Session uses secure configuration from startSecureSession()

```php
function startSecureSession(): void {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'use_only_cookies' => true
    ]);
}
```

---

## Testing the Fixes

### Test 1: CSRF Protection
```bash
# This should FAIL (no CSRF token)
curl -X DELETE http://localhost/api/admin.php?id=1 \
  -H "Cookie: PHPSESSID=abc123"
# Response: {"success":false,"error":"Invalid request"}
```

### Test 2: Rate Limiting
```bash
# Try 6 login attempts (limit is 5 per 5 minutes)
for i in {1..6}; do
  curl -X POST http://localhost/api/admin.php?action=auth \
    -H "Content-Type: application/json" \
    -d '{"password":"test"}'
done
# 6th attempt should fail with rate limit error
```

### Test 3: Token Unpredictability
```php
<?php
require_once 'api/config.php';
// Generate multiple tokens - should all be different
for ($i = 0; $i < 3; $i++) {
    echo generateSecureToken(32) . "\n";
}
// Output should show 3 completely different tokens
```

### Test 4: CORS Restriction
```bash
# Cross-origin request from unauthorized domain
curl -X OPTIONS http://localhost/api/admin.php \
  -H "Origin: http://evil.com" \
  -v

# Should NOT contain: Access-Control-Allow-Origin: http://evil.com
# Should contain: Access-Control-Allow-Origin: https://yourdomain.com (configured)
```

### Test 5: Security Headers
```bash
curl -I https://yourdomain.com/admin.html | grep -E "X-|Strict-Transport|Content-Security"
# Should show all security headers present
```

---

## Migration Guide

### For Existing Installations

If you're upgrading from v1.0.0 or v1.0.2, follow these steps:

1. **Backup your database and files**
   ```bash
   mysqldump -u user -p database > backup.sql
   cp -r snip snip-backup
   ```

2. **Update files** (don't overwrite `api/config.php`)
   - Download all files except `api/config.php`
   - Replace existing files

3. **Update ADMIN_PASSWORD_HASH**
   ```bash
   php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"
   ```
   - Copy the hash output
   - Update in `api/admin.php`

4. **Configure CORS**
   - Edit `api/config.php`
   - Change `CORS_ORIGIN` from `'*'` to your domain: `'https://yourdomain.com'`

5. **Test authentication**
   - Clear browser cookies
   - Login to admin panel
   - Verify all functions work

6. **Verify security headers**
   ```bash
   curl -I https://yourdomain.com/admin.html
   ```
   - Should show security headers

---

## Files Modified

| File | Changes |
|------|---------|
| `api/admin.php` | Token generation, CSRF protection, rate limiting, session security |
| `api/config.php` | CORS configuration, documentation |
| `admin.html` | Removed localStorage, added CSRF token handling |
| `.htaccess` | Security headers, HTTPS enforcement, CORS setup |
| `SECURITY.md` | New file with comprehensive security documentation |

---

## Remaining Medium/Low Priority Items

The following issues have been noted for future releases:

- [ ] No audit logging of admin actions (MEDIUM priority)
- [ ] Refactor inline event handlers to use data attributes (LOW priority)
- [ ] Input validation improvements for referer headers (LOW priority)
- [ ] Host QR code library locally instead of CDN (LOW priority)

See `/Users/martin/.claude/plans/proud-sleeping-sutherland.md` for complete security audit results and Phase 2/3 recommendations.

---

## Verification Checklist

After upgrading, verify:

- [ ] Admin login works with secure password hash
- [ ] CSRF token is required for edit/delete operations
- [ ] Tokens from localStorage are removed (check browser DevTools)
- [ ] CORS restricts to your configured domain
- [ ] Security headers appear in HTTP responses
- [ ] Rate limiting prevents rapid login attempts
- [ ] Session expires after 24 hours (TOKEN_EXPIRY_HOURS)
- [ ] HTTPS redirect is enabled (if using .htaccess rule)

---

## Questions?

For security-related questions or vulnerability reports, see `SECURITY.md` for contact information.

---

**Release Date:** 2025-02-13
**Version:** 1.0.3
**Security Release:** Yes
