# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

See also the workspace-level [`../.claude/CLAUDE.md`](../.claude/CLAUDE.md) for cross-project conventions (`sevenPHP` is one of three SevenCMS stacks; features ship in all three).

---

## Stack

- **PHP 8.4** in WSL Ubuntu — never call `php` from Windows directly
- **MySQL** in WSL (creds in `.env`)
- **RedBeanPHP 5.7.5** ORM, accessed through `lib/db.class.php` (`DB::execute`, `DB::getAll`, `DB::getCell`, `DB::findOne`)
- **Vue 3** loaded by Vite 6 — site SPA + admin uses server-rendered PHP with Vue islands
- **Tailwind v3** via PostCSS + Vite — never Bootstrap/Materialize
- **Composer is optional** — `lib/composerbridge.class.php` registers `vendor/autoload.php` when present, manual classloader works without it

---

## Common commands

All PHP / `bin/sev` commands run **through WSL** because the project lives on `/mnt/d/`:

```bash
# Dev server (port 8085)
wsl.exe -d Ubuntu bash -c 'cd /mnt/d/Works/SevenCMSProjects/sevenPHP && php -d opcache.enable_cli=1 -S 0.0.0.0:8085 -t public public/router.php'

# Migrations
wsl.exe -d Ubuntu bash -c 'cd /mnt/d/Works/SevenCMSProjects/sevenPHP && php bin/sev migrate'
wsl.exe -d Ubuntu bash -c 'cd /mnt/d/Works/SevenCMSProjects/sevenPHP && php bin/sev migrate:status'

# Plugins (modules/ folders are plugins — terminology is interchangeable here)
php bin/sev plugin:list
php bin/sev plugin:install <Name>     # runs migrations + onInstall hook
php bin/sev plugin:enable  <Name>
php bin/sev plugin:disable <Name>

# Cron + mail workers (point system cron at these)
php bin/sev cron:run                  # runs every job whose next_run_at <= NOW()
php bin/sev mail:send [--limit=N]     # drains mail_queue
php bin/sev ecom:bill-due             # manual-gateway subscription billing

# Scaffold
php bin/sev make:plugin <PascalName>
php bin/sev make:cct <name>
php bin/sev user:make-admin <email>

# Vite (run from Windows side, not WSL — it's a Node.js app)
npm run dev          # http://localhost:5173 with HMR for site/admin
npm run build        # production bundle into public/

# PHPUnit (pure-logic tests; no DB)
composer install
composer test                                       # all unit tests
vendor/bin/phpunit tests/Unit/JwtTest.php           # single file
vendor/bin/phpunit --filter testFormatUsdPrefixesSymbol
```

Default admin user (after install): `admin@sevencms.com / Admin123!`.

---

## Architecture

### Boot flow

`public/index.php` → loads `Env`, `Seven::createWebApp()` → `Core` → `General::__construct()` → `Module::loadAll()` → each plugin's `boot()` → `CoreJobs::register()` → router dispatches.

`Module::loadAll()` reads `storage/modules.json` for the disabled list, then `glob('modules/*/Module.php')` and instantiates each `{Name}Module` class. Disabled plugins are **skipped at boot** — they don't load routes, models, or services. The `plugins` DB table is the source of truth for install/enable state; `storage/modules.json` is a fallback for environments without DB at boot.

### Plugin lifecycle (`PluginManager`)

State machine in the `plugins` table: `uninstalled → installed → enabled ↔ disabled`. Lifecycle hooks (optional methods on the `{Name}Module` class):

- `onInstall()` — called once after migrations run on first install
- `onEnable()` / `onDisable()` — toggle state
- `onUninstall()` — leaves data tables intact by default

Plugin layout:

```
modules/MyPlugin/
├── Module.php           class MyPluginModule implements ModuleInterface
├── plugin.json          { name, version, permissions[] }
├── controllers/
├── models/
├── services/
├── migrations/*.sql     auto-run by Migrator on install
└── views/               admin views inside app/views/admin/{ctrl}/ (controller convention)
```

### Routing

Two registries in play:
1. `config/routes.php` — core routes registered with `Route::add('admin.foo', ['controller' => 'foo', 'action' => 'index', 'prefix' => 'admin'])`
2. Per-plugin routes returned from `Module::routes()` and merged in `Module::register()`

URL pattern: `/{lang}/[admin/]{controller}/{action}/{params}`. Languages: `en, ru, ka, uk, az, hy`. Controllers map by lowercase name → `app/controllers/{Name}Controller.php` or `{Name}AdminController.php` (or plugin's `controllers/`). View path: `app/views/[admin/]{ctrl}/{action}.html`.

### Database

- **Migrations** under `db/migrations/*.sql` (core) and `modules/*/migrations/*.sql` (plugins). Naming: `YYYY_MM_DD_HHMMSS_description.sql`. Run **once each, alphabetically**, tracked in `migrations` table by full ID. No automatic DDL rollback — write your own DROP if needed.
- **Schema is additive**: every refactor adds nullable columns or new tables — never breaks existing single-site/single-currency installs.
- **Money is always `int` minor units** (cents/kopeks). Never use floats for money. `Money::format($minor, $code)` to display, `Money::fromInput($string, $code)` to parse.
- `site_id` columns on multi-tenant tables (`page`, `post`, `media`, `content_entries`, `ecom_*`) are nullable — `NULL` means shared across all sites. `SiteResolver::current()` picks the active site by `HTTP_HOST`.

### Services container

`lib/container.class.php` (PSR-11-ish). Three registration styles:

```php
Container::set('thing', $instance);                    // pre-built
Container::factory('cache', fn($c) => new Cache());    // lazy + cached
Container::bind('uuid', fn() => bin2hex(random_bytes(16))); // rebuilds every get()
```

Plugins use it to expose swappable services. Notable bindings: `mailer.transport` (overrides PHP `mail()`), `ecom.fx.provider`, `ecom.cart`, `ecom.gateway.registry`.

### Events / hooks

Two compatible APIs on the same registry:

```php
Event::on('user.login', fn($data) => …);          // legacy: array payload
Event::listen('content.entry.saved', fn($obj) => …); // PSR-14-ish: any payload
Event::emit('user.login', ['id' => 7]);
Event::dispatch('content.entry.saved', $entry);
```

The `Hooks::fire(beforeCreate, $entityType, $payload)` helper dispatches namespaced events like `{entityType}.before.create`. Listeners can throw `HookAbortException` to cancel an action.

### Operations layer (added 2026-04-29)

Four cross-cutting subsystems, all opt-in, all driven by the cron scheduler:

| Subsystem | Class | Storage | Purpose |
|---|---|---|---|
| **Mail queue** | `Mailer` | `mail_queue` table | All outbound email — `Mailer::queue()`, async send via cron, exponential backoff |
| **Cron** | `CronRunner` | `cron_jobs` table | `CronRunner::register($name, $schedule, $callback)` in plugin `boot()` |
| **Notifications** | `Notify` | `notifications` table | `Notify::admins()` / `Notify::user()` — bell in admin header polls `/admin/notifications/feed.json` |
| **Calendar** | `CalendarEvent` | `calendar_events` table | `notify_at` field fires reminders via `calendar.sweep` cron |

Schedule grammar: `@minute`, `@hourly`, `@daily`, `@weekly`, `@monthly`, `*/N` (every N minutes), `HH:MM` (once a day). System cron must be wired to `php bin/sev cron:run` every minute.

### Authentication

- **Sessions** for the admin UI (PHP `Session` wrapper)
- **JWT HS256** + rotating refresh tokens for the REST API. Access TTL 15 min, refresh TTL 30 days, hashes stored in `api_refresh_tokens`
- **TOTP 2FA** (RFC 6238, own base32 encoder, recovery codes bcrypt-hashed in `user_totp.recovery_codes`)
- **RBAC** via `Permission::can($slug, $userId)` — admin role bypasses checks; `Permission::syncRoles()` to assign

### Frontend

PHP renders everything; Vue mounts as islands.

- **Site SPA**: `src/site/main.js` mounts into `<div id="app">` when controllers return an array (sets `window.__DATA__`)
- **Admin**: server-rendered PHP views in `app/views/admin/`, with small Vue islands like `data-calendar` widget and the storefront pages (CDN Vue 3) at `/{lang}/shop`
- **Theme tokens**: `--bg-primary/secondary/tertiary`, `--text-primary/secondary/tertiary`, `--border-color`, `--primary`, `--primary-hover`. Switch via `[data-theme="dark"]` and `[data-palette="aurora|violet|sand|midnight|forest"]` on `<html>`. Themes live in `src/themes/{name}/_light.scss + _dark.scss`, registered in `src/themes/_all.scss`

### Help system

Admin Help (`/admin/help`) renders `docs/*.md` through `lib/markdown.class.php`. The category tree lives in `HelpAdminController::topics()` — when adding new docs, add an entry there *and* in `docs/index.md` (the same tree is mirrored in both). Internal cross-references use `#mdlink#filename.md#anchor#` placeholders that get rewritten to `/admin/help/topic/filename#anchor`.

---

## Performance gotchas (WSL/`/mnt/d`)

The codebase runs from a Windows-hosted directory mounted into WSL via 9P. **Reads through `/mnt/d/` are 50–100× slower than WSL-native FS.** This makes a few patterns toxic:

- **Never `glob()` in plugin `boot()`** — use explicit `require_once` lists. The `glob()` cost is paid on every HTTP request.
- **Never write to the DB on every boot** if the data is unchanging — use TTL'd cache files in `storage/cache/`. `CronRunner::register()` already does this (signature cache in `cron_signatures.json`); follow the same pattern when adding plugin-boot side effects.
- **Never recurse `public/uploads/` synchronously** for dashboard-like widgets — cache the size with TTL ≥ 5 min.
- Use `php -d opcache.enable_cli=1` flag when running the built-in dev server (`php -S` is CLI-mode, OPcache off by default — but it's not the main bottleneck on WSL; the boot-time DB writes and `glob()` calls usually are).

---

## Testing

`phpunit.xml` + `tests/Unit/` cover **pure logic only** (Container, Event, Jwt, Money, Totp). Anything DB-touching needs an integration suite which isn't built yet — don't expect to mock `DB::*`. Bootstrap stubs `Logger` so test code never writes log files.

---

## Conventions

- Class files: `lib/classname.class.php` (lowercase filename, PascalCase class). Interfaces: `lib/name.interface.php`.
- Controllers: `app/controllers/{Pascal}Controller.php` for site, `{Pascal}AdminController.php` for admin, `app/apiControllers/{Pascal}V1ApiController.php` for `/api/v1/*`. API controllers extend `ApiV1Controller` (auth + rate-limit helpers).
- Views: `app/views/[admin/]{ctrl}/{action}.html` — `.html` extension despite being PHP, not negotiable.
- New tables: `INSERT IGNORE` for seed data; new columns nullable; `INDEX idx_{table}_{purpose}`. UTF8MB4 always.
- New admin pages: add to `Master.html` sidebar (groups: Content / Taxonomy / Structure / Users / Operations / System / Developer).
- New docs: write in `docs/{topic}.md`, add to `HelpAdminController::topics()` and `docs/index.md`. All `.md` content in **English** (workspace rule).
- Comments in **English**. Avoid narrating WHAT — only WHY when non-obvious (a constraint, a workaround, a perf reason).
