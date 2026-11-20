# TODO — Seven CMS

Current backlog. Items are not committed deadlines, just an honest list of what's not done yet.

## Open

### Before going live
- [ ] Manual click-through of every admin form in a real browser (all flows are covered by HTTP smoke tests, not by a browser)
- [ ] Third-party security audit before 1.0
- [ ] picomatch (build-time only) has no patched release yet — re-run `npm audit fix` when one ships

### Tests
- [ ] Port the HTTP smoke suite (login, plugins, CSRF, API/GraphQL, uploads, content, digital downloads, comments, themes, admin languages, middleware) into PHPUnit integration tests with a throw-away database. Today `tests/Unit/` covers only Container, Event, JWT, Money, TOTP, and PHPUnit isn't installed in the dev box.

### Architecture debt
- [ ] Remove the deprecated `/api/*` URL aliases in 1.0 (the Vue site no longer uses them).
- [ ] Run PHPStan once Composer is available. A token-based bean-vs-array scan (bean passed to array functions / array-typed params, returned from `array` functions, `!R::load()`) is clean; PHPStan would also cover views and generic type errors.
- [ ] RedBean runs in fluid mode everywhere (core tables like `post`, `page`, `user` are only created that way). Write migrations for them, then freeze the schema in prod.


## Done (recent)

- ✅ Shop + headless content actually work end-to-end: cart (was 500), add-to-cart (REST path swallowed), checkout (orders saved to a fluid look-alike table), content type create + entry update; route `path`/`method`; models match snake_case columns; `db:orphans`
- ✅ Admin UI in az and hy — all 9 languages complete
- ✅ Vue site on `/api/v1`; site session is an httpOnly cookie set by the server (token used to be readable by JS); site login rate-limited; legacy content API accepts JWT

- ✅ Public demo: DEMO_MODE (read-only sensitive sections, demo login, banners, 2 MB image uploads), `demo:init/seed/snapshot/reset`, hourly cron reset; deployed with PHP 8.5 + MariaDB 11.4
- ✅ Custom themes built in the admin (light + dark, WCAG contrast check, no build step)
- ✅ Public comments with moderation (API, honeypot, rate limit, admin queue)
- ✅ Digital files stored outside the web root with an admin UI; paid downloads (were always 500) fixed; atomic download limits
- ✅ HtmlSanitizer for rich text; stored XSS in product descriptions closed
- ✅ Session tokens hashed at rest; route middleware actually runs
- ✅ Admin UI in ru/uk/ka
- ✅ Broken flows fixed: post create/edit, product create/edit, roles/sites/discounts/blocks create (lastInsertId), shop settings + currency (wrong table), cron run / mail retry / calendar delete / currency toggle (lost params), flash messages, public blog + pages (always empty)
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
