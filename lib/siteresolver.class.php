<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * SiteResolver — picks the active site for the current request.
 *
 * Strategy:
 *   1. Exact match in `site_hosts.host`
 *   2. Wildcard match on the apex (e.g. www.example.com → example.com)
 *   3. Site flagged `is_default = 1`
 *   4. Site with id = 1
 *
 * The result is cached for the rest of the PHP process. Plugins that need to
 * scope queries to the current site call `SiteResolver::currentId()`.
 *
 * Single-site installs work without configuration — the default site is seeded
 * by the migration and returned for every request.
 */
class SiteResolver
{
    private static ?array $current = null;

    public static function current(): array
    {
        if (self::$current !== null) return self::$current;

        // CLI runs use the default site.
        if (PHP_SAPI === 'cli') {
            return self::$current = self::default();
        }

        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        $row = null;
        if ($host !== '') {
            $row = DB::findOne(
                'sites',
                ' id IN (SELECT site_id FROM site_hosts WHERE host = :h) ',
                [':h' => $host]
            );
            if (!$row) {
                // try apex (drop "www." prefix or first sub)
                $apex = preg_replace('/^www\./', '', $host);
                if ($apex && $apex !== $host) {
                    $row = DB::findOne(
                        'sites',
                        ' id IN (SELECT site_id FROM site_hosts WHERE host = :h) ',
                        [':h' => $apex]
                    );
                }
            }
        }

        return self::$current = $row ?: self::default();
    }

    public static function currentId(): int
    {
        return (int)(self::current()['id'] ?? 1);
    }

    public static function setting(string $key, $default = null)
    {
        $site = self::current();
        $arr  = json_decode((string)($site['settings'] ?? '{}'), true);
        return is_array($arr) ? ($arr[$key] ?? $default) : $default;
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    private static function default(): array
    {
        $row = DB::findOne('sites', ' is_default = 1 LIMIT 1 ');
        if (!$row) $row = DB::findOne('sites', ' id = 1 ');
        return $row ?: [
            'id' => 1, 'slug' => 'default', 'name' => 'Default site',
            'is_default' => 1, 'is_active' => 1, 'theme' => null,
            'default_locale' => 'en', 'settings' => '{}',
        ];
    }
}
