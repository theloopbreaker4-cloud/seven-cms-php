<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * RefreshToken — long-lived rotating refresh tokens.
 *
 *   $rt = RefreshToken::issue($userId, $deviceLabel);    // returns plain token (show once)
 *   $rotated = RefreshToken::rotate($plain);             // -> ['user_id'=>..., 'token'=>'newPlain'] or null
 *   RefreshToken::revoke($plain);                        // marks as revoked
 *   RefreshToken::revokeAllForUser($userId);             // logout everywhere
 */
class RefreshToken
{
    public const TTL_DAYS = 30;

    public static function issue(int $userId, ?string $device = null): string
    {
        $plain = bin2hex(random_bytes(32));   // 64 chars
        $hash  = hash('sha256', $plain);
        $exp   = (new DateTimeImmutable('+' . self::TTL_DAYS . ' days'))->format('Y-m-d H:i:s');

        DB::execute(
            'INSERT INTO api_refresh_tokens (user_id, token_hash, device, ip, expires_at)
             VALUES (:u, :h, :d, :ip, :e)',
            [
                ':u'  => $userId,
                ':h'  => $hash,
                ':d'  => $device,
                ':ip' => self::ip(),
                ':e'  => $exp,
            ]
        );
        return $plain;
    }

    /**
     * Verify a plain token and rotate it. Returns null if invalid/expired/revoked.
     * @return array{user_id:int, token:string}|null
     */
    public static function rotate(string $plain): ?array
    {
        $hash = hash('sha256', $plain);
        $row  = DB::findOne('api_refresh_tokens', ' token_hash = :h ', [':h' => $hash]);
        if (!$row) return null;
        if (!empty($row['revoked_at'])) return null;
        if (strtotime((string)$row['expires_at']) < time()) return null;

        // Issue replacement, link parent_id for audit, revoke old.
        $newPlain = bin2hex(random_bytes(32));
        $newHash  = hash('sha256', $newPlain);
        $exp      = (new DateTimeImmutable('+' . self::TTL_DAYS . ' days'))->format('Y-m-d H:i:s');

        DB::execute(
            'INSERT INTO api_refresh_tokens (user_id, token_hash, parent_id, device, ip, expires_at)
             VALUES (:u, :h, :p, :d, :ip, :e)',
            [
                ':u'  => (int)$row['user_id'],
                ':h'  => $newHash,
                ':p'  => (int)$row['id'],
                ':d'  => $row['device'],
                ':ip' => self::ip(),
                ':e'  => $exp,
            ]
        );
        DB::execute(
            'UPDATE api_refresh_tokens
                SET revoked_at = CURRENT_TIMESTAMP, last_used_at = CURRENT_TIMESTAMP
              WHERE id = :id',
            [':id' => (int)$row['id']]
        );

        return ['user_id' => (int)$row['user_id'], 'token' => $newPlain];
    }

    public static function revoke(string $plain): void
    {
        DB::execute(
            'UPDATE api_refresh_tokens SET revoked_at = CURRENT_TIMESTAMP
              WHERE token_hash = :h AND revoked_at IS NULL',
            [':h' => hash('sha256', $plain)]
        );
    }

    public static function revokeAllForUser(int $userId): void
    {
        DB::execute(
            'UPDATE api_refresh_tokens SET revoked_at = CURRENT_TIMESTAMP
              WHERE user_id = :u AND revoked_at IS NULL',
            [':u' => $userId]
        );
    }

    private static function ip(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) return trim(explode(',', (string)$_SERVER[$h])[0]);
        }
        return null;
    }
}
