<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class CalendarAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Calendar');

        $userId = (int)Session::get('user_id');
        $month  = (string)($_GET['month'] ?? date('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

        $start = $month . '-01 00:00:00';
        $end   = date('Y-m-d 00:00:00', strtotime($start . ' +1 month'));

        $events = CalendarEvent::inRange($userId, $start, $end);

        // Augment with auto-derived events (recent posts / scheduled subs).
        $autoEvents = $this->autoEvents($start, $end);
        $events = array_merge($events, $autoEvents);

        return $this->app->view->render('index', compact('events', 'month'));
    }

    /** GET /admin/calendar/feed.json?from=YYYY-MM-DD&to=YYYY-MM-DD */
    public function feed($req, $res, $params)
    {
        $this->requireAdmin($res);
        $userId = (int)Session::get('user_id');
        $from = (string)($_GET['from'] ?? date('Y-m-01 00:00:00'));
        $to   = (string)($_GET['to']   ?? date('Y-m-d 00:00:00', strtotime('+1 month')));

        $events = CalendarEvent::inRange($userId, $from, $to);
        $events = array_merge($events, $this->autoEvents($from, $to));

        header('Content-Type: application/json');
        echo json_encode(['events' => $events]);
        exit;
    }

    public function store($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $title    = trim((string)($_POST['title'] ?? ''));
        $startsAt = (string)($_POST['starts_at'] ?? '');
        $notifyAt = trim((string)($_POST['notify_at'] ?? ''));

        if ($title === '' || $startsAt === '') {
            Session::setFlash('Title and start time are required.');
            $res->redirectUrl('/' . $this->app->router->getLanguage() . '/admin/calendar');
            return;
        }

        CalendarEvent::add([
            'user_id'     => (int)Session::get('user_id'),
            'title'       => $title,
            'description' => trim((string)($_POST['description'] ?? '')) ?: null,
            'starts_at'   => $startsAt,
            'ends_at'     => trim((string)($_POST['ends_at'] ?? '')) ?: null,
            'color'       => trim((string)($_POST['color'] ?? '')) ?: null,
            'notify_at'   => $notifyAt !== '' ? $notifyAt : null,
            'source_type' => 'manual',
        ]);

        Session::setFlash('Event added.');
        $res->redirectUrl('/' . $this->app->router->getLanguage() . '/admin/calendar');
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id = (int)($params['id'] ?? 0);
        if ($id) CalendarEvent::delete($id, (int)Session::get('user_id'));
        $res->redirectUrl('/' . $this->app->router->getLanguage() . '/admin/calendar');
    }

    private function autoEvents(string $from, string $to): array
    {
        $events = [];

        // Recent posts published in range
        $posts = DB::getAll(
            'SELECT id, title, created_at FROM post WHERE created_at >= :f AND created_at < :t ORDER BY created_at',
            [':f' => $from, ':t' => $to]
        ) ?: [];
        foreach ($posts as $p) {
            $title = json_decode($p['title'] ?? '', true);
            $events[] = [
                'id'          => 'post-' . $p['id'],
                'title'       => '📝 ' . (is_array($title) ? ($title['en'] ?? array_values($title)[0] ?? '') : $p['title']),
                'starts_at'   => $p['created_at'],
                'color'       => 'var(--info)',
                'source_type' => 'post',
                'source_id'   => (int)$p['id'],
                'url'         => '/admin/blog/edit/' . $p['id'],
            ];
        }

        // Subscription renewals coming up
        if (DB::getCell("SHOW TABLES LIKE 'ecom_subscriptions'")) {
            $subs = DB::getAll(
                'SELECT id, current_period_end FROM ecom_subscriptions
                  WHERE status IN ("active","trialing")
                    AND current_period_end >= :f AND current_period_end < :t',
                [':f' => $from, ':t' => $to]
            ) ?: [];
            foreach ($subs as $s) {
                $events[] = [
                    'id'          => 'sub-' . $s['id'],
                    'title'       => '💳 Sub renewal #' . $s['id'],
                    'starts_at'   => $s['current_period_end'],
                    'color'       => 'var(--warning)',
                    'source_type' => 'subscription',
                    'source_id'   => (int)$s['id'],
                    'url'         => '/admin/ecom/subscriptions',
                ];
            }
        }

        return $events;
    }
}
