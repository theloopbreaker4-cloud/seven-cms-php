<?php

defined('_SEVEN') or die('No direct script access allowed');

class HomeAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Dashboard');

        $userId = (int)Session::get('user_id');
        $lang   = $this->app->router->getLanguage();

        // Counts
        $pageCount = (int)(DB::getCell('SELECT COUNT(*) FROM page WHERE is_published = 1') ?? 0);
        $postCount = (int)(DB::getCell('SELECT COUNT(*) FROM post WHERE is_published = 1') ?? 0);
        $userCount = (int)(DB::getCell('SELECT COUNT(*) FROM users WHERE is_active = 1') ?? 0);
        $mediaCount = (int)(DB::getCell("SELECT COUNT(*) FROM media WHERE 1=1") ?? 0);

        // E-commerce snapshot (only if module installed)
        $ecom = null;
        if (DB::getCell("SHOW TABLES LIKE 'ecom_orders'")) {
            $today = date('Y-m-d');
            $ecom = [
                'orders_today' => (int)(DB::getCell('SELECT COUNT(*) FROM ecom_orders WHERE DATE(created_at) = :d', [':d' => $today]) ?? 0),
                'revenue_today' => (int)(DB::getCell('SELECT COALESCE(SUM(total),0) FROM ecom_orders WHERE DATE(created_at) = :d AND payment_status = "paid"', [':d' => $today]) ?? 0),
                'pending_orders' => (int)(DB::getCell('SELECT COUNT(*) FROM ecom_orders WHERE status = "pending"') ?? 0),
                'currency'      => (string)Setting::get('ecom.currency', 'USD'),
            ];
        }

        // Mail + cron health
        $mailStats = class_exists('Mailer') ? Mailer::stats() : null;
        $cronJobs  = class_exists('CronRunner')
            ? DB::getAll('SELECT name, last_status, last_run_at, next_run_at FROM cron_jobs WHERE is_enabled = 1 ORDER BY name LIMIT 8') ?: []
            : [];

        // Recent activity
        $activity = [];
        if (DB::getCell("SHOW TABLES LIKE 'activity_log'")) {
            $activity = DB::getAll(
                'SELECT a.action, a.entity_type, a.entity_id, a.description, a.created_at, u.email
                   FROM activity_log a
                   LEFT JOIN users u ON u.id = a.user_id
                  ORDER BY a.id DESC LIMIT 10'
            ) ?: [];
        }

        // Calendar events for current month + recent posts as derived events
        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd   = date('Y-m-01 00:00:00', strtotime('+1 month'));

        $calEvents = class_exists('CalendarEvent')
            ? CalendarEvent::inRange($userId, $monthStart, $monthEnd)
            : [];
        $recentPosts = DB::getAll(
            'SELECT id, title, created_at FROM post
              WHERE created_at >= :f AND created_at < :t
              ORDER BY created_at DESC LIMIT 50',
            [':f' => $monthStart, ':t' => $monthEnd]
        ) ?: [];
        foreach ($recentPosts as $row) {
            $title = json_decode($row['title'], true);
            $calEvents[] = [
                'id'          => 'post-' . $row['id'],
                'title'       => is_array($title) ? ($title[$lang] ?? $title['en'] ?? '') : (string)$row['title'],
                'starts_at'   => $row['created_at'],
                'color'       => 'var(--info)',
                'source_type' => 'post',
                'url'         => '/admin/blog/edit/' . $row['id'],
            ];
        }

        // System info — small read-only panel
        $sys = [
            'php_version'  => PHP_VERSION,
            'environment'  => defined('ENVIRONMENT') ? ENVIRONMENT : 'dev',
            'memory_limit' => (string)ini_get('memory_limit'),
            'upload_max'   => (string)ini_get('upload_max_filesize'),
            'tz'           => date_default_timezone_get(),
            'storage_used' => self::dirSize(ROOT_DIR . '/public/uploads'),
            'cms_version'  => self::readCmsVersion(),
        ];

        // Unread notifications count (also rendered in topbar but used in widget)
        $unreadNotifications = class_exists('Notify') ? Notify::unreadCount($userId) : 0;

        return $this->app->view->render('index', compact(
            'pageCount', 'postCount', 'userCount', 'mediaCount',
            'ecom', 'mailStats', 'cronJobs',
            'activity', 'calEvents',
            'sys', 'unreadNotifications'
        ));
    }

    private static function readCmsVersion(): string
    {
        $f = ROOT_DIR . '/CHANGELOG.md';
        if (!is_file($f)) return 'unknown';
        $head = (string)file_get_contents($f, false, null, 0, 800);
        if (preg_match('/##\s*\[([^\]]+)\]/', $head, $m)) return $m[1];
        return 'dev';
    }

    private static function dirSize(string $dir): int
    {
        // Walking uploads/ on every dashboard load is too slow on networked
        // filesystems (WSL /mnt/d). Cache the result for 5 minutes.
        if (!is_dir($dir)) return 0;
        $cacheFile = ROOT_DIR . '/storage/cache/dashboard_dirsize.json';
        if (is_file($cacheFile)) {
            $cached = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cached) && ($cached['dir'] ?? '') === $dir
                && (time() - (int)($cached['ts'] ?? 0)) < 300) {
                return (int)$cached['size'];
            }
        }
        $size = 0;
        try {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) { if ($f->isFile()) $size += (int)$f->getSize(); }
        } catch (\Throwable $e) { /* permission or vanished — best effort */ }

        if (!is_dir(dirname($cacheFile))) @mkdir(dirname($cacheFile), 0755, true);
        @file_put_contents($cacheFile, json_encode(['dir' => $dir, 'size' => $size, 'ts' => time()]));
        return $size;
    }
}
