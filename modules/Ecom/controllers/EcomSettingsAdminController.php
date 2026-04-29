<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * E-commerce settings — currency, tax, gateway credentials.
 *
 * Settings live in the existing `settings` table with keys prefixed `ecom.*`.
 * Sensitive values (Stripe secret, PayPal secret) are stored as-is — production
 * deployments should set them via env so the DB row stays empty.
 */
class EcomSettingsAdminController extends Controller
{
    private const KEYS = [
        'ecom.currency',
        'ecom.tax_rate',
        'ecom.tax_inclusive',
        'ecom.stripe_public_key',
        'ecom.stripe_secret_key',
        'ecom.stripe_webhook_secret',
        'ecom.paypal_client_id',
        'ecom.paypal_secret',
        'ecom.paypal_mode',
        'ecom.paypal_webhook_id',
        'ecom.brand_name',
    ];

    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requirePerm('ecom.settings.update', $res);
        $this->app->setTitle('Shop Settings');

        $values = [];
        foreach (self::KEYS as $k) {
            $values[$k] = (string)(DB::getCell('SELECT value FROM settings WHERE `key` = :k', [':k' => $k]) ?? '');
        }
        return $this->app->view->render('ecom/settings/index', ['values' => $values]);
    }

    public function update($req, $res, $params)
    {
        $this->requirePerm('ecom.settings.update', $res);
        foreach (self::KEYS as $k) {
            $val = (string)($_POST[str_replace('.', '_', $k)] ?? '');
            DB::execute(
                'INSERT INTO settings (`key`, `value`)
                 VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
                [':k' => $k, ':v' => $val]
            );
        }
        ActivityLog::log('ecom.settings.update', 'settings', null, 'Shop settings updated');
        Session::setFlash('Settings saved.');

        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/ecom/settings');
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
