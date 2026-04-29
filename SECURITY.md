# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x (current) | Yes |

---

## Reporting a Vulnerability

**Do not open a public GitHub issue for security vulnerabilities.**

Report privately by emailing: **the.loop.breaker4@gmail.com**

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (optional)

You will receive a response within **48 hours**. If confirmed, a patch will be released within **7 days**.

---

## Security Measures

SevenCMS PHP implements the following protections:

### Input & Queries
- All user input sanitized via `Request` class (`htmlspecialchars`, `filter_var`)
- Database queries via RedBeanPHP parameterized statements
- Protection against SQL injection, XSS, directory traversal

### Authentication
- Passwords hashed with `password_hash()` / `password_verify()` (bcrypt, cost 10)
- Login rate limiting — account locked after repeated failed attempts
- Session ID regenerated after login (`session_regenerate_id()`)
- Auth cookies: `httpOnly`, `Secure`, `SameSite=Strict`

### Forms
- CSRF tokens on all state-changing forms
- Server-side validation on all input

### Headers
- `Content-Security-Policy`
- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`

### Files
- Upload MIME type whitelist
- Uploaded files stored outside web root or with execution blocked
- Directory traversal protection

### Configuration
- Credentials stored in `.env` (never committed to repository)
- `.env` listed in `.gitignore`
- Error details hidden in `prod` environment
