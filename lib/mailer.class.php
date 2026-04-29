<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Mailer — async email queue.
 *
 *   Mailer::queue('to@x.com', 'Subject', '<p>body</p>');
 *   Mailer::queue($to, $subject, $html, ['from' => '...', 'reply_to' => '...']);
 *
 * Worker (CLI):
 *   php bin/sev mail:send             — process up to 50 pending
 *   php bin/sev mail:send --limit=N
 *
 * Plug a real SMTP transport by binding 'mailer.transport' in the container:
 *   $svc must implement send(string $to, string $subject, string $body, array $headers): bool
 */
class Mailer
{
    public const STATUS_PENDING  = 'pending';
    public const STATUS_SENDING  = 'sending';
    public const STATUS_SENT     = 'sent';
    public const STATUS_FAILED   = 'failed';

    /**
     * Queue an email. Returns the queue id.
     * @param array{from?:string,from_name?:string,reply_to?:string,text?:string,headers?:array<string,string>,delay?:int,max_attempts?:int} $opts
     */
    public static function queue(string $to, string $subject, string $html, array $opts = []): int
    {
        $now    = date('Y-m-d H:i:s');
        $delay  = (int)($opts['delay'] ?? 0);
        $avail  = $delay > 0 ? date('Y-m-d H:i:s', time() + $delay) : $now;

        DB::execute(
            'INSERT INTO mail_queue
             (to_email, to_name, from_email, from_name, reply_to, subject, body_html, body_text, headers_json,
              max_attempts, status, available_at, created_at)
             VALUES (:te, :tn, :fe, :fn, :rt, :s, :h, :t, :hj, :ma, "pending", :av, :ca)',
            [
                ':te' => $to,
                ':tn' => $opts['to_name']    ?? null,
                ':fe' => $opts['from']       ?? (string)Env::get('MAIL_FROM', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')),
                ':fn' => $opts['from_name']  ?? (string)Env::get('MAIL_FROM_NAME', Setting::get('site_name', 'Seven CMS')),
                ':rt' => $opts['reply_to']   ?? null,
                ':s'  => $subject,
                ':h'  => $html,
                ':t'  => $opts['text']       ?? null,
                ':hj' => isset($opts['headers']) ? json_encode($opts['headers']) : null,
                ':ma' => (int)($opts['max_attempts'] ?? 5),
                ':av' => $avail,
                ':ca' => $now,
            ]
        );

        $id = (int)DB::getCell('SELECT LAST_INSERT_ID()');
        Event::dispatch('mail.queued', ['id' => $id, 'to' => $to, 'subject' => $subject]);
        return $id;
    }

    /**
     * Process up to $limit pending messages. Used by `bin/sev mail:send`.
     * @return array{processed:int,sent:int,failed:int}
     */
    public static function processQueue(int $limit = 50): array
    {
        $rows = DB::getAll(
            'SELECT * FROM mail_queue
             WHERE status = "pending" AND available_at <= NOW()
             ORDER BY id ASC LIMIT ' . max(1, $limit)
        );

        $sent = 0; $failed = 0;
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            DB::execute('UPDATE mail_queue SET status = "sending", attempts = attempts + 1 WHERE id = :id', [':id' => $id]);

            try {
                self::deliver($row);
                DB::execute(
                    'UPDATE mail_queue SET status = "sent", sent_at = NOW(), last_error = NULL WHERE id = :id',
                    [':id' => $id]
                );
                Event::dispatch('mail.sent', ['id' => $id, 'to' => $row['to_email']]);
                $sent++;
            } catch (\Throwable $e) {
                $attempts = (int)$row['attempts'] + 1;
                $maxA     = (int)$row['max_attempts'];
                $next     = $attempts >= $maxA ? 'failed' : 'pending';

                // Exponential backoff: 1m, 5m, 15m, 60m, 240m
                $backoff = [60, 300, 900, 3600, 14400];
                $delay   = $backoff[min($attempts - 1, count($backoff) - 1)] ?? 60;

                DB::execute(
                    'UPDATE mail_queue
                       SET status = :st, last_error = :err, available_at = DATE_ADD(NOW(), INTERVAL :d SECOND)
                     WHERE id = :id',
                    [':st' => $next, ':err' => $e->getMessage(), ':d' => $delay, ':id' => $id]
                );
                Event::dispatch('mail.failed', ['id' => $id, 'attempts' => $attempts, 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed];
    }

    /** Send a single message immediately (bypassing queue). Returns true on success. */
    public static function sendNow(string $to, string $subject, string $html, array $opts = []): bool
    {
        try {
            self::deliver([
                'to_email'   => $to,
                'to_name'    => $opts['to_name']   ?? null,
                'from_email' => $opts['from']      ?? null,
                'from_name'  => $opts['from_name'] ?? null,
                'reply_to'   => $opts['reply_to']  ?? null,
                'subject'    => $subject,
                'body_html'  => $html,
                'body_text'  => $opts['text']      ?? null,
                'headers_json' => isset($opts['headers']) ? json_encode($opts['headers']) : null,
            ]);
            return true;
        } catch (\Throwable $e) {
            Logger::channel('mail')->error('sendNow failed', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /** @return array{pending:int,sending:int,sent:int,failed:int} */
    public static function stats(): array
    {
        $rows = DB::getAll('SELECT status, COUNT(*) c FROM mail_queue GROUP BY status');
        $out  = ['pending' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($rows as $r) $out[$r['status']] = (int)$r['c'];
        return $out;
    }

    // ──────────────────────────────────────────────────────────────────

    private static function deliver(array $row): void
    {
        $to       = (string)$row['to_email'];
        $subject  = (string)$row['subject'];
        $body     = (string)($row['body_html'] ?? '');
        $from     = (string)($row['from_email'] ?? Env::get('MAIL_FROM', 'no-reply@localhost'));
        $fromName = (string)($row['from_name']  ?? '');
        $replyTo  = (string)($row['reply_to']   ?? '');

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
            'From: ' . ($fromName !== '' ? "{$fromName} <{$from}>" : $from),
        ];
        if ($replyTo !== '') $headers[] = 'Reply-To: ' . $replyTo;

        if (!empty($row['headers_json'])) {
            $extra = json_decode((string)$row['headers_json'], true);
            if (is_array($extra)) {
                foreach ($extra as $k => $v) $headers[] = $k . ': ' . $v;
            }
        }

        // Custom transport via container (e.g. SMTP, SES).
        if (class_exists('Container') && Container::has('mailer.transport')) {
            $svc = Container::get('mailer.transport');
            if (is_object($svc) && method_exists($svc, 'send')) {
                $ok = $svc->send($to, $subject, $body, $headers);
                if (!$ok) throw new \RuntimeException('Transport returned false');
                return;
            }
        }

        $ok = @mail($to, $subject, $body, implode("\r\n", $headers));
        if (!$ok) throw new \RuntimeException('PHP mail() returned false');
    }
}
