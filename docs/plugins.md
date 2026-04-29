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
