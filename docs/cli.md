# CLI — `bin/sev`

[← Back to docs](index.md)

```bash
php bin/sev <command> [args]
```

## Commands

### Migrations

```bash
php bin/sev migrate                # apply pending migrations
php bin/sev migrate:status         # list applied + pending
php bin/sev migrate:rollback       # roll back the most recent batch (no DROPs)
```

### Plugins

```bash
php bin/sev plugin:list                    # discover all + show status
php bin/sev plugin:install   <Name>        # run migrations + onInstall hook
php bin/sev plugin:enable    <Name>        # set status=enabled (+ onEnable)
php bin/sev plugin:disable   <Name>        # set status=disabled
php bin/sev plugin:uninstall <Name>        # set status=uninstalled (data kept)
```

### Scaffolding

```bash
php bin/sev make:plugin <Name>             # create modules/<Name>/ skeleton
php bin/sev make:cct    <Name>             # add a content_types row
```

### Maintenance

```bash
php bin/sev user:make-admin <email>        # promote to admin role
php bin/sev cache:clear                    # flush cache (file/redis)
```

## Output style

Green = success, yellow = pending / info, red = error. Exit code 0 on
success, 1 on any failure.

## Composer hooks

`composer.json` runs `php bin/sev migrate` after `install` and `update`,
so deployments only need:

```bash
composer install --no-dev --optimize-autoloader
```

---

[← Back to docs](index.md)
