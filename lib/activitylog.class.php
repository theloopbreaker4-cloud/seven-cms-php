<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ActivityLog — append-only audit trail.
 *
 *   ActivityLog::log('media.upload', 'media', $id, 'Uploaded ' . $name);
 *   ActivityLog::log('user.login',  null, null, "User signed in", ['provider' => 'local']);
 *
 * Reads are performed via DB::getAll directly from the AdminController.
 */
class ActivityLog
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        array $meta = []
    ): void {
        $userId = null;
        if (class_exists('Auth')) {
            $u = Auth::getCurrentUser();
            $userId = $u && isset($u->id) ? (int)$u->id : null;
        }

        DB::execute(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, meta, ip, user_agent)
             VALUES (:u, :a, :et, :ei, :d, :m, :ip, :ua)',
            [
                ':u'  => $userId,
                ':a'  => $action,
                ':et' => $entityType,
                ':ei' => $entityId,
                ':d'  => $description,
                ':m'  => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':ip' => self::clientIp(),
                ':ua' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]
        );
    }

    private static function clientIp(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) {
                return trim(explode(',', (string)$_SERVER[$h])[0]);
            }
        }
        return null;
    }
}
