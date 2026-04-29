<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Calendar events API (admin, Bearer token required)
 *
 * GET /api/calendar/events?month=YYYY-MM  — list events for a month
 */
class CalendarApiController extends ApiController
{
    public function events($req, $res, $params)
    {
        $this->requireAdminToken();

        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $res->jsonError(422, 'Invalid month format. Use YYYY-MM.');
        }

        [$year, $mon] = explode('-', $month);
        $from = sprintf('%04d-%02d-01', $year, $mon);
        $to   = sprintf('%04d-%02d-%02d', $year, $mon, cal_days_in_month(CAL_GREGORIAN, (int)$mon, (int)$year));

        $rows = DB::getAll(
            'SELECT id, title, created_at FROM post WHERE created_at >= ? AND created_at <= ? ORDER BY created_at ASC',
            [$from . ' 00:00:00', $to . ' 23:59:59']
        );

        $lang   = Seven::app()->router->getLanguage();
        $events = [];
        foreach ($rows as $row) {
            $title    = json_decode($row['title'], true) ?? [];
            $events[] = [
                'id'    => (int)$row['id'],
                'date'  => substr($row['created_at'], 0, 10),
                'title' => $title[$lang] ?? $title['en'] ?? 'Post #' . $row['id'],
                'type'  => 'post',
                'color' => 'var(--primary)',
            ];
        }

        Logger::channel('app')->debug('API calendar events', ['month' => $month, 'count' => count($events)]);
        return json_encode($events);
    }
}
