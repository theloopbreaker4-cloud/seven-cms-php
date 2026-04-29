<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * CronRunner — minimal scheduler.
 *
 * Plugins / boot code register jobs with:
 *   CronRunner::register('ecom.bill_due', '@hourly', [SubscriptionBiller::class, 'billDue']);
 *
 * The OS cron (or Windows Task Scheduler) runs `php bin/sev cron:run` every minute.
 * Each invocation fires every job whose `next_run_at <= NOW()`.
 *
 * Schedule grammar (intentionally tiny — no full cron expression parser):
 *   "@minute"       — every minute
 *   "@hourly"       — top of each hour
 *   "@daily"        — every day at 00:00 (server local)
 *   "@weekly"       — every Monday at 00:00
 *   "@monthly"      — first day of each month
 *   "*\/N"          — every N minutes (e.g. "*\/5")
 *   "HH:MM"         — once per day at given time
 */
class CronRunner
{
    /** @var array<string, array{schedule:string, callback:callable}> */
    private static array $registered = [];

    /**
     * Register a job. Always records the callback in-memory so cron:run can fire it.
     * The DB upsert (used by the admin UI listing) is throttled — we only hit the DB
     * when something actually changed, or once an hour as a safety refresh.
     * Web requests therefore don't pay the cost of multiple INSERTs on every page load.
     */
    public static function register(string $name, string $schedule, callable $callback): void
    {
        self::$registered[$name] = ['schedule' => $schedule, 'callback' => $callback];

        if (!self::shouldPersist($name, $schedule, $callback)) return;

        $now = date('Y-m-d H:i:s');
        DB::execute(
            'INSERT INTO cron_jobs (name, schedule, callback, is_enabled, next_run_at, created_at)
             VALUES (:n, :s, :cb, 1, :nr, :ca)
             ON DUPLICATE KEY UPDATE schedule = VALUES(schedule), callback = VALUES(callback)',
            [
                ':n'  => $name,
                ':s'  => $schedule,
                ':cb' => self::callableString($callback),
                ':nr' => self::computeNext($schedule, time()),
                ':ca' => $now,
            ]
        );
    }

    /**
     * Cheap signature check: keep a JSON cache file of the last persisted
     * (schedule, callback) per name. Skip the DB write when nothing changed
     * and the cache is fresh (< 1h old). CLI always writes through.
     */
    private static array $cacheData = [];
    private static bool  $cacheLoaded = false;

    private static function shouldPersist(string $name, string $schedule, callable $callback): bool
    {
        $cacheFile = ROOT_DIR . '/storage/cache/cron_signatures.json';

        if (!self::$cacheLoaded) {
            self::$cacheLoaded = true;
            if (is_file($cacheFile)) {
                $age = time() - (int)@filemtime($cacheFile);
                if ($age < 3600) {
                    $j = @file_get_contents($cacheFile);
                    self::$cacheData = $j ? (json_decode($j, true) ?: []) : [];
                }
            }
        }

        $sig = $schedule . '|' . self::callableString($callback);
        $cli = (PHP_SAPI === 'cli');

        if (!$cli && isset(self::$cacheData[$name]) && self::$cacheData[$name] === $sig) {
            return false; // unchanged + cache fresh → skip DB
        }

        // Persist signature for next request.
        self::$cacheData[$name] = $sig;
        if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0755, true);
        @file_put_contents($cacheFile, json_encode(self::$cacheData));
        return true;
    }

    /** List all registered jobs (rows from DB). */
    public static function list(): array
    {
        return DB::getAll('SELECT * FROM cron_jobs ORDER BY name ASC');
    }

    /**
     * Run all due jobs. Used by `bin/sev cron:run`.
     * @return array{ran:array<int,array{name:string,ms:int}>, errors:array<int,array{name:string,error:string}>}
     */
    public static function runDue(): array
    {
        $due = DB::getAll(
            'SELECT * FROM cron_jobs
             WHERE is_enabled = 1 AND (next_run_at IS NULL OR next_run_at <= NOW())
             ORDER BY id ASC'
        );

        $ran = []; $errors = [];
        foreach ($due as $row) {
            $name = (string)$row['name'];
            $cb   = self::$registered[$name]['callback'] ?? null;
            if (!$cb) {
                // Maybe registered string-only — try to resolve.
                $cb = self::resolveCallable((string)$row['callback']);
            }
            if (!$cb) {
                DB::execute(
                    'UPDATE cron_jobs SET last_status = "skipped", last_error = "callback unresolved", next_run_at = :n WHERE id = :id',
                    [':n' => self::computeNext((string)$row['schedule'], time()), ':id' => (int)$row['id']]
                );
                continue;
            }

            $start = microtime(true);
            try {
                $cb();
                $ms = (int)round((microtime(true) - $start) * 1000);
                DB::execute(
                    'UPDATE cron_jobs
                       SET last_run_at = NOW(), last_status = "ok", last_error = NULL,
                           last_duration_ms = :ms, next_run_at = :n
                     WHERE id = :id',
                    [':ms' => $ms, ':n' => self::computeNext((string)$row['schedule'], time()), ':id' => (int)$row['id']]
                );
                $ran[] = ['name' => $name, 'ms' => $ms];
                Event::dispatch('cron.ran', ['name' => $name, 'ms' => $ms]);
            } catch (\Throwable $e) {
                $ms = (int)round((microtime(true) - $start) * 1000);
                DB::execute(
                    'UPDATE cron_jobs
                       SET last_run_at = NOW(), last_status = "error", last_error = :err,
                           last_duration_ms = :ms, next_run_at = :n
                     WHERE id = :id',
                    [':err' => $e->getMessage(), ':ms' => $ms, ':n' => self::computeNext((string)$row['schedule'], time()), ':id' => (int)$row['id']]
                );
                $errors[] = ['name' => $name, 'error' => $e->getMessage()];
                Event::dispatch('cron.failed', ['name' => $name, 'error' => $e->getMessage()]);
            }
        }
        return ['ran' => $ran, 'errors' => $errors];
    }

    public static function setEnabled(string $name, bool $enabled): void
    {
        DB::execute('UPDATE cron_jobs SET is_enabled = :e WHERE name = :n',
            [':e' => $enabled ? 1 : 0, ':n' => $name]);
    }

    public static function runOnce(string $name): array
    {
        $row = DB::findOne('cron_jobs', ' name = :n ', [':n' => $name]);
        if (!$row) return ['ok' => false, 'error' => 'unknown'];
        DB::execute('UPDATE cron_jobs SET next_run_at = NOW() WHERE id = :id', [':id' => (int)$row['id']]);
        $r = self::runDue();
        return ['ok' => true, 'ran' => $r['ran'], 'errors' => $r['errors']];
    }

    // ──────────────────────────────────────────────────────────────────

    private static function computeNext(string $schedule, int $now): string
    {
        $next = match (true) {
            $schedule === '@minute'  => $now + 60,
            $schedule === '@hourly'  => self::nextTopOfHour($now),
            $schedule === '@daily'   => self::nextMidnight($now),
            $schedule === '@weekly'  => self::nextMonday($now),
            $schedule === '@monthly' => self::nextMonthFirst($now),
            (bool)preg_match('~^\*/(\d+)$~', $schedule, $m) => $now + max(1, (int)$m[1]) * 60,
            (bool)preg_match('~^(\d{1,2}):(\d{2})$~', $schedule, $m) => self::nextTimeOfDay($now, (int)$m[1], (int)$m[2]),
            default => $now + 3600,
        };
        return date('Y-m-d H:i:s', $next);
    }

    private static function nextTopOfHour(int $now): int { return strtotime(date('Y-m-d H:00:00', $now + 3600)); }
    private static function nextMidnight(int $now): int { return strtotime(date('Y-m-d 00:00:00', $now + 86400)); }
    private static function nextMonday(int $now): int   { return strtotime('next Monday 00:00:00', $now); }
    private static function nextMonthFirst(int $now): int { return strtotime('first day of next month 00:00:00', $now); }

    private static function nextTimeOfDay(int $now, int $h, int $m): int
    {
        $today = strtotime(sprintf('%s %02d:%02d:00', date('Y-m-d', $now), $h, $m));
        return $today > $now ? $today : $today + 86400;
    }

    private static function callableString(callable $cb): ?string
    {
        if (is_array($cb) && isset($cb[0], $cb[1])) {
            $cls = is_object($cb[0]) ? get_class($cb[0]) : (string)$cb[0];
            return $cls . '::' . $cb[1];
        }
        if (is_string($cb)) return $cb;
        return null;   // closures aren't persisted; only in-memory
    }

    private static function resolveCallable(?string $s): ?callable
    {
        if (!$s) return null;
        if (str_contains($s, '::')) {
            [$cls, $m] = explode('::', $s, 2);
            if (is_callable([$cls, $m])) return [$cls, $m];
        }
        return is_callable($s) ? $s : null;
    }
}
