<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * CoreJobs — registers the cron jobs that ship with the core.
 *
 * Plugin jobs go in the plugin's own `boot()`. This class only handles
 * jobs that always exist regardless of which plugins are enabled.
 *
 * Called from Module::loadAll() at the end of the boot phase.
 */
class CoreJobs
{
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) return;
        self::$registered = true;

        if (!class_exists('CronRunner')) return;

        try {
            // Ensure the cron_jobs table exists. If migrations haven't run
            // yet (fresh install), silently skip.
            $exists = DB::getCell("SHOW TABLES LIKE 'cron_jobs'");
            if (!$exists) return;
        } catch (\Throwable $e) {
            return;
        }

        // Process the email queue every minute. Pull up to 50 messages per tick.
        CronRunner::register('mail.queue.flush', '@minute', [Mailer::class, 'processQueue']);

        // Daily cleanup: drop sent messages older than 30 days.
        CronRunner::register('mail.queue.cleanup', '@daily', function (): void {
            DB::execute('DELETE FROM mail_queue WHERE status = "sent" AND sent_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        });

        // Daily: dismiss old read notifications (kept 60 days for audit).
        CronRunner::register('notifications.cleanup', '@daily', function (): void {
            if (DB::getCell("SHOW TABLES LIKE 'notifications'")) {
                DB::execute('DELETE FROM notifications WHERE read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 60 DAY)');
            }
        });

        // Per-minute calendar reminder sweep.
        if (class_exists('CalendarEvent')) {
            CronRunner::register('calendar.sweep', '@minute', [CalendarEvent::class, 'sweep']);
        }
    }
}
