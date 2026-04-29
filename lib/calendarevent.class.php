<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * CalendarEvent — admin-side calendar items.
 *
 * Plugins push events here for posts, scheduled launches, subscription renewals,
 * etc. The dashboard widget reads from `calendar_events` (visible to all admins
 * when user_id is NULL) plus inferred events from `post.created_at`.
 *
 * `notify_at` — if set, CalendarEvent::sweep() (cron @minute) creates a
 * notification when that time arrives, and flips `notified = 1`.
 */
class CalendarEvent
{
    public static function add(array $data): int
    {
        DB::execute(
            'INSERT INTO calendar_events
                (user_id, title, description, starts_at, ends_at, color, source_type, source_id, notify_at, url, created_at)
             VALUES (:u, :t, :d, :s, :e, :c, :st, :sid, :n, :url, :ca)',
            [
                ':u'   => $data['user_id'] ?? null,
                ':t'   => (string)($data['title'] ?? ''),
                ':d'   => $data['description'] ?? null,
                ':s'   => $data['starts_at'] ?? date('Y-m-d H:i:s'),
                ':e'   => $data['ends_at']   ?? null,
                ':c'   => $data['color']     ?? null,
                ':st'  => (string)($data['source_type'] ?? 'manual'),
                ':sid' => isset($data['source_id']) ? (int)$data['source_id'] : null,
                ':n'   => $data['notify_at'] ?? null,
                ':url' => $data['url']       ?? null,
                ':ca'  => date('Y-m-d H:i:s'),
            ]
        );
        $id = (int)DB::getCell('SELECT LAST_INSERT_ID()');
        Event::dispatch('calendar.event.created', ['id' => $id]);
        return $id;
    }

    public static function delete(int $id, int $userId): void
    {
        // Allow deleting own events; broadcasts are admin-only.
        DB::execute(
            'DELETE FROM calendar_events
              WHERE id = :id AND (user_id IS NULL OR user_id = :uid)',
            [':id' => $id, ':uid' => $userId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function inRange(int $userId, string $from, string $to): array
    {
        return DB::getAll(
            'SELECT * FROM calendar_events
              WHERE (user_id IS NULL OR user_id = :uid)
                AND starts_at >= :f AND starts_at < :t
              ORDER BY starts_at ASC',
            [':uid' => $userId, ':f' => $from, ':t' => $to]
        ) ?: [];
    }

    /** Run from cron @minute — push notifications for events whose time has arrived. */
    public static function sweep(): void
    {
        $rows = DB::getAll(
            'SELECT * FROM calendar_events
              WHERE notified = 0 AND notify_at IS NOT NULL AND notify_at <= NOW()
              ORDER BY id ASC LIMIT 200'
        ) ?: [];

        foreach ($rows as $row) {
            $title   = (string)$row['title'];
            $message = "Starts " . substr((string)$row['starts_at'], 0, 16);
            $url     = $row['url'] ?: '/admin/';

            if ($row['user_id'] === null) {
                Notify::admins('calendar.event', [
                    'title'    => '🗓 ' . $title,
                    'message'  => $message,
                    'url'      => $url,
                    'icon'     => '🗓',
                    'severity' => 'info',
                ]);
            } else {
                Notify::user((int)$row['user_id'], 'calendar.event', [
                    'title'    => '🗓 ' . $title,
                    'message'  => $message,
                    'url'      => $url,
                    'icon'     => '🗓',
                    'severity' => 'info',
                ]);
            }

            DB::execute('UPDATE calendar_events SET notified = 1 WHERE id = :id', [':id' => (int)$row['id']]);
        }
    }
}
