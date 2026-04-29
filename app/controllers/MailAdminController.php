<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class MailAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Mail queue');

        $filter = (string)($_GET['status'] ?? '');
        $allowed = ['pending', 'sending', 'sent', 'failed'];
        if (!in_array($filter, $allowed, true)) $filter = '';

        $where = $filter ? ' WHERE status = :s ' : '';
        $rows = DB::getAll(
            "SELECT id, to_email, subject, status, attempts, last_error, created_at, sent_at
               FROM mail_queue {$where}
              ORDER BY id DESC LIMIT 200",
            $filter ? [':s' => $filter] : []
        );

        $stats = Mailer::stats();
        return $this->app->view->render('index', compact('rows', 'stats', 'filter'));
    }

    public function flush($req, $res, $params)
    {
        $this->requireAdmin($res);
        $r = Mailer::processQueue(50);
        Session::setFlash("Processed {$r['processed']}, sent {$r['sent']}, failed {$r['failed']}.");
        $res->redirect('/admin/mail');
    }

    public function retry($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id = (int)($params['id'] ?? 0);
        if ($id) {
            DB::execute(
                'UPDATE mail_queue SET status = "pending", attempts = 0, available_at = NOW() WHERE id = :id',
                [':id' => $id]
            );
        }
        $res->redirect('/admin/mail');
    }
}
