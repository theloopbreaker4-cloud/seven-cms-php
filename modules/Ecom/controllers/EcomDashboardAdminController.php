<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Sales dashboard — counters + 30-day revenue chart.
 *
 *   /admin/ecom              dashboard
 *   /admin/ecom/reports      same view, kept for permission separation
 */
class EcomDashboardAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('ecom.reports.view', $res);
        $this->app->setTitle('Shop Dashboard');

        $currency = (string)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.currency"') ?? 'USD');

        $stats = [
            'orders_today'       => (int)(DB::getCell("SELECT COUNT(*) FROM ecom_orders WHERE DATE(created_at) = CURDATE()") ?? 0),
            'orders_total'       => (int)(DB::getCell("SELECT COUNT(*) FROM ecom_orders") ?? 0),
            'revenue_today'      => (int)(DB::getCell("SELECT COALESCE(SUM(total),0) FROM ecom_orders WHERE payment_status='paid' AND DATE(paid_at) = CURDATE()") ?? 0),
            'revenue_30d'        => (int)(DB::getCell("SELECT COALESCE(SUM(total),0) FROM ecom_orders WHERE payment_status='paid' AND paid_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)") ?? 0),
            'pending_orders'     => (int)(DB::getCell("SELECT COUNT(*) FROM ecom_orders WHERE status IN ('pending','partially_paid')") ?? 0),
            'unfulfilled_orders' => (int)(DB::getCell("SELECT COUNT(*) FROM ecom_orders WHERE fulfillment_status = 'unfulfilled' AND payment_status = 'paid'") ?? 0),
            'active_subs'        => (int)(DB::getCell("SELECT COUNT(*) FROM ecom_subscriptions WHERE status IN ('active','trialing')") ?? 0),
            'customers_total'    => (int)(DB::getCell("SELECT COUNT(*) FROM ecom_customers") ?? 0),
        ];

        // Revenue per day, last 30 days (zero-fill missing).
        $rows = DB::getAll(
            "SELECT DATE(paid_at) AS d, SUM(total) AS rev
               FROM ecom_orders
              WHERE payment_status = 'paid' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
              GROUP BY DATE(paid_at) ORDER BY d ASC"
        ) ?: [];
        $byDay = [];
        foreach ($rows as $r) $byDay[$r['d']] = (int)$r['rev'];

        $chart = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $chart[] = ['date' => $d, 'revenue' => $byDay[$d] ?? 0];
        }

        $recentOrders = DB::getAll('SELECT * FROM ecom_orders ORDER BY id DESC LIMIT 10') ?: [];

        return $this->app->view->render('ecom/dashboard', compact('stats', 'chart', 'currency', 'recentOrders'));
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
