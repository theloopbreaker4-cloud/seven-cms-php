<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Orders admin: browse, view, manually transition state, refund.
 *
 *   GET  /admin/ecom/orders
 *   GET  /admin/ecom/orders/view/:id
 *   POST /admin/ecom/orders/markPaid/:id
 *   POST /admin/ecom/orders/markShipped/:id
 *   POST /admin/ecom/orders/markCancelled/:id
 *   POST /admin/ecom/orders/refund/:id           body: amount, reason
 */
class EcomOrdersAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('ecom.orders.view', $res);
        $this->app->setTitle('Orders');

        $status = (string)($_GET['status'] ?? '');
        $where  = []; $args = [];
        if ($status) { $where[] = 'status = :s'; $args[':s'] = $status; }

        $sql = 'SELECT * FROM ecom_orders' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY id DESC LIMIT 200';
        $orders = DB::getAll($sql, $args) ?: [];
        return $this->app->view->render('ecom/orders/index', compact('orders', 'status'));
    }

    public function view($req, $res, $params)
    {
        $this->requirePerm('ecom.orders.view', $res);
        $id    = (int)($params[0] ?? 0);
        $order = Order::findById($id);
        if (!$order) $res->errorCode(404);

        $items    = $order->items();
        $payments = $order->payments();
        $customer = $order->customerId ? DB::findOne('ecom_customers', ' id = :id ', [':id' => $order->customerId]) : null;
        $this->app->setTitle('Order ' . $order->number);

        return $this->app->view->render('ecom/orders/view', compact('order', 'items', 'payments', 'customer'));
    }

    public function markPaid($req, $res, $params)
    {
        $this->requirePerm('ecom.orders.manage', $res);
        $order = Order::findById((int)($params[0] ?? 0));
        if (!$order) $res->errorCode(404);
        OrderService::fulfillPaidOrder($order);
        $this->back($order->id);
    }

    public function markShipped($req, $res, $params)
    {
        $this->requirePerm('ecom.orders.manage', $res);
        $order = Order::findById((int)($params[0] ?? 0));
        if (!$order) $res->errorCode(404);
        $order->markFulfilled();
        $tracking = (string)($_POST['tracking'] ?? '') ?: null;
        if (class_exists('EcomMail')) EcomMail::orderShipped($order, $tracking);
        ActivityLog::log('ecom.order.shipped', 'ecom_orders', (int)$order->id);
        $this->back($order->id);
    }

    public function markCancelled($req, $res, $params)
    {
        $this->requirePerm('ecom.orders.manage', $res);
        $order = Order::findById((int)($params[0] ?? 0));
        if (!$order) $res->errorCode(404);
        $order->markCancelled((string)($_POST['reason'] ?? null));
        if (class_exists('EcomMail')) EcomMail::orderCancelled($order);
        $this->back($order->id);
    }

    public function refund($req, $res, $params)
    {
        $this->requirePerm('ecom.orders.refund', $res);
        $order = Order::findById((int)($params[0] ?? 0));
        if (!$order) $res->errorCode(404);

        $amount = Money::fromInput((string)($_POST['amount'] ?? ''), $order->currency);
        if ($amount <= 0 || $amount > $order->total) {
            Session::setFlash('Invalid refund amount.');
            $this->back($order->id);
        }
        $reason = (string)($_POST['reason'] ?? '') ?: null;
        $payment = DB::findOne('ecom_payments', ' order_id = :o AND status = "paid" ', [':o' => $order->id]);
        if (!$payment || empty($payment['gateway_id'])) {
            Session::setFlash('No paid payment found to refund.');
            $this->back($order->id);
        }

        $gateway = GatewayRegistry::get((string)$payment['gateway']);
        $gid     = $gateway->refund((string)$payment['gateway_id'], $amount, $order->currency, $reason);

        $admin = Auth::getCurrentUser();
        DB::execute(
            'INSERT INTO ecom_refunds (order_id, payment_id, amount, reason, gateway_id, created_by)
             VALUES (:o, :p, :a, :r, :g, :u)',
            [
                ':o' => $order->id, ':p' => (int)$payment['id'], ':a' => $amount,
                ':r' => $reason, ':g' => $gid, ':u' => (int)($admin->id ?? 0),
            ]
        );
        DB::execute(
            'UPDATE ecom_payments SET refunded_amount = refunded_amount + :a WHERE id = :id',
            [':a' => $amount, ':id' => (int)$payment['id']]
        );

        $newStatus = $amount === $order->total ? 'refunded' : 'partially_refunded';
        DB::execute(
            'UPDATE ecom_orders SET status = :s, payment_status = :ps, refunded_at = NOW() WHERE id = :id',
            [':s' => $newStatus, ':ps' => $newStatus, ':id' => $order->id]
        );
        ActivityLog::log('ecom.order.refund', 'ecom_orders', (int)$order->id, "Refunded {$amount}");
        Event::dispatch('ecom.order.refunded', ['order' => $order, 'amount' => $amount]);

        $this->back($order->id);
    }

    private function requirePerm(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }

    private function back(?int $id = null): void
    {
        $lang = $this->app->router->getLanguage();
        $url  = '/' . $lang . '/admin/ecom/orders' . ($id ? '/view/' . $id : '');
        header('Location: ' . $url);
        exit;
    }
}
