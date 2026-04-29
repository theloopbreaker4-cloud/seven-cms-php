<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Crypt
{
    // Generate a cryptographically secure random token (hex string)
    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    // Hash a value with SHA-256 (for non-password use: tokens, checksums)
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    // Constant-time comparison (prevent timing attacks)
    public static function compare(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    // Kept for backwards-compatibility with any views that may call it
    // @deprecated Use randomToken() instead
    public static function randomEncode(): string
    {
        return self::randomToken();
    }
}
