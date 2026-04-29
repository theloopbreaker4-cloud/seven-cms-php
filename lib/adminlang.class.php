<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Admin panel i18n — independent from site language.
 * Language stored in cookie 'admin_lang', defaults to 'en'.
 * Lang files: lang/admin/{code}.php
 */
class AdminLang
{
    private static ?array  $data    = null;
    private static string  $current = 'en';

    // Built-in supported codes (more can be added via settings)
    public const BUILTIN = ['en', 'de', 'fr', 'nl'];

    public static function load(): void
    {
        $code = self::detect();
        $file = ROOT_DIR . DS . 'lang' . DS . 'admin' . DS . $code . '.php';

        if (!file_exists($file)) {
            $code = 'en';
            $file = ROOT_DIR . DS . 'lang' . DS . 'admin' . DS . 'en.php';
        }

        self::$current = $code;
        self::$data    = file_exists($file) ? include $file : [];
    }

    public static function detect(): string
    {
        // 1. Cookie set by user preference
        $cookie = $_COOKIE['admin_lang'] ?? '';
        if ($cookie && preg_match('/^[a-z]{2}$/', $cookie)) {
            return $cookie;
        }
        // 2. Default from settings DB (cached)
        try {
            $row = \R::findOne('setting', ' `key` = "admin_lang_default" ');
            if ($row) return $row->value;
        } catch (\Throwable $e) { /* ignore */ }

        return 'en';
    }

    public static function current(): string { return self::$current; }

    public static function t(string $key, ?string $group = null, string $fallback = ''): string
    {
        $data = self::$data ?? [];
        if ($group !== null) {
            $data = $data[$group] ?? [];
        }
        return $data[$key] ?? $fallback ?: $key;
    }

    // All installed admin languages [{code, name, isDefault, isActive}]
    public static function getAvailable(): array
    {
        try {
            $rows = \R::find('adminlang', ' 1 ORDER BY is_default DESC, code ASC ');
            if ($rows) {
                return array_values(array_map(fn($r) => [
                    'code'      => $r->code,
                    'name'      => $r->name,
                    'isDefault' => (bool)$r->is_default,
                    'isActive'  => isset($r->is_active) ? (bool)$r->is_active : true,
                ], $rows));
            }
        } catch (\Throwable $e) { /* table not yet created */ }

        // Fallback to built-in
        return [
            ['code' => 'en', 'name' => 'English',    'isDefault' => true,  'isActive' => true],
            ['code' => 'de', 'name' => 'Deutsch',    'isDefault' => false, 'isActive' => true],
            ['code' => 'fr', 'name' => 'Français',   'isDefault' => false, 'isActive' => true],
            ['code' => 'nl', 'name' => 'Nederlands', 'isDefault' => false, 'isActive' => true],
        ];
    }

    // Set language cookie and redirect back
    public static function setCookie(string $code): void
    {
        setcookie('admin_lang', $code, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'samesite' => 'Strict',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
        ]);
    }
}
