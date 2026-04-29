<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Notify — in-app notifications for admins.
 *
 * Notify::user($userId, 'system', ['title' => '…', 'message' => '…'])
 * Notify::admins('order.placed', ['title' => 'New order #1234', 'url' => '/admin/ecom/orders/1234'])
 *
 * Severity affects styling in the bell dropdown:
 *   'info' (default) | 'success' | 'warning' | 'error'
 */
class Notify
{
    public static function user(int $userId, string $type, array $data): int
    {
        return self::push($userId, $type, $data);
    }

    /** Broadcast to every admin (user_id = NULL). */
    public static function admins(string $type, array $data): int
    {
        return self::push(null, $type, $data);
    }

    /** Recent items for a user (own + broadcast). */
    public static function recent(int $userId, int $limit = 20): array
    {
        return DB::getAll(
            'SELECT * FROM notifications
              WHERE user_id IS NULL OR user_id = :uid
              ORDER BY id DESC LIMIT ' . max(1, $limit),
            [':uid' => $userId]
        ) ?: [];
    }

    /** Count unread. */
    public static function unreadCount(int $userId): int
    {
        return (int)(DB::getCell(
            'SELECT COUNT(*) FROM notifications
              WHERE (user_id IS NULL OR user_id = :uid)
                AND read_at IS NULL',
            [':uid' => $userId]
        ) ?? 0);
    }

    public static function markRead(int $userId, int $id): void
    {
        DB::execute(
            'UPDATE notifications SET read_at = NOW()
              WHERE id = :id AND (user_id IS NULL OR user_id = :uid) AND read_at IS NULL',
            [':id' => $id, ':uid' => $userId]
        );
    }

    public static function markAllRead(int $userId): void
    {
        DB::execute(
            'UPDATE notifications SET read_at = NOW()
              WHERE (user_id IS NULL OR user_id = :uid) AND read_at IS NULL',
            [':uid' => $userId]
        );
    }

    private static function push(?int $userId, string $type, array $data): int
    {
        DB::execute(
            'INSERT INTO notifications
                (user_id, type, title, message, url, icon, severity, meta, created_at)
             VALUES (:u, :t, :ti, :m, :url, :ic, :sv, :me, :ca)',
            [
                ':u'   => $userId,
                ':t'   => $type,
                ':ti'  => (string)($data['title']   ?? $type),
                ':m'   => $data['message']  ?? null,
                ':url' => $data['url']      ?? null,
                ':ic'  => $data['icon']     ?? null,
                ':sv'  => self::severity($data['severity'] ?? 'info'),
                ':me'  => isset($data['meta']) ? json_encode($data['meta']) : null,
                ':ca'  => date('Y-m-d H:i:s'),
            ]
        );
        $id = (int)DB::getCell('SELECT LAST_INSERT_ID()');
        Event::dispatch('notification.created', ['id' => $id, 'type' => $type, 'user_id' => $userId]);
        return $id;
    }

    private static function severity(string $s): string
    {
        $allowed = ['info', 'success', 'warning', 'error'];
        return in_array($s, $allowed, true) ? $s : 'info';
    }
}
