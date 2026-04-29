# Changelog

All notable changes to SevenCMS PHP are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — [Semantic Versioning](https://semver.org/)

---

## [Unreleased]

### Added — 2026-04-29 — Operations & dashboard

- **Email queue** — `lib/mailer.class.php`, `mail_queue` table, `Mailer::queue()`,
  exponential backoff (1m → 4h), retry/flush UI at `/admin/mail`, CLI commands
  `mail:send` and `mail:status`. EcomMail now goes through the queue by default.
- **Cron scheduler** — `lib/cronrunner.class.php`, `cron_jobs` table,
  `CronRunner::register()` for plugins, `bin/sev cron:run` (run from system cron),
  admin UI at `/admin/cron`. Built-in jobs: mail flush + cleanup, notifications
  cleanup, calendar reminders, manual subscription billing, FX refresh.
- **Manual subscription billing** — `SubscriptionBiller::billDue()` invoiced
  via the `ecom.subscription.bill_due` cron (hourly).
- **Multi-currency** for Ecom — `ecom_currencies` + `ecom_fx_rates` tables,
  `CurrencyService` (manual + exchangerate.host providers), customer
  `preferred_currency`, `/admin/ecom/currencies` admin, `/api/v1/shop/currencies`
  endpoint, currency picker on the Vue storefront. Off by default.
- **Notifications** — `notifications` table, `Notify::user()` / `Notify::admins()`,
  bell icon in the admin top bar with unread badge, `/admin/notifications` inbox,
  60-day cleanup cron.
- **Calendar** — `calendar_events` table, `CalendarEvent::add()`, `notify_at`
  reminders fired by the per-minute sweep cron, monthly grid widget on the
  dashboard, full editor at `/admin/calendar` with auto-derived events for
  posts and subscription renewals.
- **Dashboard rewrite** — system info, e-commerce-today, mail/cron health,
  recent activity, Tools & Docs links, calendar widget.
- **Admin themes** — added two new palettes (`midnight`, `forest`) on top of
  the existing `default`/`aurora`/`violet`/`sand`. Activate at `/admin/theme`.
- **Storefront** — Vue 3 SPA at `/{lang}/shop` (catalogue + cart) over the
  existing Shop REST API. CDN-loaded so it works without Vite changes.
- **PHPUnit baseline** — `phpunit.xml` + `tests/Unit/{Container,Event,Jwt,
  Money,Totp}Test.php`. Run with `composer test`.
- **Docs** — new pages: cron, mail, notifications, calendar, multicurrency,
  storefront, testing. All wired into the admin Help center under Operations.

### Added — 2026-04-26 — Platform overhaul

- **Composer integration** — optional. `composer.json` ships PSR-4 autoload + `suggest`
  packages (Stripe SDK, AWS SDK, PHPMailer, Predis). `vendor/autoload.php` is loaded
  automatically when present; manual class loader still works without Composer.
- **GraphQL endpoint** — `POST /api/v1/graphql` + interactive playground at
  `/api/v1/graphql/playground`. Self-contained executor, no Composer dependency.
  Schema covers users, pages, posts, content entries, products. Plugins extend via
  `Event::listen('graphql.schema', …)`.
- **Multi-site** — new `sites` + `site_hosts` tables, `SiteResolver::current()` picks
  the active site by `HTTP_HOST`. Existing tables got a nullable `site_id` column.
  Admin: `/admin/sites`. Default site seeded automatically — single-site installs
  keep working unchanged.
- **PageBuilder plugin** — drag-and-drop block editor over CCT. 9 built-in blocks
  (hero, columns, image, rich-text, CTA, products grid, content list, raw HTML,
  spacer). Custom blocks extend `BlockType`. Renderer at `BlockRenderer::render(...)`.
- **Help in admin** — categorized navigation with sidebar tree, search, breadcrumbs.
  Pages render directly from `docs/*.md` through the new `Markdown` class. Same
  content available in the repo at `docs/`.
- **Documentation** — full rewrite of `README.md`, new `docs/` directory with
  `index`, `getting-started`, `architecture`, `plugins`, `content-types`, `rbac`,
  `api`, `graphql`, `ecom`, `media`, `multisite`, `pagebuilder`, `cli`, `composer`.

### Added — 2026-04-26 — E-commerce plugin

- New `Ecom` plugin: physical / digital / subscription products, Stripe + PayPal
  + Manual gateways, refunds, taxes, shipping, discounts, sales dashboard,
  storefront REST API, secure digital downloads, webhook idempotency.

### Added — 2026-04-26 — Core platform

- Service container (`Container`), Hooks (`Hooks::fire`), Migrator, PluginManager
  (install/enable/disable/uninstall), JWT + refresh tokens, RBAC with audit log,
  TOTP 2FA, Revisions + Preview tokens, Storage abstraction (Local + S3),
  CLI tool `bin/sev`.



### Security
- `.env` support — DB credentials moved out of config files
- `Env` class — `.env` loader with `get()` / `require()` methods
- CSRF protection on all forms (in progress)
- Session hardening — `httpOnly`, `Secure`, `SameSite` cookies (in progress)
- Input sanitization in `Request` class (in progress)
- Rate limiting on login endpoint (in progress)
- Security headers — CSP, X-Frame-Options, X-Content-Type-Options (in progress)
- File upload MIME whitelist and directory traversal protection (in progress)
- Role-based middleware for admin routes (in progress)

---

## [1.0.0] — 2025-01-01

### Added
- Custom PHP 8.4 MVC framework (`Seven`)
- Multilingual routing — 6 languages: `en`, `ru`, `ka`, `uk`, `az`, `hy`
- Pages CRUD with multilingual JSON content fields
- Blog CRUD — posts with cover image, excerpt
- Authentication — register, login, logout, bcrypt passwords
- Admin panel — pages, posts, users
- Role system — `admin` / `user`
- Theme toggle — dark / light
- Language switcher
- RedBeanPHP 5.7.5 ORM integration
- Tailwind CSS v3 via PostCSS + Webpack
- Session management with flash messages
- File-based logger
- Password reset flow (skeleton)
- Two-factor auth fields on User model (not yet implemented)
- OAuth fields on User model (not yet implemented)
- REST API skeleton (2 endpoints)

[Unreleased]: https://github.com/theloopbreaker4-cloud/seven-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/theloopbreaker4-cloud/seven-php/releases/tag/v1.0.0
