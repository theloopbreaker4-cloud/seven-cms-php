# Cron & scheduler

[← Back to docs](index.md)

SevenCMS ships with a tiny scheduler that runs **registered jobs** when their
next execution time arrives. The OS cron (or Windows Task Scheduler) hits
`php bin/sev cron:run` once a minute; everything else is data.

## How it works

1. A job is **registered** with `CronRunner::register($name, $schedule, $callback)`.
   Calling `register()` is idempotent — it upserts a row in `cron_jobs`.
2. The first call computes `next_run_at` based on the schedule.
3. `cron:run` selects every enabled job whose `next_run_at <= NOW()`,
   executes its callback, captures duration / errors, and recomputes
   `next_run_at`.

## Schedule grammar

We deliberately don't parse full cron expressions — just the few forms we need:

| Schedule  | Fires                                |
|-----------|--------------------------------------|
| `@minute` | every minute                         |
| `@hourly` | top of every hour                    |
| `@daily`  | every day at 00:00 (server local TZ) |
| `@weekly` | every Monday at 00:00                |
| `@monthly`| 1st day of every month at 00:00      |
| `*/N`     | every N minutes                      |
| `HH:MM`   | once a day at given time             |

If you need cron-expression power, replace `CronRunner::computeNext()` or
register jobs as `@minute` and check the time inside the callback.

## Built-in jobs

| Name                          | Schedule | Purpose                                        |
|-------------------------------|----------|------------------------------------------------|
| `mail.queue.flush`            | @minute  | Drain up to 50 pending messages from the queue |
| `mail.queue.cleanup`          | @daily   | Delete sent messages older than 30 days        |
| `notifications.cleanup`       | @daily   | Drop read notifications older than 60 days     |
| `calendar.sweep`              | @minute  | Fire reminders for `calendar_events.notify_at` |
| `ecom.subscription.bill_due`  | @hourly  | Renew due subscriptions on the manual gateway  |
| `ecom.fx.refresh`             | @daily   | Refresh FX rates (only when multi-currency on) |

## OS cron setup

**Linux / macOS** — `crontab -e`:

```cron
* * * * * cd /var/www/sevenphp && /usr/bin/php bin/sev cron:run >> storage/logs/cron.log 2>&1
```

**Windows Task Scheduler:**

```text
Action:    php.exe
Arguments: bin\sev cron:run
Start in:  C:\path\to\sevenphp
Trigger:   Daily, recur every 1 minute, indefinitely
```

**Docker / systemd timers**: any "fire every minute" mechanism works.

## Admin UI

`/admin/cron` lists every registered job with its last status, last run,
next run, and duration. From there you can run a job on demand or
disable/enable it. The dashboard widget shows a compact health view.

## Registering custom jobs

```php
class MyPluginModule implements ModuleInterface
{
    public function boot(): void
    {
        if (!class_exists('CronRunner')) return;
        CronRunner::register('myplugin.cleanup', '@daily', [MyJob::class, 'run']);
    }
}
```

Closures register fine in memory but **aren't persisted across PHP
processes** — the `cron:run` worker re-imports your plugin on each tick,
so it'll see the registration again. For long-lived cleanup logic prefer a
named class method (`'Class::method'`).

## Hooks fired

- `cron.ran`     — `{ name, ms }` — fires after a successful run
- `cron.failed`  — `{ name, error }` — fires on exception

---

[← Back to docs](#mdlink#index.md#)
