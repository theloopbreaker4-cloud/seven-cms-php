<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Session
{

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        // Secure session cookie params before session_start
        $secure   = (PROTOCOL === 'https://');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => BasePath::cookiePath(),
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
        $_SESSION['_flash'] = $message; // survives the redirect that usually follows
    }

    public static function hasFlash(): bool
    {
        return isset($_SESSION['_flash']);
    }

    public static function flash(): void
    {
        echo htmlspecialchars((string)($_SESSION['_flash'] ?? ''), ENT_QUOTES, 'UTF-8');
        unset($_SESSION['_flash']);
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
