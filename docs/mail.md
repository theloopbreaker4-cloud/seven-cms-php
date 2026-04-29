# Mail queue

[← Back to docs](index.md)

All outbound email goes through `Mailer::queue()` — a row is inserted in
`mail_queue`, and the `mail.queue.flush` cron job (every minute) sends what's
pending. Nothing blocks the request.

## Quick send

```php
Mailer::queue(
    'customer@example.com',
    'Welcome to Seven',
    '<p>Hello!</p>',
    [
        'from'     => 'noreply@seven.local',
        'reply_to' => 'support@seven.local',
        'text'     => 'Hello!',
        'delay'    => 60,           // seconds
    ]
);
```

## CLI

```bash
php bin/sev mail:send             # process up to 50 pending
php bin/sev mail:send --limit=200
php bin/sev mail:status           # queue counts
```

## Retries & backoff

`max_attempts` defaults to **5**. After a failure we mark the row `pending`
again with an exponential `available_at` delay: 1m, 5m, 15m, 60m, 240m. After
the last attempt the status flips to `failed` and the message stops being
retried.

You can manually retry from `/admin/mail` or by running:

```sql
UPDATE mail_queue SET status='pending', attempts=0, available_at=NOW() WHERE id=42;
```

## SMTP / external transports

The default transport is PHP's `mail()`. Bind a custom service to override:

```php
class SmtpTransport
{
    public function send(string $to, string $subject, string $body, array $headers): bool
    {
        // … PHPMailer / Symfony Mailer / SES / Mailgun
        return true;
    }
}

// Anywhere during boot:
Container::singleton('mailer.transport', fn() => new SmtpTransport());
```

The queue worker calls `Mailer::deliver()` → finds the transport in the
container → invokes `send()`. Returning `false` (or throwing) is treated as
a delivery failure and triggers backoff.

## Schema

```
mail_queue
├── id
├── to_email, to_name
├── from_email, from_name
├── reply_to
├── subject
├── body_html, body_text
├── headers_json     extra headers
├── attempts, max_attempts
├── status           pending | sending | sent | failed
├── last_error
├── available_at     when it becomes eligible for the next attempt
├── sent_at
└── created_at
```

## Hooks

- `mail.queued`  — `{ id, to, subject }`
- `mail.sent`    — `{ id, to }`
- `mail.failed`  — `{ id, attempts, error }`

---

[← Back to docs](#mdlink#index.md#)
