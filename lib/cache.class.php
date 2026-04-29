<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Cache facade.
 *
 * Boot once in General::process():
 *   Cache::boot(new CacheRedisDriver(...));   // or CacheFileDriver
 *
 * Usage:
 *   Cache::set('key', $value, 600);
 *   $val = Cache::get('key');
 *   Cache::delete('key');
 *   Cache::flush();
 *
 *   // Remember pattern:
 *   $posts = Cache::remember('posts.all', 300, fn() => DB::getAll('SELECT * FROM post'));
 */
class Cache
{
    private static CacheDriverInterface $driver;
    private static bool $booted = false;

    public static function boot(CacheDriverInterface $driver): void
    {
        self::$driver = $driver;
        self::$booted = true;
    }

    public static function driver(): CacheDriverInterface
    {
        if (!self::$booted) {
            self::boot(new CacheFileDriver());
        }
        return self::$driver;
    }

    public static function get(string $key): mixed
    {
        return self::driver()->get($key);
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): void
    {
        self::driver()->set($key, $value, $ttl);
    }

    public static function delete(string $key): void
    {
        self::driver()->delete($key);
    }

    public static function flush(): void
    {
        self::driver()->flush();
    }

    public static function has(string $key): bool
    {
        return self::driver()->has($key);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = self::get($key);
        if ($cached !== null) return $cached;

        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function forget(string $key): void
    {
        self::delete($key);
    }
}
