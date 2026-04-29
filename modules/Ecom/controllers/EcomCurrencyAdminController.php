<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class EcomCurrencyAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Currencies');

        $currencies = DB::getAll('SELECT * FROM ecom_currencies ORDER BY is_base DESC, code ASC') ?: [];
        $base       = CurrencyService::base();
        $enabled    = CurrencyService::isEnabled();
        $provider   = (string)Setting::get('ecom.fx_provider', 'manual');

        // Latest FX rates for display.
        $rates = DB::getAll(
            'SELECT t.* FROM ecom_fx_rates t
             INNER JOIN (
                 SELECT base_code, quote_code, MAX(fetched_at) AS mt
                   FROM ecom_fx_rates
                  GROUP BY base_code, quote_code
             ) m ON m.base_code = t.base_code AND m.quote_code = t.quote_code AND m.mt = t.fetched_at
             ORDER BY t.base_code, t.quote_code'
        ) ?: [];

        return $this->app->view->render('index', compact('currencies', 'base', 'enabled', 'provider', 'rates'));
    }

    public function toggle($req, $res, $params)
    {
        $this->requireAdmin($res);
        $code = strtoupper((string)($params['code'] ?? ''));
        if ($code !== '') {
            DB::execute(
                'UPDATE ecom_currencies SET is_enabled = 1 - is_enabled WHERE code = :c',
                [':c' => $code]
            );
        }
        $res->redirect('/admin/ecom/currencies');
    }

    public function setBase($req, $res, $params)
    {
        $this->requireAdmin($res);
        $code = strtoupper((string)($params['code'] ?? ''));
        if ($code !== '') {
            DB::execute('UPDATE ecom_currencies SET is_base = 0');
            DB::execute('UPDATE ecom_currencies SET is_base = 1, is_enabled = 1 WHERE code = :c', [':c' => $code]);
            DB::execute(
                'UPDATE settings SET `value` = :v WHERE `key` = "ecom.currency"',
                [':v' => $code]
            );
        }
        $res->redirect('/admin/ecom/currencies');
    }

    public function update($req, $res, $params)
    {
        $this->requireAdmin($res);

        DB::execute(
            'UPDATE settings SET `value` = :v WHERE `key` = "ecom.multi_currency_enabled"',
            [':v' => isset($_POST['multi_currency_enabled']) ? '1' : '0']
        );
        DB::execute(
            'UPDATE settings SET `value` = :v WHERE `key` = "ecom.fx_provider"',
            [':v' => (string)($_POST['fx_provider'] ?? 'manual')]
        );

        // Manual rate updates.
        if (isset($_POST['rates']) && is_array($_POST['rates'])) {
            foreach ($_POST['rates'] as $pair => $rate) {
                if (!is_string($pair) || !preg_match('/^[A-Z]{3}_[A-Z]{3}$/', $pair)) continue;
                if (!is_numeric($rate) || (float)$rate <= 0) continue;
                [$from, $to] = explode('_', $pair);
                CurrencyService::setRate($from, $to, (float)$rate);
            }
        }

        Session::setFlash('Currency settings updated.');
        $res->redirect('/admin/ecom/currencies');
    }

    public function refresh($req, $res, $params)
    {
        $this->requireAdmin($res);
        try {
            CurrencyService::refreshRates();
            Session::setFlash('FX rates refreshed.');
        } catch (\Throwable $e) {
            Session::setFlash('Refresh failed: ' . $e->getMessage());
        }
        $res->redirect('/admin/ecom/currencies');
    }
}
