# Notifications

[← Back to docs](index.md)

In-app notifications surface in the **bell** in the admin top bar and on the
dedicated `/admin/notifications` inbox. Two categories:

- **Targeted** — `Notify::user($userId, …)` — visible only to one admin.
- **Broadcast** — `Notify::admins(…)` — visible to every admin.

## API

```php
Notify::admins('order.placed', [
    'title'    => 'New order #' . $order->number,
    'message'  => $order->email . ' just paid ' . Money::format($order->total, $order->currency),
    'url'      => '/admin/ecom/orders/' . $order->id,
    'icon'     => '🛒',
    'severity' => 'success',     // info | success | warning | error
]);

Notify::user(7, 'mention', ['title' => 'Bob mentioned you', 'url' => '/…']);
```

The bell polls `/admin/notifications/feed.json` every 60 seconds and on open;
a red badge shows the unread count.

## Severity → color

| Severity | UI                          |
|----------|-----------------------------|
| info     | blue (default)              |
| success  | green                       |
| warning  | amber                       |
| error    | red                         |

## Dismissal & cleanup

- Clicking an entry in the bell opens its `url` and marks it read.
- "Mark all read" clears the badge for the current admin.
- `notifications.cleanup` cron removes read entries older than 60 days.

## Schema

```
notifications
├── id
├── user_id          NULL = broadcast
├── type             'order.placed' | 'system' | …
├── title, message
├── url              destination on click
├── icon             emoji or token
├── severity
├── meta             JSON
├── read_at
└── created_at
```

## Plugins

Anywhere a plugin emits a domain event you can attach a listener that pushes
a notification:

```php
Event::listen('ecom.order.placed', function ($order) {
    Notify::admins('order.placed', [
        'title' => 'New order #' . $order->number,
        'url'   => '/admin/ecom/orders/' . $order->id,
        'icon'  => '🛒',
    ]);
});
```

Calendar events with `notify_at` automatically create notifications via the
`calendar.sweep` cron — see [Calendar](#mdlink#calendar.md#).

---

[← Back to docs](#mdlink#index.md#)
