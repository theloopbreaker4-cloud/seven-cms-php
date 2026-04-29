<?php

defined('_SEVEN') or die('No direct script access allowed');

class Module
{
    /** @var ModuleInterface[] */
    private static array $modules  = [];
    private static bool  $loaded   = false;
    private static string $cfgFile = '';

    public static function register(ModuleInterface $module): void
    {
        $name = $module->getName();
        if (isset(self::$modules[$name])) return;
        self::$modules[$name] = $module;
        $module->boot();
        foreach ($module->routes() as $routeName => $routeDef) {
            Route::add($routeName, $routeDef);
        }
    }

    public static function loadAll(): void
    {
        if (self::$loaded) return;
        self::$loaded  = true;
        self::$cfgFile = ROOT_DIR . DS . 'storage' . DS . 'modules.json';

        $dir = ROOT_DIR . DS . 'modules';
        if (is_dir($dir)) {
            $disabled = self::readDisabled();

            foreach (glob($dir . DS . '*' . DS . 'Module.php') as $file) {
                $name = basename(dirname($file));
                if (in_array($name, $disabled, true)) continue;
                require_once $file;
                $class = $name . 'Module';
                if (class_exists($class)) {
                    self::register(new $class());
                }
            }
        }

        // Core cron jobs. Plugins register their own inside boot().
        if (class_exists('CoreJobs')) CoreJobs::register();
    }

    // Returns list of all discovered module names (enabled + disabled)
    public static function discover(): array
    {
        $dir    = ROOT_DIR . DS . 'modules';
        $result = [];
        if (!is_dir($dir)) return $result;
        foreach (glob($dir . DS . '*' . DS . 'Module.php') as $file) {
            $name  = basename(dirname($file));
            $class = $name . 'Module';
            // Load file temporarily to read metadata if not already loaded
            if (!class_exists($class)) require_once $file;
            $disabled = self::readDisabled();
            $result[] = [
                'name'     => $name,
                'class'    => $class,
                'enabled'  => !in_array($name, $disabled, true),
                'booted'   => isset(self::$modules[$name]),
                'routes'   => isset(self::$modules[$name])
                              ? array_keys(self::$modules[$name]->routes())
                              : [],
            ];
        }
        return $result;
    }

    public static function enable(string $name): void
    {
        $disabled = self::readDisabled();
        $disabled = array_values(array_filter($disabled, fn($n) => $n !== $name));
        self::writeDisabled($disabled);
    }

    public static function disable(string $name): void
    {
        $disabled = self::readDisabled();
        if (!in_array($name, $disabled, true)) {
            $disabled[] = $name;
            self::writeDisabled($disabled);
        }
    }

    private static function readDisabled(): array
    {
        $file = self::$cfgFile ?: ROOT_DIR . DS . 'storage' . DS . 'modules.json';
        if (!file_exists($file)) return [];
        $data = json_decode(file_get_contents($file), true);
        return $data['disabled'] ?? [];
    }

    private static function writeDisabled(array $disabled): void
    {
        $file = self::$cfgFile ?: ROOT_DIR . DS . 'storage' . DS . 'modules.json';
        file_put_contents($file, json_encode(['disabled' => array_values($disabled)], JSON_PRETTY_PRINT));
    }

    public static function get(string $name): ?ModuleInterface  { return self::$modules[$name] ?? null; }
    public static function all(): array                         { return self::$modules; }
    public static function has(string $name): bool              { return isset(self::$modules[$name]); }
}
