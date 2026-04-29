# Security

How SevenCMS protects requests, sessions, and uploads. Read this before
running the site in production — most defaults are safe, but a few choices
(trusted proxies, CSP, file uploads) need explicit configuration.

## CSRF

Every state-changing request to `/admin/...` is verified by a global guard in
`General::process()`:

```php
if ($prefix === 'admin' && in_array($method, ['POST','PUT','PATCH','DELETE'])) {
    Csrf::verify($_POST + $_GET);
}
```

The token is generated per session (`Csrf::token()`) and auto-injected into
every admin form by a small inline script in `app/views/admin/Master.html`:

```html
<script>
  window.__CSRF_TOKEN__ = "<?= Csrf::token() ?>";
  document.querySelectorAll('form').forEach(f => {
    if (f.method.toUpperCase() !== 'GET' && !f.querySelector('[name=_csrf_token]')) {
      f.appendChild(/* hidden _csrf_token input */);
    }
  });
</script>
```

So even forms that forget to call `<?= Csrf::field() ?>` are protected. The
hardcoded `Csrf::field()` calls in views are belt-and-braces — they keep
working when JS is disabled.

**Site-public POSTs** (auth/login, auth/register, setup/install) are NOT
under the admin guard. They include `<?= Csrf::field() ?>` in the form and
either rely on the auto-inject script or call `Csrf::verify()` manually
(see `SetupController::install()`).

**API endpoints** (`/api/v1/*`) are exempt from CSRF — they use stateless JWT
in the `Authorization` header, which browsers don't send automatically, so
classic CSRF doesn't apply.

## Rate limiting

`RateLimit` throttles per-IP per-action via the `Cache` facade (Redis or
file driver — whatever Cache is booted with). 5 attempts per 15 minutes,
configurable in the class constants.

```php
RateLimit::check('api_login');         // 429 if exceeded
RateLimit::hit('api_login');           // count a failed attempt
RateLimit::clear('api_login');         // wipe on success (e.g. after login)
```

Currently wired into:
- `AuthAdminController::login`
- `AuthV1ApiController::login`, `::refresh`
- `AuthApiController::register`, `::forgot`, `::reset`

To throttle a new endpoint just call `check()` first and `hit()`/`clear()`
on failure/success.

### Trusted proxies

If you run behind nginx/Cloudflare, the real client IP is in
`X-Forwarded-For`, but that header can be spoofed by anyone hitting your
app directly. SevenCMS only honours it when `REMOTE_ADDR` is in the
configured whitelist:

```env
TRUSTED_PROXIES=10.0.0.1,10.0.0.2
```

Default is empty — `REMOTE_ADDR` is used as-is.

## File uploads

### SVG sanitization

`SvgSanitizer::clean($svg)` parses uploads through `DOMDocument` with
`LIBXML_NONET` (blocks XXE), removes:

- everything outside a tight tag whitelist (no `<script>`, `<foreignObject>`,
  `<iframe>`, etc.)
- every `on*` event handler attribute
- `href` / `xlink:href` values starting with `javascript:`, `vbscript:`,
  `file:`, or non-image `data:`
- `style` attributes containing `expression()`, `javascript:`, `@import`

Used by `SettingAdminController::uploadbrand()` for brand/favicon SVGs. If
you accept SVG anywhere else, run uploads through it.

### Other uploads

`MediaModel` and theme icon upload validate MIME type and extension; the
final filename is replaced with a UUID, so directory traversal via crafted
filenames is impossible.

## Authentication

- Sessions for the admin UI (PHP `Session` wrapper, `httponly`, `secure`,
  `samesite=Strict`)
- JWT HS256 + rotating refresh tokens for `/api/v1/*`. Access TTL 15 min,
  refresh TTL 30 days. Refresh hashes stored in `api_refresh_tokens`.
- TOTP 2FA (RFC 6238). Recovery codes are bcrypt-hashed in
  `user_totp.recovery_codes`.
- Passwords: `password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10])`.

`Session::regenerate()` is called on login to mitigate fixation. `Csrf::rotate()`
is called after a successful POST to prevent token replay.

## Open redirects

`AuthController::logout()` accepts a `?next=` parameter for post-logout
redirect, but only when:

- It starts with a single `/`
- The second char is not `/` or `\` (rejects `//evil.com` and `\evil`)
- It matches `^/[a-z]{2}(/|$)` (must look like a localized internal path)
- It contains no whitespace or `:` (rejects `javascript:` and friends)

Anything else falls through to the default `auth/index`.

## Headers / CSP

Security headers are sent unconditionally by `public/index.php`:

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=()`
- `Content-Security-Policy:` — `'self'` + per-request `'nonce-{value}'` for
  inline scripts and styles. **`'unsafe-inline'` is dropped in production**
  — every inline block in the codebase is stamped with the nonce. In dev,
  `'unsafe-inline'` is kept as a fallback because Vite injects unsigned
  inline shims for HMR.
- `Strict-Transport-Security` (only when serving over HTTPS)

### CSP nonce

Every inline `<script>` and `<style>` in views — Master.html *and* every
feature view — uses:

```html
<script nonce="<?= Csp::nonce() ?>">…</script>
<style  nonce="<?= Csp::nonce() ?>">…</style>
```

When you add a new inline block, **always** include the nonce attribute.
Forgetting it means the script silently fails to execute in production.

## Reporting issues

Open a security advisory on
[GitHub](https://github.com/theloopbreaker4-cloud/seven-cms-php/security/advisories)
rather than a public issue.
