<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Totp — RFC 6238 Time-based One-Time Password (Google Authenticator compatible).
 *
 *   $secret = Totp::generateSecret();        // base32 string
 *   $uri    = Totp::otpAuthUri($secret, $userEmail, 'SevenCMS');
 *   $valid  = Totp::verify($secret, $codeFromUser);
 *
 * Storage:
 *   - secret kept in `user_totp.secret`
 *   - enabled flag in `user_totp.enabled`
 *   - recovery codes in `user_totp.recovery_codes` (JSON of bcrypt hashes)
 *
 * Uses HOTP with SHA1 (per RFC 6238, default). Window of ±1 step (30 sec) for clock skew.
 */
class Totp
{
    public const STEP   = 30; // seconds
    public const DIGITS = 6;
    public const ALG    = 'sha1';

    /** Generate a 160-bit base32 secret (same length as Google Authenticator default). */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** otpauth:// URI for QR codes. */
    public static function otpAuthUri(string $secret, string $accountName, string $issuer = 'SevenCMS'): string
    {
        $label  = rawurlencode($issuer) . ':' . rawurlencode($accountName);
        $params = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => 'SHA1',
            'digits'    => self::DIGITS,
            'period'    => self::STEP,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /** Verify a 6-digit code with ±1 step skew tolerance. */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) return false;
        $bin = self::base32Decode($secret);
        if ($bin === '') return false;

        $counter = (int)floor(time() / self::STEP);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($bin, $counter + $i), $code)) return true;
        }
        return false;
    }

    public static function generateRecoveryCodes(int $count = 8): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(5)));         // 10-char hex
            $out[] = chunk_split($raw, 5, '-');                  // ABCDE-FGHIJ-
        }
        return array_map(fn($s) => rtrim($s, '-'), $out);
    }

    public static function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn($c) => password_hash($c, PASSWORD_BCRYPT), $codes);
    }

    /** Returns the index of the matched recovery code, or null. */
    public static function consumeRecoveryCode(array $hashes, string $code): ?int
    {
        foreach ($hashes as $i => $h) {
            if (is_string($h) && password_verify($code, $h)) return $i;
        }
        return null;
    }

    // ──────────────────────────────────────────────────────────────────

    private static function hotp(string $key, int $counter): string
    {
        $bin    = pack('N*', 0, $counter);                       // 8-byte big-endian counter
        $hash   = hash_hmac(self::ALG, $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value  = (
            ((ord($hash[$offset    ]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** self::DIGITS);
        return str_pad((string)$value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bin): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits  = '';
        foreach (str_split($bin) as $b) $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= $chars[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    private static function base32Decode(string $b32): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32   = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32) ?? '');
        $bits  = '';
        foreach (str_split($b32) as $c) {
            $idx = strpos($chars, $c);
            if ($idx === false) return '';
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }
}
