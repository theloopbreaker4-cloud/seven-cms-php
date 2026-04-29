<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class EcomSubscriptionsAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('ecom.subscriptions.view', $res);
        $this->app->setTitle('Subscriptions');
        $status = (string)($_GET['status'] ?? '');
        $where = []; $args = [];
        if ($status) { $where[] = 'status = :s'; $args[':s'] = $status; }
        $sql = 'SELECT s.*, c.email FROM ecom_subscriptions s
                  LEFT JOIN ecom_customers c ON c.id = s.customer_id'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY s.id DESC LIMIT 200';
        $subs = DB::getAll($sql, $args) ?: [];
        return $this->app->view->render('ecom/subscriptions/index', compact('subs', 'status'));
    }

    public function cancel($req, $res, $params)
    {
        $this->requirePerm('ecom.subscriptions.manage', $res);
        $id = (int)($params[0] ?? 0);
        $sub = Subscription::findById($id);
        if (!$sub) $res->errorCode(404);
        $sub->cancelImmediately();
        ActivityLog::log('ecom.subscription.cancel', 'ecom_subscriptions', $id);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/ecom/subscriptions');
        exit;
    }

    private function requirePerm(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }
}
