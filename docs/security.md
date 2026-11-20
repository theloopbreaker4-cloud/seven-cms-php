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
- `AuthApiController::login` (site session, `POST /api/v1/session`)
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

Default is empty — `REMOTE_ADDR` is used as-is. When it is set,
`X-Forwarded-For` is read right-to-left (the left part is client-supplied)
and `X-Forwarded-Proto: https` from those proxies marks the request as HTTPS
(Secure cookies, HSTS). Always use `RateLimit::clientIp()` instead of reading
these headers yourself. Behind Cloudflare, prefer nginx `real_ip_header
CF-Connecting-IP` with Cloudflare's ranges so `REMOTE_ADDR` is already right.

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

Media and `Upload` detect the MIME type from the file content and take the
stored **extension from that type** (`Media::EXT_FOR_MIME`), never from the
client's filename — a JPEG/PHP polyglot named `x.php` is stored as `.jpg`.
The filename is a random UUID. `public/uploads/.htaccess` and the nginx
example deny script execution and send a sandboxing CSP for uploads.

Paid digital files are not media at all: they live in
`storage/private/digital/` (outside the web root) and are only streamed by
`/{lang}/shop/download/{token}` with an atomic download-limit check.

### Rich text

Editor-authored HTML (page/post content, product descriptions) goes through
`HtmlSanitizer::clean()` — an allowlist of formatting tags, `http(s)`/
`mailto`/relative URLs only, scripts and event handlers removed. Every other
text field is tag-stripped. Comments are plain text.

## Authentication

- Sessions for the admin UI and the site (`token` cookie, `httponly`, `secure`,
  `samesite=Strict`). The site logs in through `POST /api/v1/session`, which sets
  the cookie server-side — the token is never returned to JavaScript (before
  2026-11-20 it was, and the site stored it in a script-readable cookie).
- JWT HS256 + rotating refresh tokens for `/api/v1/*`. Access TTL 15 min,
  refresh TTL 30 days. Refresh hashes stored in `api_refresh_tokens`.
- TOTP 2FA (RFC 6238). Recovery codes are bcrypt-hashed in
  `user_totp.recovery_codes`.
- Passwords: `password_hash($pw, PASSWORD_BCRYPT, ['cost' => 10])`.
- Session tokens are stored as SHA-256 in the `token` table and expire
  server-side after 30 days, whatever the cookie says.
- Password reset: one-time token (stored hashed, 1 hour), sent through the
  mail queue; a successful reset revokes every session and refresh token.
- `JWT_SECRET` must be at least 32 characters — the API refuses to sign
  tokens otherwise. Generate it with `php bin/sev key:generate`.
- A missing `SEVEN_ENV` means `prod` (no stack traces in error pages).

## No state changes over GET

Admin actions that change data (`delete`, `*Delete`, `*Store`, `*Update`,
`*Upload`, `toggle`, `run`, `approve`, `read`, …) answer `405` to GET. In
views use a POST form, or a link with `data-method="post"` (optionally with
`data-confirm`) — `src/admin/dialog.js` submits it as a form with the CSRF
token. Same-origin `fetch()` calls in the admin automatically send an
`X-CSRF-Token` header, which `Csrf::verify()` accepts.

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
