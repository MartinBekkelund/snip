# Troubleshooting: "Invalid credentials" on Admin Login

## Problem
When trying to log in to the admin panel, you get "Invalid credentials" error even with the correct password.

## Root Cause
The `ADMIN_PASSWORD_HASH` in `api/admin.php` is empty:
```php
define('ADMIN_PASSWORD_HASH', '');  // Line 16
```

Without a valid password hash, all login attempts fail.

---

## Solution

### Step 1: Generate a Password Hash

You need to create a bcrypt hash of your password. You have three options:

#### Option A: PHP Command Line (if you have shell access)
```bash
php -r "echo password_hash('your-password-here', PASSWORD_BCRYPT);"
```

**Replace `your-password-here` with your actual admin password.**

Output will look like:
```
$2y$10$k7fY8mK...long string of random characters...
```

#### Option B: Online Tool (Quick & Easy)
1. Visit: https://www.bcrypt-generator.com/ or https://www.passwordhasher.com/sha1.php
2. Enter your desired admin password
3. Copy the hash

#### Option C: Use a Simple PHP Script
Create a temporary PHP file (`hash.php`) with:
```php
<?php
echo password_hash('your-password-here', PASSWORD_BCRYPT);
?>
```

Upload to your server, visit it (e.g., `yourdomain.com/hash.php`), copy the output, then delete `hash.php`.

---

### Step 2: Update api/admin.php

1. **Via SFTP:**
   - Download `api/admin.php`
   - Open in text editor
   - Find line 16:
     ```php
     define('ADMIN_PASSWORD_HASH', '');
     ```
   - Replace with:
     ```php
     define('ADMIN_PASSWORD_HASH', '$2y$10$k7fY8mK...'); // Paste your hash here
     ```
   - Save file
   - Upload back to server via SFTP

2. **Example:**
   ```php
   // BEFORE:
   define('ADMIN_PASSWORD_HASH', '');

   // AFTER:
   define('ADMIN_PASSWORD_HASH', '$2y$10$salty.hash.generated.from.password_hash.function.here');
   ```

---

### Step 3: Test Login

1. Refresh your browser (clear cache if needed)
2. Try logging in with your password
3. Should now work! ✅

---

## If It Still Doesn't Work

### Check 1: Password Hash Format
- Should start with `$2y$10$`
- Should be 60 characters long
- Double-check it was pasted completely (no truncation)

### Check 2: File Was Saved
- Download `api/admin.php` again
- Verify line 16 has your hash (not empty)
- Check no syntax errors (missing quotes, etc.)

### Check 3: Session Issues
- Clear browser cookies for your domain
- Try in incognito/private window
- Check browser console (F12) for errors

### Check 4: Database Connection
- Verify database credentials in `api/config.php` are correct
- Check that `rate_limits` table exists in database

---

## Password Requirements

The password can be anything you want:
- ✅ `mySecurePassword123!`
- ✅ `admin`
- ✅ `SuperSecret@Pass#2025`
- ✅ Any combination of letters, numbers, symbols

### Security Best Practice
Use a **strong password**:
- At least 12 characters
- Mix of uppercase, lowercase, numbers, symbols
- Example: `Sn1p-Admin$2025!`

---

## Preventing This in Future

### For v1.0.3+ (with automated updates):
The new system still uses the same password hash. After upgrading:

1. Keep the same `ADMIN_PASSWORD_HASH` in `api/config.php`
2. Or set a new one using the same method above
3. The password won't change during updates

---

## Quick Reference

**File:** `api/admin.php`
**Line:** 16
**Issue:** `ADMIN_PASSWORD_HASH` is empty
**Solution:** Generate hash and replace empty string with your hash

```php
// Generate hash using:
php -r "echo password_hash('your-password', PASSWORD_BCRYPT);"

// Then update line 16 with the result
define('ADMIN_PASSWORD_HASH', '$2y$10$...');
```

---

## Still Need Help?

1. Verify the hash was generated correctly (60 chars, starts with `$2y$10$`)
2. Check file was uploaded correctly
3. Clear browser cache and cookies
4. Try incognito window
5. Check `/api/admin.php` line 16 again to confirm change was saved

**Once you can log in, you can proceed with the v1.0.3 upgrade as documented!**
