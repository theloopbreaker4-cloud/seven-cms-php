<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * PreviewToken — short-lived signed tokens for preview links to draft content.
 *
 * Format:  base64url(payload).base64url(hmac_sha256(payload, JWT_SECRET))
 * Payload: {"e":"content_entries","i":42,"x":1735689600}   // entity / id / expires-at
 *
 * Stateless — no DB write. Server validates by recomputing HMAC and checking expiry.
 */
class PreviewToken
{
    /** Default lifetime: 1 hour. */
    public const DEFAULT_TTL = 3600;

    public static function create(string $entityType, int $entityId, int $ttl = self::DEFAULT_TTL): string
    {
        $payload = [
            'e' => $entityType,
            'i' => $entityId,
            'x' => time() + max(60, $ttl),
        ];
        $body = self::b64url(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $sig  = self::b64url(hash_hmac('sha256', $body, self::secret(), true));
        return $body . '.' . $sig;
    }

    /**
     * Validates a token. Returns the decoded payload, or null if invalid/expired/tampered.
     *
     * @return array{e:string,i:int,x:int}|null
     */
    public static function verify(string $token): ?array
    {
        if (substr_count($token, '.') !== 1) return null;
        [$body, $sig] = explode('.', $token, 2);
        $expected = self::b64url(hash_hmac('sha256', $body, self::secret(), true));
        if (!hash_equals($expected, $sig)) return null;

        $payload = json_decode((string)self::b64urlDecode($body), true);
        if (!is_array($payload) || empty($payload['e']) || empty($payload['i']) || empty($payload['x'])) {
            return null;
        }
        if ((int)$payload['x'] < time()) return null;

        return [
            'e' => (string)$payload['e'],
            'i' => (int)$payload['i'],
            'x' => (int)$payload['x'],
        ];
    }

    private static function secret(): string
    {
        $secret = (string)Env::get('JWT_SECRET', '');
        if ($secret === '') $secret = (string)Env::get('APP_KEY', 'change-me');
        return $secret;
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
