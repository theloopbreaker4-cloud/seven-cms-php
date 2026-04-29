<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class CronAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Cron jobs');

        $jobs = CronRunner::list();
        return $this->app->view->render('index', ['jobs' => $jobs]);
    }

    public function run($req, $res, $params)
    {
        $this->requireAdmin($res);
        $name = (string)($params['name'] ?? '');
        if ($name === '') { $res->redirect('/admin/cron'); return; }
        $r = CronRunner::runOnce($name);
        Session::setFlash('Job dispatched: ' . $name);
        $res->redirect('/admin/cron');
    }

    public function toggle($req, $res, $params)
    {
        $this->requireAdmin($res);
        $name    = (string)($params['name'] ?? '');
        $enabled = (string)($_POST['enabled'] ?? '0') === '1';
        if ($name !== '') CronRunner::setEnabled($name, $enabled);
        $res->redirect('/admin/cron');
    }
}
