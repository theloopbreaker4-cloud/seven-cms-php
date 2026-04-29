# SevenCMS Architecture

This document describes the platform extensions added on top of the original
sevenPHP core (controller / view / model + RedBeanPHP). Nothing existing was
removed — everything below is additive.

---

## 1. Bootstrap & Service Container

`lib/container.class.php` is a small PSR-11–style service container:

```php
Container::set('db', DB::instance());
Container::factory('cache',   fn()  => new Cache());
Container::singleton('media', fn($c) => new MediaService($c->get('db')));
Container::bind('uuid', fn() => bin2hex(random_bytes(16)));   // rebuilds every call

$cache = Container::get('cache');
```

Use it inside plugin `boot()` methods to register reusable services so other
modules (or the admin UI) can resolve them without hard imports.

## 2. Plugins

Plugins live under `modules/{Name}/` and ship a `Module.php` that implements
`ModuleInterface`. The `PluginManager` (`lib/pluginmanager.class.php`) tracks
their install state in the `plugins` table:

| status        | meaning                                             |
|---------------|-----------------------------------------------------|
| uninstalled   | discovered on disk, never installed                 |
| installed     | rows persisted, currently disabled                  |
| enabled       | active — routes, hooks, models all loaded           |
| disabled      | rows kept, but routes/hooks not registered          |

Lifecycle hooks (optional methods on the Module class):
`onInstall`, `onEnable`, `onDisable`, `onUninstall`.

Each plugin should ship:

```
modules/MyPlugin/
├── Module.php
├── plugin.json                     # name, version, description, permissions
├── controllers/
├── models/
└── migrations/                     # *.sql files run by Migrator on install
```

Manage via CLI: `php bin/sev plugin:install MyPlugin`

## 3. Migrations

`lib/migrator.class.php` — lightweight runner. SQL files discovered from:

- `db/migrations/*.sql` — core
- `modules/{Name}/migrations/*.sql` — preferred per-plugin location
- `modules/{Name}/migration.sql` — legacy single file

Tracked in the `migrations` table with `(migration, batch, applied_at)`.
Run with `php bin/sev migrate`. Rollback the last batch with
`php bin/sev migrate:rollback` (note: tables are not auto-dropped — supply your
own `DROP` statements if you need destructive rollback).

## 4. Hooks (Event System)

Standardized lifecycle events fire through `Hooks::fire`:

```php
Hooks::fire(Hooks::BEFORE_CREATE, 'page', $payload);
Hooks::fire(Hooks::AFTER_UPDATE,  'page', $page);
```

These dispatch via `Event::dispatch` under names `{entity}.{event}` plus a
catch-all `any.{event}`. Listeners subscribe with `Event::listen('page.afterCreate', ...)`.

Use `Hooks::fireOrAbort` for `before*` events when listeners may veto the
operation by throwing `HookAbortException`.

## 5. Custom Content Types

The `Content` plugin (modules/Content) lets administrators define entity
schemas at runtime:

- `content_types` — type definition
- `content_fields` — schema rows (text, richtext, number, boolean, image, media,
  select, multiselect, date, datetime, relation, repeater, json)
- `content_entries` — actual data rows; typed values stored in JSON `data`
- `content_relations` — pivot for relation field type

Admin UI lives at `/admin/content/types` and `/admin/content/entries/{slug}`.
Public read API: `GET /api/v1/content/{slug}` and `GET /api/v1/content/{slug}/{entrySlug}`.

## 6. RBAC

Tables: `roles`, `permissions`, `role_permissions`, `user_roles`. Helper:

```php
Permission::can('content.update');                 // current user
Permission::can('content.update', $userId);
Permission::syncRoles($userId, ['editor']);
```

Admins (legacy `users.role = 'admin'` OR membership in role slug `admin`) bypass
all checks. Permissions are `{module}.{action}` strings; plugins extend the
catalog via SQL seeds in their migrations.

Admin UI: `/admin/roles`.

## 7. Revisions & Preview

- `Revisions::snapshot('content_entries', $id, $data, $meta, $comment)` — append a
  snapshot to the `revisions` table.
- `Revisions::restore($revisionId)` — returns the snapshot data (caller persists).
- `PreviewToken::create('content_entries', $id, 3600)` returns a stateless
  HMAC-signed token; verify with `PreviewToken::verify($token)`.

## 8. Activity Log

`ActivityLog::log('media.upload', 'media', $id, "Uploaded {$file}")` writes an
append-only row with user id, IP, user-agent. View at `/admin/activity`.

## 9. API v1

JWT-based authentication with rotating refresh tokens. Versioned under
`/api/v1/*`. Base controller: `lib/apiv1controller.class.php` adds
`requireAuth`, `requirePermission`, rate limiting, and `paginate`.

| Endpoint                              | Auth      | Description                  |
|---------------------------------------|-----------|------------------------------|
| `POST /api/v1/auth/login`             | public    | Email + password             |
| `POST /api/v1/auth/refresh`           | refresh   | Rotates refresh token        |
| `POST /api/v1/auth/logout`            | optional  | Revokes refresh token        |
| `GET  /api/v1/auth/me`                | bearer    | Current user + permissions   |
| `GET  /api/v1/content/types`          | public    | List active content types    |
| `GET  /api/v1/content/{slug}`         | public    | List published entries       |
| `GET  /api/v1/content/{slug}/{entry}` | public/preview | Single entry            |
| `POST /api/v1/content/{slug}`         | content.create | Create entry            |
| `PUT  /api/v1/content/{slug}/{id}`    | content.update | Update entry            |
| `DELETE /api/v1/content/{slug}/{id}`  | content.delete | Delete entry            |

Access tokens live 15 min. Refresh tokens live 30 days, are rotated on every
use, and are stored as SHA-256 hashes in `api_refresh_tokens`.

## 10. Media

- Drag-and-drop multi-upload UI at `/admin/media`
- Folder organization (`media_folder` table, denormalized `path`)
- Image variants (thumb / medium / large / full WebP) generated by
  `MediaProcessor` using GD
- Storage abstraction via `StorageDriver` interface — `LocalStorage` ships out of
  the box; `S3Storage` works when `aws/aws-sdk-php` is installed (Composer)
- Validation by content sniffing (`finfo`) + 25 MB cap

## 11. 2FA

`Totp` (`lib/totp.class.php`) is a self-contained RFC-6238 implementation. The
admin user can enable/disable from `/admin/2fa`. TOTP secrets, recovery codes
(bcrypt-hashed), and the enabled flag live in `user_totp`.

## 12. CLI

`bin/sev` exposes the most common operations:

```
php bin/sev migrate
php bin/sev migrate:status
php bin/sev plugin:list
php bin/sev plugin:install Content
php bin/sev plugin:enable Media
php bin/sev make:plugin MyPlugin
php bin/sev make:cct Recipe
php bin/sev user:make-admin admin@sevencms.com
php bin/sev cache:clear
```

## 13. Folder layout (additive)

```
sevenPHP/
├── bin/sev                          # CLI tool
├── db/migrations/                   # core SQL migrations
│   ├── 2026_04_26_000001_create_plugins_table.sql
│   ├── 2026_04_26_000002_create_content_types.sql
│   ├── 2026_04_26_000003_create_revisions.sql
│   ├── 2026_04_26_000004_create_rbac.sql
│   ├── 2026_04_26_000005_create_activity_log.sql
│   └── 2026_04_26_000006_create_api_tokens.sql
├── lib/
│   ├── container.class.php          # service container
│   ├── migrator.class.php
│   ├── pluginmanager.class.php
│   ├── hooks.class.php
│   ├── jwt.class.php
│   ├── refreshtoken.class.php
│   ├── totp.class.php
│   ├── apiv1controller.class.php
│   ├── permission.class.php
│   ├── activitylog.class.php
│   ├── revisions.class.php
│   ├── previewtoken.class.php
│   ├── localstorage.class.php
│   ├── s3storage.class.php
│   └── storage.interface.php
├── modules/
│   ├── Content/                     # Custom Content Types plugin
│   ├── Media/                       # extended in this update
│   └── …
├── app/
│   ├── controllers/
│   │   ├── ModuleAdminController.php  # rewritten on top of PluginManager
│   │   ├── RoleAdminController.php    # NEW
│   │   ├── ActivityAdminController.php# NEW
│   │   └── TwoFactorAdminController.php # NEW
│   ├── apiControllers/
│   │   ├── AuthV1ApiController.php    # NEW
│   │   └── ContentV1ApiController.php # NEW
│   └── views/admin/
│       ├── activity/index.html
│       ├── content/{types,entries}/…
│       ├── plugins/index.html
│       ├── rbac/{index,edit}.html
│       └── users/twofactor.html
└── ARCHITECTURE.md                  # this file
```

---

## Writing a plugin

```bash
php bin/sev make:plugin MyPlugin
```

Edit `modules/MyPlugin/Module.php`:

```php
class MyPluginModule implements ModuleInterface
{
    public function getName(): string { return 'MyPlugin'; }

    public function boot(): void
    {
        Container::singleton('myplugin.service', fn() => new MyPluginService());
        Event::listen('content.afterCreate', [MyPluginListener::class, 'onContentCreated']);
    }

    public function onInstall(): void
    {
        // Add custom permissions, seed defaults, …
    }

    public function routes(): array
    {
        return [
            'admin.myplugin' => ['controller' => 'myplugin', 'action' => 'index', 'prefix' => 'admin'],
        ];
    }
}
```

Drop SQL into `modules/MyPlugin/migrations/` and run:

```bash
php bin/sev plugin:install MyPlugin
```

## Defining a content type

Either through `/admin/content/types/create` or via SQL seed:

```sql
INSERT INTO content_types (slug, name, icon) VALUES ('recipe', 'Recipe', 'utensils');
INSERT INTO content_fields (type_id, `key`, label, field_type, sort_order) VALUES
  (LAST_INSERT_ID(), 'title',     'Title',      'text',     0),
  (LAST_INSERT_ID(), 'body',      'Body',       'richtext', 1),
  (LAST_INSERT_ID(), 'cover',     'Cover',      'image',    2),
  (LAST_INSERT_ID(), 'minutes',   'Minutes',    'number',   3),
  (LAST_INSERT_ID(), 'tags',      'Tags',       'multiselect', 4);
```

Entries appear immediately at `/admin/content/entries/recipe`.
