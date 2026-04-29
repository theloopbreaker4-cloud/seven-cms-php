<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Migrator — runs SQL migration files for core and plugins.
 *
 * Discovery order:
 *   1. ROOT_DIR/db/migrations/*.sql           — core migrations
 *   2. ROOT_DIR/modules/{Plugin}/migrations/*.sql  (preferred) OR migration.sql (legacy single file)
 *
 * Tracks applied migrations in `migrations` table:
 *   - id           PK
 *   - migration    full id like "core/2026_04_26_000001_create_plugins_table.sql"
 *                  or "modules/Slider/migration.sql"
 *   - batch        increments per `migrate` invocation, lets us roll back the most recent batch
 *   - applied_at
 *
 * Migration files are run in alphabetical order. They are NOT idempotent by default — each
 * file is run exactly once. Use `IF NOT EXISTS` guards in DDL where useful.
 */
class Migrator
{
    private const TABLE = 'migrations';

    public static function ensureTable(): void
    {
        DB::execute(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                id          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                migration   VARCHAR(255)   NOT NULL,
                batch       INT UNSIGNED   NOT NULL,
                applied_at  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_migrations_migration (migration)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /** @return array<int,string> Pending migration ids in run order. */
    public static function pending(): array
    {
        self::ensureTable();
        $applied = self::appliedSet();
        $all     = self::discover();
        return array_values(array_filter($all, fn(string $id) => !isset($applied[$id])));
    }

    /** @return array<int,string> Applied migration ids in apply order. */
    public static function applied(): array
    {
        self::ensureTable();
        $rows = DB::getAll('SELECT migration FROM ' . self::TABLE . ' ORDER BY id ASC') ?: [];
        return array_column($rows, 'migration');
    }

    /**
     * Run all pending migrations as one batch.
     *
     * @return array{count:int, ran:array<int,string>, errors:array<int,array{migration:string,error:string}>}
     */
    public static function migrate(): array
    {
        self::ensureTable();
        $pending = self::pending();
        if (!$pending) return ['count' => 0, 'ran' => [], 'errors' => []];

        $batch = (int)(DB::getCell('SELECT COALESCE(MAX(batch), 0) + 1 FROM ' . self::TABLE) ?? 1);
        $ran     = [];
        $errors  = [];

        foreach ($pending as $migration) {
            $abs = ROOT_DIR . '/' . $migration;
            if (!is_file($abs)) {
                $errors[] = ['migration' => $migration, 'error' => 'File missing'];
                continue;
            }
            $sql = (string)file_get_contents($abs);
            if (trim($sql) === '') { $ran[] = $migration; continue; } // record empty as applied

            try {
                self::runSql($sql);
                DB::execute(
                    'INSERT INTO ' . self::TABLE . ' (migration, batch) VALUES (:m, :b)',
                    [':m' => $migration, ':b' => $batch]
                );
                $ran[] = $migration;
                Logger::channel('app')->info('Migration applied', ['migration' => $migration]);
            } catch (\Throwable $e) {
                $errors[] = ['migration' => $migration, 'error' => $e->getMessage()];
                Logger::channel('app')->error('Migration failed', [
                    'migration' => $migration, 'error' => $e->getMessage(),
                ]);
                break; // stop on first error
            }
        }

        return ['count' => count($ran), 'ran' => $ran, 'errors' => $errors];
    }

    /**
     * Splits a multi-statement SQL string and runs each statement.
     * Naive splitter — `;` followed by newline. Avoid putting `;` inside string literals
     * across lines in migration files.
     */
    private static function runSql(string $sql): void
    {
        // Strip comment-only lines and split on `;\n`.
        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $clean = array_filter($lines, fn(string $l) => !preg_match('/^\s*(--|#)/', $l));
        $sql   = implode("\n", $clean);

        foreach (preg_split('/;\s*\n/', $sql) ?: [] as $statement) {
            $statement = trim($statement, " \t\n\r\0\x0B;");
            if ($statement === '') continue;
            DB::execute($statement);
        }
    }

    /**
     * Discover all migration files. Returns sorted by full id.
     *
     * @return array<int,string>
     */
    private static function discover(): array
    {
        $out = [];

        // Core: db/migrations/*.sql
        $coreDir = ROOT_DIR . '/db/migrations';
        if (is_dir($coreDir)) {
            foreach (glob($coreDir . '/*.sql') ?: [] as $file) {
                $out[] = 'db/migrations/' . basename($file);
            }
        }

        // Plugins: modules/{Plugin}/migrations/*.sql  (preferred) or modules/{Plugin}/migration.sql
        foreach (glob(ROOT_DIR . '/modules/*', GLOB_ONLYDIR) ?: [] as $modDir) {
            $name = basename($modDir);
            if (is_dir($modDir . '/migrations')) {
                foreach (glob($modDir . '/migrations/*.sql') ?: [] as $file) {
                    $out[] = "modules/{$name}/migrations/" . basename($file);
                }
            } elseif (is_file($modDir . '/migration.sql')) {
                $out[] = "modules/{$name}/migration.sql";
            }
        }

        sort($out, SORT_STRING);
        return $out;
    }

    /** @return array<string,bool> */
    private static function appliedSet(): array
    {
        $rows = DB::getAll('SELECT migration FROM ' . self::TABLE) ?: [];
        $set  = [];
        foreach ($rows as $r) $set[(string)$r['migration']] = true;
        return $set;
    }

    /** Roll back the most recent batch. Returns the list of files that were marked rolled back. */
    public static function rollbackLastBatch(): array
    {
        self::ensureTable();
        $batch = (int)(DB::getCell('SELECT MAX(batch) FROM ' . self::TABLE) ?? 0);
        if (!$batch) return [];

        $rows = DB::getAll(
            'SELECT migration FROM ' . self::TABLE . ' WHERE batch = :b ORDER BY id DESC',
            [':b' => $batch]
        ) ?: [];

        $rolled = [];
        foreach ($rows as $r) {
            DB::execute('DELETE FROM ' . self::TABLE . ' WHERE migration = :m', [':m' => $r['migration']]);
            $rolled[] = $r['migration'];
        }
        // Note: DDL rollback is not automatic; provide your own DROP statements if needed.
        return $rolled;
    }
}
