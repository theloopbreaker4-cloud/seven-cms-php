# Changelog

All notable changes to SevenCMS PHP are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) — [Semantic Versioning](https://semver.org/)

---

## [Unreleased]

### Fixed

- The admin menu had no links to Content types, the Shop (products, orders, customers,
  subscriptions, discounts, currencies, settings), Roles, Sites or Languages — the pages
  worked but could only be reached by typing the URL. Plugin sections show up when the
  plugin is enabled.

---

## [2.1.0] — 2026-11-22

### Added

- `demo:seed` also creates shop categories and products (physical, digital, monthly
  subscription) and a "Team" content type with entries, so every demo area has data.

### Fixed

- **Storefront pages were broken in production**: the catalog and cart loaded Vue from a CDN
  with in-DOM templates (blocked by the CSP, which allows neither the CDN nor `eval`), and the
  product page called a method that doesn't exist (500). Catalog, product and cart + checkout are
  now compiled pages of the Vue site (`src/site/pages/Shop*.vue`); the site menu links the shop.
- Sub-path installs: links inside rich text delivered as JSON (pages and posts in the Vue
  site) now get the prefix too.

---

## [2.0.0] — 2026-11-22

Everything since 1.0.0: plugins, content types, page builder, e-commerce, multi-site, REST +
GraphQL, RBAC with 2FA, nine admin languages, a public demo mode, sub-path installs, and a
security and correctness pass over the whole codebase. **Breaking:** Modules are now Plugins,
the Vue site uses `/api/v1` with an httpOnly session cookie, and PHP 8.1 is the minimum.

### Added — 2026-11-21 — Sub-path installs

- Install under a path next to another site (`BASE_PATH=/cms`, or a `BASE_URL` with a path).
  The prefix is applied at the edges of each request — stripped before routing, added to
  redirects, HTML URLs, inline-script and JSON app paths and cookie paths; bundles read it from
  `<meta name="seven-base">`. Nothing changes when `BASE_PATH` is empty. See `docs/subpath.md`.

### Fixed — 2026-11-21

- Guests opening an admin page were sent to the site login instead of the admin sign-in.
- The notification bell never loaded (its `feed.json` URL never matched the route).
- The UI kit page imported a dev-only source path, so its demos broke in production.
- Admin elements with inline `style` attributes lost their styling under the CSP
  (`style-src-attr 'unsafe-inline'` allows attributes only; scripts still need a nonce).
- The PHP version check said 7.4; the code needs 8.1.

### Security — 2026-11-22

- Preview links were signed with the default key `change-me` when `JWT_SECRET` was not set,
  so anyone could forge a link to unpublished content. They now refuse to work without a
  32+ character secret, like API tokens (`php bin/sev key:generate`).

### Fixed — 2026-11-20 — Shop and headless content were not working

- **Cart API always 500** — `CartService::current()` returned a RedBean bean from an
  `array` function. Fixed; the Vue storefront's cart works again.
- **REST paths never matched** — `POST /api/v1/shop/cart/items`, `PUT|DELETE
  /cart/items/{id}`, `GET /shop/products/{slug}`, `/shop/orders/{number}`,
  `/shop/subscriptions/{id}/cancel`, `/api/v1/content/{type}[/{slug|id}]` were
  swallowed by shorter route names (add-to-cart silently did nothing). Routes now
  accept an explicit `path` with `{placeholders}` and a `method`.
- **Checkout wrote orders to the wrong table** — models whose table differs from the
  class name (Order, EcomCustomer, Subscription, Discount, Product, ProductVariant,
  ContentType, ContentField, ContentEntry, MediaFolder) were saved by RedBean's fluid
  mode into auto-created `order`, `ecomcustomer`, … tables; checkout then failed on
  the order-items foreign key. Models declare `$tableName`; underscore tables are
  written with SQL. `php bin/sev db:orphans` lists/drops the leftover tables.
- **Model fields silently empty** — `setModel()` only matched exact keys, so models
  built from `DB::getAll()` rows lost every snake_case column (`basePrice` was 0 in
  the catalog, `createdAt` empty, …). Both spellings are matched now.
- **Content entry update inserted a copy** instead of updating.

### Changed — 2026-11-20 — Schema owned by migrations, frozen in production

- Migrations now create every table (`token`, `slug`, `language`, `adminlang`,
  `post`, `category`, `tag`, `posttag`, `page`, `menu`, `menuitem`, `sociallink`,
  `user`) and repair the column types fluid mode had guessed: post/page title and
  content were `VARCHAR(191)` for multilingual JSON, `post.view_count` was
  `TINYINT` (the `+1` broke after 255 views), `sort_order` columns `TINYINT`.
- The RedBean schema is frozen unless `DB_FREEZE=false` (default: frozen outside dev).
- Migrations: `ADD COLUMN IF NOT EXISTS` / `MODIFY COLUMN IF EXISTS` /
  `CREATE INDEX IF NOT EXISTS` work on MySQL 8 too (were MariaDB-only, so a MySQL
  install failed in `create_sites`).

### Fixed — 2026-11-20 — Silent SQL failures

- RedBean's fluid mode answered any raw query naming a missing column with an empty
  result — writes were silently lost. `Db` now logs it (`logs/db`, level error) and
  throws in dev. Missing *tables* stay quiet (fluid creates them on first save).
- The public demo's hourly reset deleted the visitor login: the baseline had no
  demo user and `demo:init` inserted into a column that didn't exist (silently).
  `demo:reset` now re-creates the user, inserting only existing columns.

### Security — 2026-11-20 — Site login

- The site login returned the session token in JSON and the Vue site wrote it to a
  script-readable cookie — any XSS could take over an admin session. The server now
  sets an `httpOnly` cookie and never returns the token.
- Site login (`/api/auth/login`) had no brute-force limit; it now shares the
  5-per-15-minutes limiter.

### Changed — 2026-11-20

- The Vue site talks only to `/api/v1` (`/session`, `/account/*`, `/posts`, `/pages`,
  `/posts/{id}/comments`, `/social/links`). Old `/api/*` URLs still answer.
- Legacy content endpoints (`/api/post|page|user/*`) accept the v1 JWT; the list
  endpoints were always `500` (protected `setModel()` called from outside).

### Added — 2026-11-20

- Admin UI in Azerbaijani and Armenian (all 9 languages complete).

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
- `.env` support — DB credentials moved out of config files (`Env::get()` / `require()`)
- CSRF tokens on every admin form and state-changing `fetch()`; no state changes over GET
- Session cookies `httpOnly`, `Secure`, `SameSite=Strict`; session tokens stored as SHA-256
- Login rate limits (5 per 15 minutes per IP) for admin, site and API
- CSP with per-request nonces, X-Frame-Options, X-Content-Type-Options, HSTS
- Uploads: MIME + extension allowlist, no PHP in `/uploads`, SVG sanitizer
- Allowlist HTML sanitizer for rich text (closes stored XSS)
- Role-based middleware and permission checks on admin routes

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

[Unreleased]: https://github.com/theloopbreaker4-cloud/seven-cms-php/compare/v2.1.0...HEAD
[2.1.0]: https://github.com/theloopbreaker4-cloud/seven-cms-php/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/theloopbreaker4-cloud/seven-cms-php/releases/tag/v2.0.0
[1.0.0]: https://github.com/theloopbreaker4-cloud/seven-cms-php/commits/master
