<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class RateLimit
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SEC   = 15 * 60; // 15 minutes

    // Check if IP is blocked for a given action key (e.g. 'login')
    public static function check(string $action): void
    {
        $key  = self::key($action);
        $data = Session::get($key) ?? ['count' => 0, 'since' => time()];

        // Reset window if expired
        if (time() - $data['since'] > self::WINDOW_SEC) {
            $data = ['count' => 0, 'since' => time()];
        }

        if ($data['count'] >= self::MAX_ATTEMPTS) {
            $wait = self::WINDOW_SEC - (time() - $data['since']);
            http_response_code(429);
            exit('Too many attempts. Try again in ' . ceil($wait / 60) . ' min.');
        }
    }

    // Increment attempt counter after a failed attempt
    public static function hit(string $action): void
    {
        $key  = self::key($action);
        $data = Session::get($key) ?? ['count' => 0, 'since' => time()];

        if (time() - $data['since'] > self::WINDOW_SEC) {
            $data = ['count' => 0, 'since' => time()];
        }

        $data['count']++;
        Session::set($key, $data);
    }

    // Clear counter after successful login
    public static function clear(string $action): void
    {
        Session::delete(self::key($action));
    }

    private static function key(string $action): string
    {
        return '_rl_' . $action . '_' . hash('sha256', self::clientIp());
    }

    // Resolve the real client IP. Only trust X-Forwarded-For when REMOTE_ADDR
    // is in the configured trusted-proxy whitelist; otherwise an attacker
    // hitting us directly could spoof the header to bypass rate limits.
    private static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $cfg = Seven::app()?->config ?? [];
        $trusted = $cfg['trustedProxies'] ?? [];

        if ($trusted && in_array($remote, $trusted, true)) {
            $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($fwd !== '') {
                // Take the leftmost (originating client) and validate as IP.
                $first = trim(explode(',', $fwd)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
            }
        }
        return $remote;
    }
}
