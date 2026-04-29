# Testing

[← Back to docs](index.md)

A baseline PHPUnit suite ships under `tests/`. It covers pure logic only —
nothing in the suite touches MySQL or the HTTP layer.

## Running

```bash
composer install        # installs phpunit + cs-fixer (require-dev)
composer test           # alias for `phpunit`
```

Or directly:

```bash
vendor/bin/phpunit
vendor/bin/phpunit tests/Unit/JwtTest.php
vendor/bin/phpunit --filter testFormatUsdPrefixesSymbol
```

## What's covered

| Suite        | Class               | Notes                          |
|--------------|---------------------|--------------------------------|
| `Unit`       | `Container`         | set / factory / bind / has     |
| `Unit`       | `Jwt`               | sign + verify, expiry, tampering |
| `Unit`       | `Totp`              | secrets, verify, recovery codes |
| `Unit`       | `Money`             | format, parse, currencies      |
| `Unit`       | `Event`             | on / dispatch / listen / off   |

## Layout

```
tests/
├── bootstrap.php       loads composer autoload, defines _SEVEN, stubs Logger
├── Unit/
│   ├── ContainerTest.php
│   ├── EventTest.php
│   ├── JwtTest.php
│   ├── MoneyTest.php
│   └── TotpTest.php
└── (Integration/)      reserved — needs a test DB
```

## Adding tests

```php
<?php
declare(strict_types=1);
namespace Seven\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MyServiceTest extends TestCase
{
    public function testSomething(): void
    {
        $this->assertTrue(true);
    }
}
```

Tests live under `Seven\Tests\Unit\…` namespace (PSR-4 wired in
`composer.json`'s `autoload-dev`). Class names and file names must match.

## Running CI-style

```bash
composer cs:check   # PHP-CS-Fixer dry-run
composer test       # phpunit
```

## Integration tests (TODO)

Anything that needs a database lives outside the current suite — see the
empty `tests/Integration/` slot. Suggested approach:

1. Spin up a transient MySQL via Docker or GitHub Actions services.
2. Boot SevenCMS with a `tests/.env.testing`.
3. Run `php bin/sev migrate` against the test DB before the suite.
4. Wrap each test in a transaction + rollback.

---

[← Back to docs](#mdlink#index.md#)
