# Composer integration

[← Back to docs](index.md)

Composer is **optional**. SevenCMS keeps a manual class loader that walks
`lib/`, `app/`, and `modules/` so you can drop the project on shared hosting
and run it without `composer install`. When `vendor/autoload.php` exists,
SevenCMS auto-loads it on every request and CLI invocation.

## Why install Composer?

- `composer require` third-party packages (Stripe SDK, AWS SDK, PHPMailer…)
- PSR-4 autoload for plugin namespaces — `Seven\\Yourplugin\\…`
- Locked dependency versions via `composer.lock`
- Run `composer dump-autoload --optimize` for production speed

## Suggested packages

Already listed under `suggest` in `composer.json`; install only the ones you need:

```bash
composer require stripe/stripe-php          # drop-in for the bundled Stripe REST adapter
composer require aws/aws-sdk-php            # use S3Storage with R2 / S3 / MinIO
composer require phpmailer/phpmailer        # SMTP / Mailgun / Postmark transports
composer require predis/predis              # Redis cache fallback when ext-redis is missing
```

## Production install

```bash
composer install --no-dev --optimize-autoloader
```

The post-install hook automatically runs `php bin/sev migrate` so a fresh
checkout becomes a working install in one command.

## Plugin namespace

Drop your namespaced classes anywhere under `modules/{Name}/` — by default
they're resolved through the classmap autoloader, which scans every `*.php`
under `modules/`. If you'd rather use PSR-4:

```jsonc
// composer.json
{
  "autoload": {
    "psr-4": {
      "Seven\\": "src/Seven/",
      "Acme\\Reviews\\": "modules/Reviews/src/"
    }
  }
}
```

After editing `composer.json`, run:

```bash
composer dump-autoload
```

---

[← Back to docs](index.md)
