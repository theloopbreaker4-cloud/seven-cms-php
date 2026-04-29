<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * CSP — Content-Security-Policy nonce helper.
 *
 * Generates a single per-request nonce. Use `Csp::nonce()` in templates to
 * stamp inline <script>/<style> blocks. Modern browsers (CSP Level 3) will
 * ignore the `'unsafe-inline'` keyword when a nonce or hash is also present,
 * so adding nonces gradually upgrades security without breaking views that
 * still rely on `'unsafe-inline'`.
 *
 * Usage in views:
 *   <script nonce="<?= Csp::nonce() ?>">…</script>
 *   <style  nonce="<?= Csp::nonce() ?>">…</style>
 */
class Csp
{
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        if (self::$nonce === null) {
            // 16 random bytes → 22 url-safe base64 chars (no padding).
            self::$nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        }
        return self::$nonce;
    }

    /** Reset for testing only — never call in normal request flow. */
    public static function reset(): void
    {
        self::$nonce = null;
    }
}
