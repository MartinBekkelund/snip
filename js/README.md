# JavaScript Libraries

This directory contains local copies of JavaScript libraries used by SN/P.

## QR Code Library

The QRCode.js library is hosted locally to avoid CDN dependencies.

**Original Source:** https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js

**Installation:**
1. Download `qrcode.min.js` from the CDN or included in this package
2. Place in this directory
3. The admin panel will automatically use the local copy

## Why Local Hosting?

- **No external dependencies** - Works offline
- **Better security** - No CDN trust required
- **Faster loading** - No external network request
- **GDPR compliant** - No third-party tracking

## Libraries

- `qrcode.min.js` (v1.0.0) - QR code generation library
