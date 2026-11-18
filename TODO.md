# TODO — Seven CMS

Current backlog. Items are not committed deadlines, just an honest list of what's not done yet.

## Open

### UX testing
- [ ] Manual pass through Blog/Pages/Media/Ecom forms in browser — verify nothing regressed after the form-validation overhaul (CSRF auto-inject, novalidate, custom select wrapping, dark-theme pickers)

### Demo instance (public showcase)
- [ ] Deploy a demo on the VPS with the latest stable PHP (8.4/8.5), php-fpm + nginx (see `nginx.conf.example`)
- [ ] `DEMO_MODE=true`: shared demo login shown on the sign-in page; block users/roles/settings/SMTP/payment keys/plugin install/theme files/key rotation; uploads images-only and small
- [ ] Hourly reset of DB + uploads from a seed snapshot (cron), banner "Demo — data resets every hour"

### Themes
- [ ] Custom theme creation in admin — UI to build a new palette (light + dark variants required for both modes), save as a new `src/themes/{slug}/` with `theme.json` + `_light.scss` + `_dark.scss`, picked up by `ThemeAdminController::scanThemes()` automatically. Both light and dark variants are mandatory.

### Hardening (when CMS goes public)
- [ ] Third-party security audit before 1.0
- [ ] picomatch (build-time only) has no patched release yet — re-run `npm audit fix` when one ships
- [ ] Hash session tokens at rest (`token.value` is stored in plain text; a DB leak = session hijack)
- [ ] Serve paid digital downloads from outside `public/` (today a leaked `/uploads/...` URL bypasses the grant token)
- [ ] Stored HTML from editors (page/post/product content) is rendered raw — sanitise rich text or rely on CSP being strict in prod

### Tests
- [ ] Integration test suite — currently only `tests/Unit/` (Container, Event, JWT, Money, TOTP). DB-touching code is verified by hand. PHPUnit DB fixtures + a separate test database would close this gap.

### Docs polish
- [ ] Walk every `docs/*.md` once — ensure every code sample is current after the recent CSRF/RateLimit/Csp changes

### Architecture debt
- [ ] Two auth APIs side by side: legacy `/api/auth/*` (opaque token) and `/api/v1/*` (JWT). Pick v1, deprecate legacy.
- [ ] Route middleware (`Route::middleware()`, `app/middleware/*`) is never executed by the dispatcher — wire it in or delete it.
- [ ] Public comment submission does not exist (only admin moderation of comments).
- [ ] Admin UI languages (en/de/fr/nl) differ from site languages (en/ru/ka/uk/az/hy).


## Done (recent)

- ✅ Modules renamed to **Plugins** (`/admin/plugin`); empty-list bug fixed (wrong view path); enable/disable now really unloads a plugin (DB is source of truth, also for autoload); actions POST-only
- ✅ Admin login was broken for everyone (token never handed to `Auth`)
- ✅ `bin/sev` CLI booted with fatal errors — fixed; new `key:generate`
- ✅ Routing: named routes now resolve URLs (`/admin/ecom/*`, `/api/v1/*`, `/admin/roles`, `/admin/sites` were 404); case-insensitive autoload for Linux; slash view paths (≈20 admin screens rendered empty)
- ✅ API v1 / GraphQL fixed (500s, trailing `1` in JSON, bean vs array); GraphQL `user(id)` no longer leaks other users
- ✅ Security: upload RCE (client extension), SVG sanitising, HTTPS detection (cookies never Secure, no HSTS), JWT `change-me` fallback, dev mode by default, spoofable client IP, open redirect, API error reflection, ~20 GET mutations, CSRF for fetch()
- ✅ Password reset implemented end-to-end; session tokens expire server-side
- ✅ Per-user features (calendar, notifications, dashboard) ran as user 0; DB/PHP clock skew fixed
- ✅ Duplicate plugin controllers/models removed from `app/`
- ✅ npm audit fix; docs `#mdlink#` placeholders replaced with real links
- ✅ `.gitattributes` — `* text=auto eol=lf`, kills LF/CRLF warnings
- ✅ CSP nonce on every inline `<script>`/`<style>` in all views (Master.html + feature views + error pages + setup). `'unsafe-inline'` dropped from prod CSP — only kept in dev for Vite HMR shims
- ✅ Custom form validation engine + dark-theme pickers (admin + site)
- ✅ Custom select wrapper (`seven-select`) with keyboard nav
- ✅ Character counter for `[maxlength]` inputs/textareas
- ✅ Shake + toast on invalid submit
- ✅ Global CSRF guard for admin POST/PUT/PATCH/DELETE
- ✅ CSRF token auto-inject into every form via inline JS in Master.html
- ✅ `RateLimit` moved off PHP sessions to `Cache` facade — works for stateless API clients
- ✅ Rate limit on all auth endpoints (login/refresh/register/forgot/reset)
- ✅ Trusted-proxy validation for `X-Forwarded-For`
- ✅ `SvgSanitizer` (DOMDocument + LIBXML_NONET, whitelist of tags/attrs)
- ✅ Open-redirect fix in `AuthController::logout()`
- ✅ CSP per-request nonce (`Csp::nonce()`) on every Master.html inline block
- ✅ Pretty `ErrorPage` for dev-mode (GitHub-dark, source snippet, stack trace, copy button)
- ✅ Boot order fix: DB connect before `Module::loadAll()` so plugins can query in `boot()`
- ✅ `DB::execute()` alias proxying to RedBean `exec()` (~131 call sites)
- ✅ Dashboard layout 50/50 (Calendar | Add event, then 2x2 System/Mail/Cron/Ecom)
- ✅ Add-event flow on dashboard with inline upcoming list (max 3 + "View all")
- ✅ File header `/** SevenCMS — github.com/... */` in all 208 PHP source files
- ✅ Squashed legacy git history into one Initial commit under correct author
- ✅ README "Project status & credits" section (transparent about AI-assisted development)
- ✅ docs/security.md + docs/forms.md, registered in HelpAdminController
- ✅ Removed legacy: `public/bundle.js`, `public/package.json`, `lib/extension/rb/rb.php.bak43`
- ✅ Renamed `rewrite_nginx.info` → `nginx.conf.example`
- ✅ Workspace conventions split into per-topic `.claude/rules/*.md`

## Won't do (decided against)

- ~~Replicate features in sevenAngular.io / sevenVUE~~ — those folders are now separate products (Suite ERP/CRM/Billing and Shop e-commerce respectively). The "ship in all 3" rule is retired.
- ~~Strict CSP without `'unsafe-inline'`~~ for now — kept as fallback so feature views still work. Migrate per-view as we touch them, not in one big sweep.
