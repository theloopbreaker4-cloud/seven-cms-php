<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) return;

        if (!file_exists($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (str_starts_with(trim($line), '#')) continue;

            if (!str_contains($line, '=')) continue;

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip inline comments
            if (str_contains($value, ' #')) {
                $value = trim(explode(' #', $value, 2)[0]);
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        return ($value !== false && $value !== null && $value !== '') ? $value : $default;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null) {
            throw new RuntimeException("Required .env variable '$key' is not set.");
        }
        return $value;
    }

    /**
     * Write or update a key=value in the .env file.
     */
    public static function set(string $path, string $key, string $value): bool
    {
        if (!file_exists($path)) return false;

        $content = file_get_contents($path);
        $pattern = '/^' . preg_quote($key, '/') . '=.*/m';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $key . '=' . $value, $content);
        } else {
            $content .= PHP_EOL . $key . '=' . $value;
        }

        // Also update runtime env
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
        putenv("$key=$value");

        return file_put_contents($path, $content, LOCK_EX) !== false;
    }
}
