<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * RateLimit — per-IP throttle backed by the Cache facade (Redis or file).
 *
 * Used by both web flows (admin login) and stateless API endpoints (login,
 * refresh, register, forgot, reset). The previous Session-based implementation
 * couldn't throttle API clients because they never sent a session cookie —
 * Cache::set() with a TTL works for both.
 *
 * Usage:
 *   RateLimit::check('api_login');         // throws 429 if exceeded
 *   RateLimit::hit('api_login');           // count a failed attempt
 *   RateLimit::clear('api_login');         // wipe on success
 *
 * Trusted-proxy aware: X-Forwarded-For is honoured only when REMOTE_ADDR is in
 * `config['trustedProxies']`. Otherwise REMOTE_ADDR is used directly.
 */
class RateLimit
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SEC   = 15 * 60; // 15 minutes

    public static function check(string $action): void
    {
        $data = self::load($action);

        if ($data['count'] >= self::MAX_ATTEMPTS) {
            $wait = self::WINDOW_SEC - (time() - $data['since']);
            if ($wait < 0) $wait = 0;
            http_response_code(429);
            header('Retry-After: ' . $wait);
            exit('Too many attempts. Try again in ' . max(1, ceil($wait / 60)) . ' min.');
        }
    }

    public static function hit(string $action): void
    {
        $data = self::load($action);
        $data['count']++;
        // Persist for the remaining window so the lock isn't extended by every hit.
        $remaining = max(1, self::WINDOW_SEC - (time() - $data['since']));
        Cache::set(self::key($action), $data, $remaining);
    }

    public static function clear(string $action): void
    {
        Cache::delete(self::key($action));
    }

    /** @return array{count:int,since:int} */
    private static function load(string $action): array
    {
        $raw = Cache::get(self::key($action));
        if (is_array($raw) && isset($raw['count'], $raw['since'])) {
            // Defensive: if the entry somehow outlived its window (clock skew, file
            // backend without strict TTL enforcement), reset it.
            if (time() - $raw['since'] > self::WINDOW_SEC) {
                return ['count' => 0, 'since' => time()];
            }
            return $raw;
        }
        return ['count' => 0, 'since' => time()];
    }

    private static function key(string $action): string
    {
        return 'rl:' . $action . ':' . hash('sha256', self::clientIp());
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
                $first = trim(explode(',', $fwd)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
            }
        }
        return $remote;
    }
}
