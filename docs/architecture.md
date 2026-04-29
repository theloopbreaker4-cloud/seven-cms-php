# Architecture

[← Back to docs](index.md)

## Overview

SevenCMS is an MVC application with a thin core and rich plugin layer.
Everything user-facing — Pages, Blog, Media, Content Types, Ecom — is a plugin
under `modules/`. The core supplies routing, the request/response cycle, the
service container, hooks, RBAC, and the admin shell.

```
┌────────────────────────────────────────────────────────────────────┐
│                        public/index.php                            │
│  Env → Composer bridge → manual classloader → Seven::createWebApp  │
└────────────────────────────────────────────────────────────────────┘
                              │
                ┌─────────────┴─────────────┐
                ▼                           ▼
        ┌──────────────┐           ┌────────────────┐
        │   Router     │           │  PluginManager │
        │  (config/    │           │   modules/*    │
        │   routes.php)│           │  + DB lifecycle│
        └──────────────┘           └────────────────┘
                │                           │
                ▼                           ▼
        ┌────────────────────────────────────────┐
        │     Controller dispatch (admin/api)    │
        │     ├─ ApiV1Controller → JWT + RBAC    │
        │     └─ Controller     → CSRF + session │
        └────────────────────────────────────────┘
                │
                ▼
        ┌────────────────────────────────────────┐
        │   Models / Services / Hooks / Events   │
        │   ├─ DB (RedBean)                      │
        │   ├─ Container                         │
        │   ├─ Hooks::fire(beforeCreate, …)      │
        │   └─ Event::dispatch(name, payload)    │
        └────────────────────────────────────────┘
```

## Service container

`Container` (`lib/container.class.php`) is a tiny PSR-11-style registry.

```php
// In a plugin's boot()
Container::set('greeter',     new Greeter());
Container::singleton('cache', fn()  => new Cache());
Container::factory('cache.fresh', fn($c) => new Cache($c->get('redis')));  // cached after 1st call
Container::bind('uuid', fn() => bin2hex(random_bytes(16)));               // rebuilt every time

// Anywhere
$cache = Container::get('cache');
```

Use the container to expose services other plugins might want to swap:

```php
Container::singleton('ecom.mailer', fn() => new SmtpMailer($config));
```

## Hooks

`Hooks::fire($event, $entity, $payload)` dispatches `{entity}.{event}` plus a
catch-all `any.{event}` through the underlying `Event` system.

Standard events:

```
beforeCreate / afterCreate
beforeUpdate / afterUpdate
beforeDelete / afterDelete
```

Listeners subscribe via `Event::listen('content.afterCreate', $callable)`.
Throw `HookAbortException` from a `before*` listener to cancel the operation.

## Migrations

`Migrator` runs SQL files from:

- `db/migrations/*.sql` — core
- `modules/{Plugin}/migrations/*.sql` — per-plugin

Each file runs **once**, tracked in the `migrations` table. The CLI:

```bash
php bin/sev migrate
php bin/sev migrate:status
php bin/sev migrate:rollback   # drops the most recent batch from the tracking table
```

Migrations are not automatically reversible — provide your own `DROP` statements
if needed.

## Storage abstraction

The `StorageDriver` interface decouples files from disk:

```php
interface StorageDriver {
    public function put(string $path, string $contents, string $mime = '...'): string;
    public function get(string $path): ?string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
    public function url(string $path): string;
}
```

Two drivers ship in core:

- `LocalStorage` — `public/uploads/...`
- `S3Storage`    — Cloudflare R2, AWS S3, MinIO; needs Composer `aws/aws-sdk-php`

Swap globally with `STORAGE_DRIVER=s3` in `.env`.

## Composer integration

Composer is **optional**. SevenCMS keeps a manual class loader so you can drop
the project on a shared host without `composer install`. When `vendor/autoload.php`
exists, the bootstrap loads it automatically — see [composer.md](composer.md).

---

[← Back to docs](index.md)
