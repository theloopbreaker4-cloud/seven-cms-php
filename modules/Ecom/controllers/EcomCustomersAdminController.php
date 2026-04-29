<?php

defined('_SEVEN') or die('No direct script access allowed');

class EcomCustomersAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('ecom.customers.view', $res);
        $this->app->setTitle('Customers');
        $q = (string)($_GET['q'] ?? '');
        $where = []; $args = [];
        if ($q) { $where[] = '(email LIKE :q OR first_name LIKE :q OR last_name LIKE :q)'; $args[':q'] = '%' . $q . '%'; }
        $sql = 'SELECT * FROM ecom_customers' . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY id DESC LIMIT 200';
        $customers = DB::getAll($sql, $args) ?: [];
        return $this->app->view->render('ecom/customers/index', compact('customers', 'q'));
    }

    public function view($req, $res, $params)
    {
        $this->requirePerm('ecom.customers.view', $res);
        $id = (int)($params[0] ?? 0);
        $row = DB::findOne('ecom_customers', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);
        $customer = new EcomCustomer($row);
        $orders = DB::getAll('SELECT * FROM ecom_orders WHERE customer_id = :c ORDER BY id DESC LIMIT 100',
            [':c' => $id]) ?: [];
        $subs = Subscription::listForCustomer($id);
        $this->app->setTitle('Customer: ' . $customer->email);
        return $this->app->view->render('ecom/customers/view', compact('customer', 'orders', 'subs'));
    }

    public function update($req, $res, $params)
    {
        $this->requirePerm('ecom.customers.manage', $res);
        $id = (int)($params[0] ?? 0);
        DB::execute(
            'UPDATE ecom_customers SET first_name = :f, last_name = :l, phone = :p, notes = :n,
                                       accepts_marketing = :am
              WHERE id = :id',
            [
                ':f'  => trim((string)($_POST['first_name'] ?? '')) ?: null,
                ':l'  => trim((string)($_POST['last_name']  ?? '')) ?: null,
                ':p'  => trim((string)($_POST['phone']      ?? '')) ?: null,
                ':n'  => trim((string)($_POST['notes']      ?? '')) ?: null,
                ':am' => !empty($_POST['accepts_marketing']) ? 1 : 0,
                ':id' => $id,
            ]
        );
        ActivityLog::log('ecom.customer.update', 'ecom_customers', $id);
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/ecom/customers/view/' . $id);
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
