<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Session
{
    protected static ?string $flashMessage = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        // Secure session cookie params before session_start
        $secure   = (PROTOCOL === 'https://');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        @ob_start();
        session_start();
    }

    // Call after login to prevent session fixation
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
    }

    public static function setFlash(string $message): void
    {
        self::$flashMessage = $message;
    }

    public static function hasFlash(): bool
    {
        return self::$flashMessage !== null;
    }

    public static function flash(): void
    {
        echo htmlspecialchars(self::$flashMessage ?? '', ENT_QUOTES, 'UTF-8');
        self::$flashMessage = null;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
