<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Db extends \RedBeanPHP\Facade
{
    /**
     * RedBean's facade method is `exec()`. The codebase historically called it
     * as `DB::execute()` (PDO-style), which falls through to __callStatic and
     * fails with "Plugin 'execute' does not exist". Alias here so existing
     * call sites keep working without a sweep.
     */
    public static function execute($sql, $bindings = [])
    {
        return self::exec($sql, $bindings);
    }

    // ── Raw queries ─────────────────────────────────────────────────────────
    // In fluid mode RedBean's facade answers SQL that names a missing table or
    // column with an empty result — no error, no log. That hid real bugs (the demo
    // INSERT into a non-existent `user.provider` "succeeded" and created nothing).
    // These overrides log every such query; in dev they throw so it's seen at once.

    public static function exec($sql, $bindings = [])     { return self::run('exec',     $sql, $bindings); }
    public static function getAll($sql, $bindings = [])   { return self::run('get',      $sql, $bindings); }
    public static function getRow($sql, $bindings = [])   { return self::run('getRow',   $sql, $bindings); }
    public static function getCol($sql, $bindings = [])   { return self::run('getCol',   $sql, $bindings); }
    public static function getCell($sql, $bindings = [])  { return self::run('getCell',  $sql, $bindings); }
    public static function getAssoc($sql, $bindings = []) { return self::run('getAssoc', $sql, $bindings); }

    private static function run(string $method, $sql, $bindings)
    {
        try {
            return parent::getDatabaseAdapter()->$method($sql, $bindings ?? []);
        } catch (\RedBeanPHP\RedException\SQL $e) {
            $state = (string)$e->getSQLState();
            if (!in_array($state, ['42S02', '42S22'], true)) throw $e; // not "no such table/column"
            $ctx = [
                'error' => $e->getMessage(),
                'sql'   => is_string($sql) ? substr(preg_replace('/\s+/', ' ', $sql), 0, 300) : '',
            ];
            if ($state === '42S02') {
                // Fluid mode creates a table on its first store(), so reading one that
                // doesn't exist yet just means "no rows".
                if (class_exists('Logger')) Logger::channel('db')->debug('Query on a table not created yet', $ctx);
            } else {
                // A missing column is a real bug: the write is silently lost otherwise.
                if (class_exists('Logger')) Logger::channel('db')->error('Query references a missing column', $ctx);
                if (defined('ENVIRONMENT') && ENVIRONMENT === 'dev') throw $e;
            }
            return $method === 'getCell' ? null : [];
        }
    }

    /** PDO-style alias used across the codebase; RedBean calls it getInsertID(). */
    public static function lastInsertId(): int
    {
        return (int)parent::getInsertID();
    }

    /**
     * Align the MySQL session time zone with PHP's. Code mixes date() with NOW()
     * (cron next_run_at, mail available_at, reset expiry); a server/DB zone
     * mismatch would otherwise shift every comparison by hours.
     */
    public static function syncTimezone(): void
    {
        self::exec('SET time_zone = ?', [date('P')]);
    }

    public static function isSetDatabase(array $dbConfig, Logger $logger): void
    {
        $host     = $dbConfig['dbHost']    ?? '127.0.0.1';
        $user     = $dbConfig['user']      ?? '';
        $password = $dbConfig['password']  ?? '';
        $database = $dbConfig['dbname']    ?? 'sevencms';
        $dbLog    = Logger::channel('db');

        try {
            $link = mysqli_connect($host, $user, $password, null, (int)($dbConfig['dbPort'] ?? 3306));
            if (!$link) {
                throw new RuntimeException('Could not connect to MySQL: ' . mysqli_connect_error());
            }

            if (!mysqli_select_db($link, $database)) {
                $escaped = mysqli_real_escape_string($link, $database);
                if (mysqli_query($link, 'CREATE DATABASE `' . $escaped . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
                    $dbLog->info('Database created', ['db' => $database]);
                } else {
                    $dbLog->error('Create database failed', ['db' => $database, 'error' => mysqli_error($link)]);
                }
            } else {
                $dbLog->debug('Database connected', ['db' => $database, 'host' => $host]);
            }

            mysqli_close($link);
        } catch (RuntimeException $e) {
            $dbLog->error('DB init error', ['message' => $e->getMessage()]);
        }
    }
}
