# TODO — Seven CMS

Current backlog. Items are not committed deadlines, just an honest list of what's not done yet.

## Open

### UX testing
- [ ] Manual pass through Blog/Pages/Media/Ecom forms in browser — verify nothing regressed after the form-validation overhaul (CSRF auto-inject, novalidate, custom select wrapping, dark-theme pickers)

### Hardening (when CMS goes public)
- [ ] Third-party security audit before 1.0
- [ ] Fix the 6 dependabot moderate vulnerabilities flagged on push

### Tests
- [ ] Integration test suite — currently only `tests/Unit/` (Container, Event, JWT, Money, TOTP). DB-touching code is verified by hand. PHPUnit DB fixtures + a separate test database would close this gap.

### Docs polish
- [ ] Walk every `docs/*.md` once — ensure every code sample is current after the recent CSRF/RateLimit/Csp changes

### Nice-to-have
- [ ] Replace lingering `/admin/help` markdown placeholders (`#mdlink#...#`) with verified links

## Done (recent)

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
