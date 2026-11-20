# Plugins

[← Back to docs](index.md)

## Lifecycle

Every directory under `modules/` that contains a `Module.php` implementing
`ModuleInterface` is a plugin. Plugins move through these states:

| State         | Meaning                                                 |
|---------------|---------------------------------------------------------|
| `uninstalled` | Discovered on disk; never installed                     |
| `installed`   | Rows persisted (currently disabled)                     |
| `enabled`     | Live: routes, hooks, models loaded                      |
| `disabled`    | Files kept, but routes/hooks not registered             |

Lifecycle hooks (all optional methods on the Module class):

```php
public function onInstall(): void   {}
public function onEnable(): void    {}
public function onDisable(): void   {}
public function onUninstall(): void {}
```

## Writing a plugin

```bash
php bin/sev make:plugin Reviews
```

Scaffolds:

```
modules/Reviews/
├── Module.php
├── plugin.json
├── controllers/
├── models/
└── migrations/
    └── 2026_04_26_000000_init.sql
```

Edit `Module.php`:

```php
<?php

defined('_SEVEN') or die('No direct script access allowed');

class ReviewsModule implements ModuleInterface
{
    public function getName(): string { return 'Reviews'; }

    public function boot(): void
    {
        Container::singleton('reviews.repository', fn() => new ReviewRepository());
        Event::listen('content.afterCreate', [ReviewListener::class, 'onContentCreated']);
    }

    public function onInstall(): void
    {
        // Add custom permissions, seed defaults, schedule cron, …
        DB::execute(
            'INSERT IGNORE INTO permissions (slug, module, action, description)
             VALUES ("reviews.moderate", "reviews", "moderate", "Approve / hide reviews")'
        );
    }

    public function routes(): array
    {
        return [
            'admin.reviews'        => ['controller' => 'reviews', 'action' => 'index',   'prefix' => 'admin'],
            'admin.reviews.moderate' => ['controller' => 'reviews', 'action' => 'moderate', 'prefix' => 'admin'],
        ];
    }
}
```

### How a route name becomes a URL

Without a `path`, the dotted name **is** the path: `admin.reviews.moderate` answers
`/{lang}/admin/reviews/moderate/{params…}`; the longest matching name wins and the
leftover segments arrive as `$params[0]`, `$params[1]`, ….

For REST endpoints give an explicit `path` with `{placeholders}` and a `method`:

```php
'api.v1.reviews.update' => [
    'controller' => 'reviewsV1', 'action' => 'update', 'prefix' => 'api/v1',
    'path' => 'api/v1/reviews/{id}', 'method' => ['PUT', 'PATCH'],
],
```

Path routes are tried first and only for their methods; placeholders fill
`$params` in order. When two paths fit, the one with more literal segments wins
(`reviews/featured` beats `reviews/{id}`).

### Models

A model's table defaults to its lowercased class name. If yours differs, say so —
otherwise RedBean's fluid mode quietly creates a look-alike table and your rows
end up there:

```php
class Review extends Model
{
    protected string $tableName = 'reviews_items';
}
```

Tables with underscores are saved with plain SQL (RedBean can't dispense such
bean types); only properties that have a matching snake_case column are written.

**Every table needs a migration.** In production the schema is frozen
(`DB_FREEZE`, default on outside dev): RedBean won't create tables or columns, and
saving a property without a column fails loudly. In dev it stays fluid so you can
prototype — then write the `CREATE TABLE IF NOT EXISTS` before shipping. Give text
that holds JSON for several languages `TEXT`/`MEDIUMTEXT`, counters `INT`.

Migrations may use `ADD COLUMN IF NOT EXISTS`, `MODIFY COLUMN IF EXISTS` and
`CREATE INDEX IF NOT EXISTS` (one action per statement); the migrator makes them
work on MySQL 8 as well as MariaDB.

Drop your migrations into `modules/Reviews/migrations/*.sql` and install:

```bash
php bin/sev plugin:install Reviews
```

## plugin.json

```json
{
  "name": "Reviews",
  "version": "0.1.0",
  "description": "Customer reviews for products and content.",
  "author": "you@example.com",
  "requires": {
    "core": ">=1.0.0",
    "Ecom": ">=1.0.0"
  },
  "permissions": ["reviews.view", "reviews.moderate"],
  "settings": {
    "auto_approve": false,
    "min_chars":    20
  }
}
```

The admin reads `permissions` and `settings` to render plugin metadata in
**Plugins** → click *Permissions* under each plugin card.

## Hook reference

### Generic CRUD (per entity)

| Event                         | Payload                              |
|-------------------------------|--------------------------------------|
| `{entity}.beforeCreate`       | The model about to be created        |
| `{entity}.afterCreate`        | The persisted model                  |
| `{entity}.beforeUpdate`       | The model with new values applied    |
| `{entity}.afterUpdate`        | The persisted model                  |
| `{entity}.beforeDelete`       | The model about to be deleted        |
| `{entity}.afterDelete`        | The deleted model (still in memory)  |
| `any.{event}`                 | `['entity' => ..., 'payload' => ...]` (catch-all) |

### Plugin lifecycle

| Event                  | Payload                |
|------------------------|------------------------|
| `plugin.installed`     | `['name' => 'Reviews']`|
| `plugin.uninstalled`   | same                   |
| `plugin.enabled`       | same                   |
| `plugin.disabled`      | same                   |

### Domain events (selected)

| Event                          | Payload                                          |
|--------------------------------|--------------------------------------------------|
| `media.afterCreate`            | `Media`                                          |
| `media.afterDelete`            | `['id' => int]`                                  |
| `auth.login` / `auth.logout`   | `User` row or `['userId' => int]`                |
| `ecom.order.created`           | `Order`                                          |
| `ecom.order.paid`              | `Order`                                          |
| `ecom.order.cancelled`         | `['order' => Order, 'reason' => string\|null]`   |
| `ecom.subscription.renewed`    | `Subscription`                                   |
| `ecom.subscription.cancelled`  | `Subscription`                                   |
| `ecom.download.granted`        | `['order' => Order, 'product' => Product, 'tokens' => array]` |

---

[← Back to docs](index.md)
