<?php

defined('_SEVEN') or die('No direct script access allowed');

class EcomDiscountsAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('ecom.discounts.manage', $res);
        $this->app->setTitle('Discounts');
        $rows = DB::getAll('SELECT * FROM ecom_discounts ORDER BY id DESC') ?: [];
        return $this->app->view->render('ecom/discounts/index', ['discounts' => $rows]);
    }

    public function store($req, $res, $params)
    {
        $this->requirePerm('ecom.discounts.manage', $res);
        $currency = (string)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.currency"') ?? 'USD');

        $kind = (string)($_POST['kind'] ?? 'percent');
        $value = match ($kind) {
            'percent'       => (int)round(((float)($_POST['value'] ?? 0)) * 100),
            'fixed'         => Money::fromInput((string)($_POST['value'] ?? '0'), $currency),
            'free_shipping' => 0,
            default         => 0,
        };

        DB::execute(
            'INSERT INTO ecom_discounts
                (code, kind, value, min_subtotal, applies_to, applies_id,
                 usage_limit, per_customer_limit, starts_at, ends_at, is_active)
             VALUES
                (:code, :kind, :value, :min, :at, :aid,
                 :lim, :pcl, :sa, :ea, :ia)',
            [
                ':code'  => strtoupper(trim((string)($_POST['code'] ?? ''))),
                ':kind'  => $kind,
                ':value' => $value,
                ':min'   => $_POST['min_subtotal'] !== '' ? Money::fromInput((string)$_POST['min_subtotal'], $currency) : null,
                ':at'    => (string)($_POST['applies_to'] ?? 'all'),
                ':aid'   => $_POST['applies_id'] !== '' ? (int)$_POST['applies_id'] : null,
                ':lim'   => $_POST['usage_limit'] !== '' ? (int)$_POST['usage_limit'] : null,
                ':pcl'   => $_POST['per_customer_limit'] !== '' ? (int)$_POST['per_customer_limit'] : null,
                ':sa'    => (string)($_POST['starts_at'] ?? '') ?: null,
                ':ea'    => (string)($_POST['ends_at']   ?? '') ?: null,
                ':ia'    => isset($_POST['is_active']) ? 1 : 0,
            ]
        );
        ActivityLog::log('ecom.discount.create', 'ecom_discounts', (int)DB::lastInsertId());
        $this->back();
    }

    public function delete($req, $res, $params)
    {
        $this->requirePerm('ecom.discounts.manage', $res);
        $id = (int)($params[0] ?? 0);
        DB::execute('DELETE FROM ecom_discounts WHERE id = :id', [':id' => $id]);
        ActivityLog::log('ecom.discount.delete', 'ecom_discounts', $id);
        $this->back();
    }

    private function requirePerm(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }

    private function back(): void
    {
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/ecom/discounts');
        exit;
    }
}
