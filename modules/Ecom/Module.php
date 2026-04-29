<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * EcomModule — boots the e-commerce plugin.
 *
 *   - Registers default payment gateways on every request
 *   - Adds default settings rows on first install
 *   - Exposes admin + storefront + webhook routes
 */
class EcomModule implements ModuleInterface
{
    public function getName(): string { return 'Ecom'; }

    public function boot(): void
    {
        // Explicit loads in dependency order. Faster than glob() — especially on
        // network/9P-mounted filesystems (WSL /mnt/d) where each readdir + stat
        // is a syscall round-trip.
        $base = __DIR__;
        require_once $base . '/services/Money.php';
        require_once $base . '/services/CartService.php';
        require_once $base . '/services/TaxCalculator.php';
        require_once $base . '/services/ShippingCalculator.php';
        require_once $base . '/services/GatewayRegistry.php';
        require_once $base . '/services/OrderService.php';
        require_once $base . '/services/DigitalDelivery.php';
        require_once $base . '/services/EcomMail.php';
        if (is_file($base . '/services/CurrencyService.php'))    require_once $base . '/services/CurrencyService.php';
        if (is_file($base . '/services/SubscriptionBiller.php')) require_once $base . '/services/SubscriptionBiller.php';
        require_once $base . '/gateways/PaymentGateway.php';   // interface first
        require_once $base . '/gateways/ManualGateway.php';
        require_once $base . '/gateways/StripeGateway.php';
        require_once $base . '/gateways/PayPalGateway.php';

        if (!class_exists('GatewayRegistry')) return;
        GatewayRegistry::bootDefaults();

        // Optional: bind cart/order services in the container so other plugins can swap them.
        if (class_exists('Container')) {
            Container::singleton('ecom.cart',    fn() => CartService::current());
            Container::singleton('ecom.gateway.registry', fn() => GatewayRegistry::class);
        }

        // Cron registrations are throttled inside CronRunner::register() — when nothing
        // changed and the cache file is fresh, no DB writes happen here.
        if (class_exists('CronRunner') && class_exists('SubscriptionBiller')) {
            CronRunner::register('ecom.subscription.bill_due', '@hourly', [SubscriptionBiller::class, 'billDue']);

            if (class_exists('CurrencyService') && Setting::get('ecom.multi_currency_enabled', '0') === '1') {
                CronRunner::register('ecom.fx.refresh', '@daily', [CurrencyService::class, 'refreshRates']);
            }
        }
    }

    public function onInstall(): void
    {
        // Seed default settings (currency, tax rate) only when missing.
        $defaults = [
            'ecom.currency'      => 'USD',
            'ecom.tax_rate'      => '0',
            'ecom.tax_inclusive' => '0',
            'ecom.paypal_mode'   => 'sandbox',
        ];
        foreach ($defaults as $k => $v) {
            $exists = DB::findOne('settings', ' `key` = :k ', [':k' => $k]);
            if (!$exists) {
                DB::execute('INSERT INTO settings (`key`, `value`) VALUES (:k, :v)', [':k' => $k, ':v' => $v]);
            }
        }
    }

    public function onEnable(): void   { /* re-enable cron, webhooks, etc. */ }
    public function onDisable(): void  { /* hide storefront routes */ }
    public function onUninstall(): void { /* leave data in place by default */ }

    public function routes(): array
    {
        return [
            // ── Admin: dashboard
            'admin.ecom'                 => ['controller' => 'ecomDashboard', 'action' => 'index', 'prefix' => 'admin'],

            // ── Admin: products
            'admin.ecom.products'              => ['controller' => 'ecomProducts', 'action' => 'index',         'prefix' => 'admin'],
            'admin.ecom.products.create'       => ['controller' => 'ecomProducts', 'action' => 'create',        'prefix' => 'admin'],
            'admin.ecom.products.store'        => ['controller' => 'ecomProducts', 'action' => 'store',         'prefix' => 'admin'],
            'admin.ecom.products.edit'         => ['controller' => 'ecomProducts', 'action' => 'edit',          'prefix' => 'admin'],
            'admin.ecom.products.update'       => ['controller' => 'ecomProducts', 'action' => 'update',        'prefix' => 'admin'],
            'admin.ecom.products.delete'       => ['controller' => 'ecomProducts', 'action' => 'delete',        'prefix' => 'admin'],
            'admin.ecom.products.variant.store'  => ['controller' => 'ecomProducts', 'action' => 'variantStore',  'prefix' => 'admin'],
            'admin.ecom.products.variant.update' => ['controller' => 'ecomProducts', 'action' => 'variantUpdate', 'prefix' => 'admin'],
            'admin.ecom.products.variant.delete' => ['controller' => 'ecomProducts', 'action' => 'variantDelete', 'prefix' => 'admin'],

            // ── Admin: orders
            'admin.ecom.orders'              => ['controller' => 'ecomOrders', 'action' => 'index',         'prefix' => 'admin'],
            'admin.ecom.orders.view'         => ['controller' => 'ecomOrders', 'action' => 'view',          'prefix' => 'admin'],
            'admin.ecom.orders.markPaid'     => ['controller' => 'ecomOrders', 'action' => 'markPaid',      'prefix' => 'admin'],
            'admin.ecom.orders.markShipped'  => ['controller' => 'ecomOrders', 'action' => 'markShipped',   'prefix' => 'admin'],
            'admin.ecom.orders.markCancelled'=> ['controller' => 'ecomOrders', 'action' => 'markCancelled', 'prefix' => 'admin'],
            'admin.ecom.orders.refund'       => ['controller' => 'ecomOrders', 'action' => 'refund',        'prefix' => 'admin'],

            // ── Admin: customers
            'admin.ecom.customers'        => ['controller' => 'ecomCustomers', 'action' => 'index',  'prefix' => 'admin'],
            'admin.ecom.customers.view'   => ['controller' => 'ecomCustomers', 'action' => 'view',   'prefix' => 'admin'],
            'admin.ecom.customers.update' => ['controller' => 'ecomCustomers', 'action' => 'update', 'prefix' => 'admin'],

            // ── Admin: discounts
            'admin.ecom.discounts'        => ['controller' => 'ecomDiscounts', 'action' => 'index',  'prefix' => 'admin'],
            'admin.ecom.discounts.store'  => ['controller' => 'ecomDiscounts', 'action' => 'store',  'prefix' => 'admin'],
            'admin.ecom.discounts.delete' => ['controller' => 'ecomDiscounts', 'action' => 'delete', 'prefix' => 'admin'],

            // ── Admin: subscriptions
            'admin.ecom.subscriptions'        => ['controller' => 'ecomSubscriptions', 'action' => 'index',  'prefix' => 'admin'],
            'admin.ecom.subscriptions.cancel' => ['controller' => 'ecomSubscriptions', 'action' => 'cancel', 'prefix' => 'admin'],

            // ── Admin: settings
            'admin.ecom.settings'        => ['controller' => 'ecomSettings', 'action' => 'index',  'prefix' => 'admin'],
            'admin.ecom.settings.update' => ['controller' => 'ecomSettings', 'action' => 'update', 'prefix' => 'admin'],

            // ── Admin: currencies (multi-currency)
            'admin.ecom.currencies'         => ['controller' => 'ecomCurrency', 'action' => 'index',    'prefix' => 'admin'],
            'admin.ecom.currencies.update'  => ['controller' => 'ecomCurrency', 'action' => 'update',   'prefix' => 'admin'],
            'admin.ecom.currencies.toggle'  => ['controller' => 'ecomCurrency', 'action' => 'toggle',   'prefix' => 'admin'],
            'admin.ecom.currencies.base'    => ['controller' => 'ecomCurrency', 'action' => 'setBase',  'prefix' => 'admin'],
            'admin.ecom.currencies.refresh' => ['controller' => 'ecomCurrency', 'action' => 'refresh',  'prefix' => 'admin'],

            // ── Storefront digital download
            'shop.download' => ['controller' => 'ecomDownload', 'action' => 'serve'],

            // ── Storefront REST API
            'api.v1.shop.products'             => ['controller' => 'shopV1', 'action' => 'products',           'prefix' => 'api/v1'],
            'api.v1.shop.currencies'           => ['controller' => 'shopV1', 'action' => 'currencies',         'prefix' => 'api/v1'],
            'api.v1.shop.product'              => ['controller' => 'shopV1', 'action' => 'product',            'prefix' => 'api/v1'],
            'api.v1.shop.categories'           => ['controller' => 'shopV1', 'action' => 'categories',         'prefix' => 'api/v1'],
            'api.v1.shop.cart'                 => ['controller' => 'shopV1', 'action' => 'cart',               'prefix' => 'api/v1'],
            'api.v1.shop.cart.add'             => ['controller' => 'shopV1', 'action' => 'cartAdd',            'prefix' => 'api/v1'],
            'api.v1.shop.cart.update'          => ['controller' => 'shopV1', 'action' => 'cartUpdate',         'prefix' => 'api/v1'],
            'api.v1.shop.cart.remove'          => ['controller' => 'shopV1', 'action' => 'cartRemove',         'prefix' => 'api/v1'],
            'api.v1.shop.cart.discount'        => ['controller' => 'shopV1', 'action' => 'cartDiscount',       'prefix' => 'api/v1'],
            'api.v1.shop.checkout'             => ['controller' => 'shopV1', 'action' => 'checkout',           'prefix' => 'api/v1'],
            'api.v1.shop.order'                => ['controller' => 'shopV1', 'action' => 'order',              'prefix' => 'api/v1'],
            'api.v1.shop.subscriptions'        => ['controller' => 'shopV1', 'action' => 'subscriptions',      'prefix' => 'api/v1'],
            'api.v1.shop.subscription.cancel'  => ['controller' => 'shopV1', 'action' => 'cancelSubscription', 'prefix' => 'api/v1'],

            // ── Webhooks (one endpoint, gateway in URL)
            'api.v1.shop.webhook' => ['controller' => 'ecomWebhook', 'action' => 'handle', 'prefix' => 'api/v1'],
        ];
    }
}
