# Contributing to SevenCMS PHP

Thank you for your interest in contributing.

---

## Development Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/theloopbreaker4-cloud/seven-php.git
   cd seven-php
   ```
3. Copy `.env.example` to `.env` and fill in your local credentials
4. Install frontend dependencies:
   ```bash
   npm install
   npm run build
   ```
5. Start the PHP dev server:
   ```bash
   wsl.exe -d Ubuntu bash -c 'php -S 0.0.0.0:8085 -t /mnt/d/Works/SevenCMSProjects/sevenPHP/public'
   ```

---

## Branch Naming

| Type | Pattern | Example |
|------|---------|---------|
| Feature | `feature/short-description` | `feature/2fa-totp` |
| Bug fix | `fix/short-description` | `fix/session-cookie-flags` |
| Security | `security/short-description` | `security/csrf-middleware` |
| Refactor | `refactor/short-description` | `refactor/router-cleanup` |

---

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add CSRF middleware
fix: correct session regeneration after login
security: add httpOnly flag to auth cookie
refactor: extract Env class from index.php
docs: update README installation steps
```

---

## Code Standards

- PHP 8.4+ syntax
- Class files: `lib/classname.class.php` (lowercase filename)
- Controllers: `app/controllers/NameController.php`
- Models: `app/models/name.php` (lowercase filename)
- Views: `app/views/{prefix}/{controller}/{action}.html`
- All comments in **English**
- No inline credentials — use `.env`
- All forms must include CSRF token
- All user input must be sanitized via `Request` methods

---

## Pull Request Checklist

- [ ] Code follows the structure above
- [ ] No credentials hardcoded
- [ ] CSRF token included on new forms
- [ ] Input sanitized with `Request::sanitize()`
- [ ] Security headers not removed
- [ ] CHANGELOG.md updated under `[Unreleased]`
- [ ] README updated if new feature changes setup/URL structure

---

## Reporting Bugs

Open an issue at [github.com/theloopbreaker4-cloud/seven-php/issues](https://github.com/theloopbreaker4-cloud/seven-php/issues) with:

- PHP version
- Steps to reproduce
- Expected vs actual behavior

For security vulnerabilities — see [SECURITY.md](SECURITY.md).
