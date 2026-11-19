# Public demo instance

Run a copy of Seven CMS that strangers can log into and play with. Never enable this on a real site.

## What demo mode does

Set in `.env`:

```env
DEMO_MODE=true
DEMO_LOGIN=demo@seven.dev
DEMO_PASSWORD=demo1234
```

- The admin sign-in page shows the demo credentials; admin and site show a "resets every hour" banner.
- Content (pages, posts, categories, media, menus, sliders, shop catalogue…) is fully editable.
- Read-only: users, roles, settings, mail, plugins, cron, sites, 2FA, languages, cache, shop settings and currencies. Attempts get a flash message (or `403` JSON for API/XHR).
- API: registration, password reset and `/api/user/*` are disabled.
- Media uploads: images only, max 2 MB.

## Setup

```bash
php bin/sev key:generate
php bin/sev migrate
php bin/sev demo:init        # creates/refreshes the demo admin from DEMO_LOGIN / DEMO_PASSWORD
# …add the sample content you want visitors to see…
php bin/sev demo:snapshot    # saves DB + public/uploads to storage/demo/
```

Reset every hour (crontab of the web user):

```cron
0 * * * * cd /var/www/seven-demo && php bin/sev demo:reset >> logs/demo-reset.log 2>&1
* * * * * cd /var/www/seven-demo && php bin/sev cron:run >> logs/cron.log 2>&1
```

`demo:reset` refuses to run unless `DEMO_MODE=true`, so it can't wipe a real site by accident.
Re-run `demo:snapshot` whenever you change the baseline content.

[← Back to docs](index.md)
