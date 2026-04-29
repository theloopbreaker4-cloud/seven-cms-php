# Getting started

[← Back to docs](index.md)

## Installation

### Requirements

- PHP **8.1+** with extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `fileinfo`
- MySQL **8.0+** or MariaDB **10.5+**
- (Optional) Composer 2.x — see [Composer guide](composer.md)
- (Optional) Redis 6+ — for cache and session driver
- (Optional) `gd` or `imagick` — for image variants

### Step by step

```bash
git clone https://github.com/your-org/sevencms.git
cd sevencms
cp .env.example .env
```

Edit `.env`:

```ini
SEVEN_ENV=dev
BASE_URL=http://localhost:8085
DB_HOST=127.0.0.1
DB_NAME=sevencms
DB_USER=root
DB_PASS=secret
JWT_SECRET=$(openssl rand -hex 32)
APP_KEY=$(openssl rand -hex 32)
```

Create the database, run migrations:

```bash
mysql -u root -p -e "CREATE DATABASE sevencms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php bin/sev migrate
```

(Optional) install Composer packages for production:

```bash
composer install --no-dev --optimize-autoloader
```

Start the dev server:

```bash
php -S localhost:8085 -t public public/router.php
```

## Configuration

`.env` is the source of truth. Sensitive values (`JWT_SECRET`, `STRIPE_SECRET_KEY`,
DB credentials) **must** live there in production — never commit them.

| Key                  | Purpose                                                  |
|----------------------|----------------------------------------------------------|
| `SEVEN_ENV`          | `dev` / `test` / `prod`                                  |
| `BASE_URL`           | Public URL of the site                                   |
| `DB_*`               | Database connection                                      |
| `JWT_SECRET`         | HMAC key for API access tokens                           |
| `APP_KEY`            | Fallback secret for HMAC operations                      |
| `MAIL_FROM`          | Default sender for transactional emails                  |
| `STRIPE_SECRET_KEY`  | (optional) overrides DB setting                          |
| `STRIPE_WEBHOOK_SECRET` | (optional) overrides DB setting                       |
| `PAYPAL_CLIENT_ID`   | (optional) overrides DB setting                          |
| `PAYPAL_SECRET`      | (optional) overrides DB setting                          |
| `STORAGE_DRIVER`     | `local` (default) or `s3`                                |
| `S3_*`               | Credentials when `STORAGE_DRIVER=s3`                     |
| `REDIS_HOST` / `REDIS_PORT` | Cache + session driver when set                   |

## Five-minute tour

1. **Sign in.** First visit redirects to `/setup` if no admin exists. Create one.
2. **Plugins** (`/admin/module`). Install `Content` and `Ecom`.
3. **Roles** (`/admin/roles`). Tune which permissions Editor / Author / Viewer
   roles have. Admins always pass every check.
4. **Content Types** (`/admin/content/types`). Click *New type*, name it,
   add fields. Entries appear at `/admin/content/entries/{slug}`.
5. **Media** (`/admin/media`). Drag and drop files. Create folders. Edit alt
   text inline.
6. **Help** (`/admin/help`). The same docs you're reading, but indexed by topic.

## Upgrading

We never change a migration file once it's released. Each upgrade adds
*new* migrations under `db/migrations/`. To upgrade an existing install:

```bash
git pull
composer install --no-dev --optimize-autoloader  # if you use Composer
php bin/sev migrate
php bin/sev cache:clear
```

Settings, roles, and permissions are merged via `INSERT IGNORE` so reruns
are safe. Plugin install hooks are also idempotent — re-running them only
re-applies missing rows.

---

[← Back to docs](index.md)
