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

## License

ISC — see [LICENSE](LICENSE).
