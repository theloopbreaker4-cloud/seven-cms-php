<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * PluginManager — lifecycle for installable modules under /modules.
 *
 * State is kept in the `plugins` table:
 *   id, name, version, status (installed|enabled|disabled), installed_at, updated_at
 *
 * Lifecycle:
 *   discover()        — scan /modules and return [name => Module class]
 *   install($name)    — run plugin migrations, fire onInstall hook, mark as installed+enabled
 *   uninstall($name)  — fire onUninstall hook, mark as not installed (does NOT drop tables)
 *   enable($name)     — set status=enabled, fire onEnable hook
 *   disable($name)    — set status=disabled, fire onDisable hook
 *   isEnabled($name)  — quick check used by the boot/router code
 *
 * Existing core registers modules unconditionally; this manager lets the admin
 * UI gate them dynamically without removing files.
 */
class PluginManager
{
    private const TABLE = 'plugins';

    public static function ensureTable(): void
    {
        DB::execute(
            'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                id            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
                name          VARCHAR(120)   NOT NULL,
                version       VARCHAR(40)    NOT NULL DEFAULT "0.0.0",
                status        ENUM("installed","enabled","disabled","uninstalled")
                              NOT NULL DEFAULT "uninstalled",
                config        JSON           NOT NULL DEFAULT ("{}"),
                installed_at  DATETIME       NULL,
                updated_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_plugins_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );
    }

    /**
     * @return array<string,string> Map of plugin name → Module class name (e.g. "Pages" => "PagesModule").
     */
    public static function discover(): array
    {
        $found = [];
        foreach (glob(ROOT_DIR . '/modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            $file = $dir . '/Module.php';
            if (!is_file($file)) continue;
            $class = $name . 'Module';
            if (!class_exists($class)) {
                require_once $file;
            }
            if (class_exists($class)) {
                $found[$name] = $class;
            }
        }
        ksort($found);
        return $found;
    }

    /** Returns a row from the plugins table, or null. */
    public static function find(string $name): ?array
    {
        self::ensureTable();
        $row = DB::findOne(self::TABLE, ' name = :n ', [':n' => $name]);
        return $row ?: null;
    }

    /** All plugin rows in the DB. */
    public static function all(): array
    {
        self::ensureTable();
        return DB::getAll('SELECT * FROM ' . self::TABLE . ' ORDER BY name ASC') ?: [];
    }

    public static function isEnabled(string $name): bool
    {
        $row = self::find($name);
        return $row && $row['status'] === 'enabled';
    }

    /**
     * Install a plugin: run its migrations, persist its row, fire onInstall hook.
     * Re-running install on an already-installed plugin re-runs migrations only (idempotent for SQL).
     */
    public static function install(string $name): array
    {
        self::ensureTable();

        $classes = self::discover();
        if (!isset($classes[$name])) return ['ok' => false, 'message' => "Plugin {$name} not found"];

        $migration = Migrator::migrate(); // runs ALL pending, including this plugin's

        $existing = self::find($name);
        $payload  = [
            ':n' => $name,
            ':v' => self::readVersion($name),
            ':i' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            DB::execute(
                'UPDATE ' . self::TABLE . ' SET status = "enabled", version = :v, installed_at = COALESCE(installed_at, :i) WHERE name = :n',
                $payload
            );
        } else {
            DB::execute(
                'INSERT INTO ' . self::TABLE . ' (name, version, status, installed_at) VALUES (:n, :v, "enabled", :i)',
                $payload
            );
        }

        self::callHook($classes[$name], 'onInstall');
        Event::dispatch('plugin.installed', ['name' => $name]);

        return ['ok' => true, 'migrations' => $migration];
    }

    public static function uninstall(string $name): array
    {
        $classes = self::discover();
        if (isset($classes[$name])) self::callHook($classes[$name], 'onUninstall');

        DB::execute(
            'UPDATE ' . self::TABLE . ' SET status = "uninstalled" WHERE name = :n',
            [':n' => $name]
        );
        Event::dispatch('plugin.uninstalled', ['name' => $name]);
        return ['ok' => true];
    }

    public static function enable(string $name): array
    {
        $classes = self::discover();
        if (!isset($classes[$name])) return ['ok' => false, 'message' => "Plugin {$name} not found"];

        if (!self::find($name)) return self::install($name);

        DB::execute(
            'UPDATE ' . self::TABLE . ' SET status = "enabled" WHERE name = :n',
            [':n' => $name]
        );
        self::callHook($classes[$name], 'onEnable');
        Event::dispatch('plugin.enabled', ['name' => $name]);
        return ['ok' => true];
    }

    public static function disable(string $name): array
    {
        $classes = self::discover();
        if (isset($classes[$name])) self::callHook($classes[$name], 'onDisable');

        DB::execute(
            'UPDATE ' . self::TABLE . ' SET status = "disabled" WHERE name = :n',
            [':n' => $name]
        );
        Event::dispatch('plugin.disabled', ['name' => $name]);
        return ['ok' => true];
    }

    private static function callHook(string $class, string $method): void
    {
        if (!class_exists($class)) return;
        $instance = new $class();
        if (method_exists($instance, $method)) {
            try { $instance->$method(); }
            catch (\Throwable $e) {
                Logger::channel('app')->warning("Plugin hook {$class}::{$method} failed", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Reads version from /modules/{name}/plugin.json if present; falls back to "0.0.0".
     */
    private static function readVersion(string $name): string
    {
        $manifest = ROOT_DIR . "/modules/{$name}/plugin.json";
        if (!is_file($manifest)) return '0.0.0';
        $data = json_decode((string)file_get_contents($manifest), true);
        return is_array($data) && !empty($data['version']) ? (string)$data['version'] : '0.0.0';
    }
}
