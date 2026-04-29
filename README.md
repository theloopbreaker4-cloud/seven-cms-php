# SevenCMS

<p align="center">
  <img src="public/brand.svg" width="96" alt="SevenCMS logo" />
</p>

<p align="center">
  <strong>Modular PHP CMS</strong> — plugins, custom content types, RBAC, REST + GraphQL APIs,
  full e-commerce, multi-site, drag-and-drop page builder.
</p>

<p align="center">
  <a href="docs/getting-started.md">Getting started</a> ·
  <a href="docs/architecture.md">Architecture</a> ·
  <a href="docs/plugins.md">Plugins</a> ·
  <a href="docs/content-types.md">Content types</a> ·
  <a href="docs/rbac.md">RBAC</a> ·
  <a href="docs/api.md">REST API</a> ·
  <a href="docs/graphql.md">GraphQL</a> ·
  <a href="docs/ecom.md">E-commerce</a> ·
  <a href="docs/multisite.md">Multi-site</a> ·
  <a href="docs/pagebuilder.md">Page builder</a> ·
  <a href="docs/cli.md">CLI</a>
</p>

---

## What you get

| Area               | Highlights                                                                                  |
|--------------------|---------------------------------------------------------------------------------------------|
| **Core**           | Hand-rolled MVC + RedBeanPHP, Twig-free PHP templates, opt-in Composer autoloader           |
| **Plugins**        | install / enable / disable / uninstall lifecycle, per-plugin migrations & permissions       |
| **Content Types**  | UI-driven schema builder; 13 field types incl. relations, repeaters, rich text             |
| **RBAC**           | Roles × permissions matrix, audit log, 2FA (TOTP), JWT API + refresh tokens                 |
| **Media**          | Drag-and-drop multi-upload, folders, image variants (WebP), Local + S3 drivers              |
| **E-commerce**     | Physical / digital / subscription products, Stripe + PayPal, refunds, discounts, taxes      |
| **Multi-site**     | One install, many domains; per-site theming, content scoping, settings                      |
| **Page builder**   | Drag-and-drop block editor over CCT — heroes, columns, products, custom blocks              |
| **APIs**           | REST `v1` + GraphQL endpoint, both auto-documented                                          |
| **Tooling**        | `bin/sev` CLI (migrate, plugin:*, make:*), revisions, preview tokens, activity log          |

---

## Install

### Quick (zero-dependency)

```bash
git clone https://github.com/your-org/sevencms.git
cd sevencms
cp .env.example .env
# edit .env: DB credentials, BASE_URL, JWT_SECRET
mysql -u root -p sevencms < db/migrations/2026_04_26_000001_create_plugins_table.sql
php bin/sev migrate
php -S localhost:8085 -t public public/router.php
```

Open `http://localhost:8085`. The **Setup wizard** runs on first request and seeds an admin user.

### With Composer (recommended for production)

```bash
composer install --no-dev --optimize-autoloader
php bin/sev migrate
php bin/sev plugin:install Content
php bin/sev plugin:install Ecom
```

Composer is **optional** — SevenCMS ships a manual class loader so the project runs on
shared hosts without `composer install`. When `vendor/autoload.php` exists,
SevenCMS registers it automatically and you can `composer require` Stripe / AWS / PHPMailer.

See [docs/composer.md](docs/composer.md) for the full Composer guide.

---

## First five minutes

1. Sign in as the admin you created in setup.
2. Open **Plugins** → install `Content`, `Ecom`, and any others you want.
3. Open **Roles & Permissions** → tune what editors and authors can do.
4. Open **Content Types** → click *New type*, give it a slug, add fields.
5. Open **Help** in the admin sidebar — every feature has an in-app guide that mirrors `docs/`.

---

## Documentation map

```
docs/
├── index.md              Table of contents
├── getting-started.md    Install / configure / first steps
├── architecture.md       How everything fits together
├── plugins.md            Writing your own plugin
├── content-types.md      Custom content types and fields
├── rbac.md               Roles, permissions, 2FA
├── api.md                REST API v1 + JWT + refresh
├── graphql.md            GraphQL schema and queries
├── ecom.md               Catalog, payments, subscriptions, webhooks
├── multisite.md          Running multiple sites from one codebase
├── pagebuilder.md        Drag-and-drop blocks
├── cli.md                Every `bin/sev` command
└── composer.md           Composer integration and recommended packages
```

The same content is reachable in the admin panel under **Help** — see
[`HelpAdminController`](app/controllers/HelpAdminController.php).

---

## Folder layout

```
sevencms/
├── bin/sev                # CLI tool
├── public/                # Web root (index.php, brand.svg, uploads, …)
├── lib/                   # Core classes (Container, Migrator, JWT, Hooks, …)
├── app/
│   ├── controllers/       # Site + admin controllers
│   ├── apiControllers/    # REST API controllers
│   ├── models/            # Core models (User, Page, Post, …)
│   ├── middleware/        # AuthMiddleware, GuestMiddleware
│   └── views/admin/       # Admin templates
├── modules/               # Plugins (Pages, Blog, Media, Content, Ecom, …)
├── db/migrations/         # Core SQL migrations
├── config/                # routes.php, db.config.php, common.config.php
├── docs/                  # Markdown documentation (this index above)
├── lang/                  # UI language packs
├── storage/               # Cache, sessions, logs (writable)
└── composer.json          # Optional Composer setup
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Quick rules:

- Follow PSR-12 coding style; run `composer cs:fix`.
- Each new plugin lives under `modules/{Name}/` and ships a `plugin.json`.
- Each new feature ships a doc file under `docs/{feature}.md` **and** an admin help
  page under `app/views/admin/help/{feature}.html`.

---

## Project status & credits

SevenCMS is an **active, open-source project under continuous development**. I want to
be upfront about how it's built so you can decide whether it fits your use case.

### Who works on this

- **Maintainer & author:** [@theloopbreaker4-cloud](https://github.com/theloopbreaker4-cloud)
  — wrote the original codebase, owns architectural decisions, reviews and tests every
  change before it ships.
- **AI-assisted development:** large portions of the recent codebase (refactors, new
  modules, security hardening, UI components, validation engine, custom select, CSRF
  guard, SVG sanitizer, error pages, form validation, etc.) were written together with
  [Claude](https://www.anthropic.com/claude) acting as a pair-programmer. I direct the
  work, review the diffs, and take responsibility for what lands on `master`.

### What this means for you

- **Read the code before you trust it in production.** Every line is reviewed by a
  human, but a CMS is a large surface area. If you're shipping SevenCMS publicly,
  audit at least: authentication (`lib/auth.class.php`, `app/apiControllers/AuthV1ApiController.php`),
  CSRF (`lib/csrf.class.php` + `lib/general.class.php`), file upload sanitization
  (`lib/svgsanitizer.class.php`, `modules/Media/`), and the global request guard.
- **Security testing is ongoing.** I run manual checks against the OWASP Top 10
  (CSRF, XSS, SQL injection, open redirects, file upload, broken auth, rate limiting)
  on every release. There is no formal third-party audit yet — if you find a real
  issue, please open a security advisory on GitHub instead of a public issue.
- **Tests cover pure logic only.** `tests/Unit/` validates Container, Event, JWT,
  Money, TOTP. The integration suite (DB-touching) is on the roadmap but not built
  yet, so end-to-end behavior is verified by hand on every change.
- **Expect breaking changes** until 1.0. The schema is additive (we don't drop
  columns), but APIs and plugin contracts may shift.

### Why I'm telling you this

You should know who actually wrote the thing you're running. Not disclosing AI
involvement is becoming common — I'd rather be honest. If that means you choose a
more battle-tested CMS, that's a fair call. If you want to help test, audit, or
contribute fixes — pull requests are very welcome.

## License

ISC — see [LICENSE](LICENSE).
