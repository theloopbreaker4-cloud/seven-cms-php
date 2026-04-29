# Admin calendar

[← Back to docs](index.md)

The dashboard ships with a calendar widget that combines:

- **Manual events** — created from `/admin/calendar` or via `CalendarEvent::add()`.
- **Auto-derived events** — recent posts (📝 blue), upcoming subscription
  renewals (💳 amber), anything plugins want to surface.

Events with a `notify_at` timestamp create an in-app notification when that
time arrives — handled by the `calendar.sweep` cron job (every minute).

## Adding from PHP

```php
CalendarEvent::add([
    'title'       => 'Launch v2.0',
    'description' => 'Coordinate with marketing.',
    'starts_at'   => '2026-05-15 10:00:00',
    'ends_at'     => '2026-05-15 12:00:00',
    'notify_at'   => '2026-05-15 09:00:00',
    'color'       => '#0891b2',
    'url'         => '/admin/page/edit/12',
    'source_type' => 'launch',
    'source_id'   => 12,
]);
```

`user_id` is `NULL` by default (visible to all admins). Set it to scope an
event to one user.

## Visibility rules

```
SELECT * FROM calendar_events
 WHERE user_id IS NULL OR user_id = :current_admin
```

This means the **viewing admin** sees their own events plus all broadcasts.
Source-derived events (posts, sub renewals) come from `autoEvents()` in the
controller and don't live in the calendar table.

## Reminders

When a row's `notify_at <= NOW()` and `notified = 0`, the cron sweep:

1. Pushes an in-app notification with severity `info` and the icon 🗓.
2. Sets `notified = 1` so it never fires twice.

Reminders are intentionally idempotent — a missed cron tick won't replay old
notifications, it'll catch up on the next run.

## Plugin integration

Plugins can populate the calendar by listening to their own events and
adding rows:

```php
Event::listen('content.entry.scheduled', function ($entry) {
    CalendarEvent::add([
        'title'       => '📝 ' . $entry->title,
        'starts_at'   => $entry->scheduledAt,
        'source_type' => 'content_entry',
        'source_id'   => $entry->id,
        'url'         => '/admin/content/entries/' . $entry->id,
    ]);
});
```

## Schema

```
calendar_events
├── id
├── user_id          NULL = broadcast
├── title, description
├── starts_at, ends_at
├── color            CSS color (hex or var)
├── source_type      'manual' | 'post' | 'order' | …
├── source_id
├── notify_at        when to fire reminder
├── notified         0/1 — sweep flips this
├── url              click target
└── created_at
```

---

[← Back to docs](#mdlink#index.md#)
