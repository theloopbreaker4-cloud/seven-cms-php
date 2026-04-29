<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Jwt — minimal HS256 JSON Web Token implementation, no external dependencies.
 *
 *   $access  = Jwt::sign(['sub' => $userId, 'role' => 'admin'], 900);   // 15 min
 *   $payload = Jwt::verify($token);                                     // null if invalid/expired
 *
 * Secret comes from `JWT_SECRET` env var. Algorithm is fixed to HS256.
 */
class Jwt
{
    public const ALG = 'HS256';

    /**
     * @param array $claims  Application claims; iat/exp/jti are added automatically.
     * @param int   $ttl     Lifetime in seconds.
     * @param array $header  Extra header fields (typ/alg are forced).
     */
    public static function sign(array $claims, int $ttl = 900, array $header = []): string
    {
        $now = time();
        $claims = array_merge([
            'iat' => $now,
            'exp' => $now + max(1, $ttl),
            'jti' => bin2hex(random_bytes(8)),
        ], $claims);
        $header = array_merge(['alg' => self::ALG, 'typ' => 'JWT'], $header);

        $h = self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = self::b64url(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $s = self::b64url(hash_hmac('sha256', "{$h}.{$p}", self::secret(), true));
        return "{$h}.{$p}.{$s}";
    }

    /** Returns decoded claims, or null if invalid/expired. */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;

        $expected = self::b64url(hash_hmac('sha256', "{$h}.{$p}", self::secret(), true));
        if (!hash_equals($expected, $s)) return null;

        $header = json_decode((string)self::b64urlDecode($h), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== self::ALG) return null;

        $claims = json_decode((string)self::b64urlDecode($p), true);
        if (!is_array($claims)) return null;
        if (isset($claims['exp']) && (int)$claims['exp'] < time()) return null;
        if (isset($claims['nbf']) && (int)$claims['nbf'] > time()) return null;

        return $claims;
    }

    private static function secret(): string
    {
        $s = (string)Env::get('JWT_SECRET', '');
        if ($s === '') $s = (string)Env::get('APP_KEY', 'change-me');
        return $s;
    }

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad) $s .= str_repeat('=', 4 - $pad);
        return (string)base64_decode(strtr($s, '-_', '+/'), true);
    }
}
