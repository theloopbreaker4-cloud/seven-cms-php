<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ShopV1ApiController — public storefront REST API.
 *
 *   GET  /api/v1/shop/products
 *   GET  /api/v1/shop/products/:slug
 *   GET  /api/v1/shop/categories
 *
 *   GET  /api/v1/shop/cart
 *   POST /api/v1/shop/cart/items                    body: product_id, variant_id?, quantity
 *   PUT  /api/v1/shop/cart/items/:itemId            body: quantity
 *   DELETE /api/v1/shop/cart/items/:itemId
 *   POST /api/v1/shop/cart/discount                 body: code
 *
 *   POST /api/v1/shop/checkout                      body: customer, billing_address, shipping_address, gateway, return_url, cancel_url
 *
 *   GET  /api/v1/shop/orders/:number                — only the buyer (matched by email or auth)
 *   GET  /api/v1/shop/subscriptions                 — auth only
 *   POST /api/v1/shop/subscriptions/:id/cancel      — auth only
 */
class ShopV1ApiController extends ApiV1Controller
{
    protected int $rateLimitPerMinute = 240;

    // ─── Catalog ─────────────────────────────────────────────────────

    public function products($req, $res, $params)
    {
        $items = Product::listPublic([
            'category_id' => isset($_GET['category']) ? (int)$_GET['category'] : null,
            'kind'        => (string)($_GET['kind'] ?? ''),
            'q'           => (string)($_GET['q']    ?? ''),
            'limit'       => (int)($_GET['limit']  ?? 50),
            'offset'      => (int)($_GET['offset'] ?? 0),
        ]);
        $locale = (string)($_GET['locale'] ?? 'en');
        $total  = (int)(DB::getCell('SELECT COUNT(*) FROM ecom_products WHERE is_active = 1') ?? 0);
        $out    = array_map(fn($r) => (new Product($r))->toArray($locale), $items);
        return print $this->paginate($out, $total, ['limit' => $_GET['limit'] ?? 50, 'offset' => $_GET['offset'] ?? 0]);
    }

    public function product($req, $res, $params)
    {
        $slug = (string)($params[0] ?? '');
        $product = Product::findBySlug($slug);
        if (!$product || !$product->isActive) $this->jsonError(404, 'Not found');
        $locale = (string)($_GET['locale'] ?? 'en');

        return print $this->json(array_merge(
            $product->toArray($locale),
            ['variants' => array_map(fn($v) => (new ProductVariant($v))->toArray(), $product->variants())]
        ));
    }

    public function categories($req, $res, $params)
    {
        $rows = DB::getAll('SELECT * FROM ecom_categories WHERE is_active = 1 ORDER BY sort_order, name') ?: [];
        return print $this->json(['items' => $rows]);
    }

    /** GET /api/v1/shop/currencies — list enabled currencies + the request's active one. */
    public function currencies($req, $res, $params)
    {
        if (!class_exists('CurrencyService')) {
            return print $this->json([
                'enabled' => false,
                'base'    => (string)Setting::get('ecom.currency', 'USD'),
                'active'  => (string)Setting::get('ecom.currency', 'USD'),
                'items'   => [],
            ]);
        }
        return print $this->json([
            'enabled' => CurrencyService::isEnabled(),
            'base'    => CurrencyService::base(),
            'active'  => CurrencyService::active(),
            'items'   => CurrencyService::enabled(),
        ]);
    }

    // ─── Cart ────────────────────────────────────────────────────────

    public function cart($req, $res, $params)
    {
        $cart   = CartService::current();
        $totals = CartService::totals($cart);
        return print $this->json([
            'token'    => $cart['token'],
            'currency' => $cart['currency'],
            'items'    => $totals['items'],
            'totals'   => [
                'subtotal'      => $totals['subtotal'],
                'discount'      => $totals['discountTotal'],
                'shipping'      => $totals['shippingTotal'],
                'tax'           => $totals['taxTotal'],
                'total'         => $totals['total'],
                'free_shipping' => $totals['freeShipping'],
            ],
            'discount_code' => $cart['discount_code'],
        ]);
    }

    public function cartAdd($req, $res, $params)
    {
        $body = $this->jsonBody();
        try {
            CartService::addItem(
                CartService::current(),
                (int)($body['product_id'] ?? 0),
                isset($body['variant_id']) ? (int)$body['variant_id'] : null,
                (int)($body['quantity'] ?? 1)
            );
        } catch (\InvalidArgumentException $e) {
            $this->jsonError(400, $e->getMessage());
        }
        return $this->cart($req, $res, $params);
    }

    public function cartUpdate($req, $res, $params)
    {
        $body = $this->jsonBody();
        CartService::setQuantity(CartService::current(), (int)($params[0] ?? 0), (int)($body['quantity'] ?? 1));
        return $this->cart($req, $res, $params);
    }

    public function cartRemove($req, $res, $params)
    {
        CartService::removeItem(CartService::current(), (int)($params[0] ?? 0));
        return $this->cart($req, $res, $params);
    }

    public function cartDiscount($req, $res, $params)
    {
        $body = $this->jsonBody();
        $err = CartService::applyDiscount(CartService::current(), $body['code'] ?? null);
        if ($err) $this->jsonError(422, $err);
        return $this->cart($req, $res, $params);
    }

    // ─── Checkout ────────────────────────────────────────────────────

    public function checkout($req, $res, $params)
    {
        $body = $this->jsonBody();
        $cart = CartService::current();

        try {
            $result = OrderService::createFromCart($cart, $body);
        } catch (\InvalidArgumentException $e) {
            $this->jsonError(400, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::channel('app')->error('Checkout failed', ['error' => $e->getMessage()]);
            $this->jsonError(500, 'Checkout failed');
        }

        return print $this->json([
            'order'   => $result['order']->toArray(),
            'payment' => $result['payment'],
            'subscription' => $result['subscription']?->toArray(),
        ], 201);
    }

    // ─── Orders / subs (lookup for buyer) ────────────────────────────

    public function order($req, $res, $params)
    {
        $number = (string)($params[0] ?? '');
        $order  = Order::findByNumber($number);
        if (!$order) $this->jsonError(404, 'Not found');

        // Either token-auth as the customer, or matching email via query.
        $u = $this->authUser();
        $email = strtolower(trim((string)($_GET['email'] ?? ($u['email'] ?? ''))));
        if (strtolower($order->email) !== $email) $this->jsonError(403, 'Forbidden');

        return print $this->json(array_merge($order->toArray(), ['items' => $order->items()]));
    }

    public function subscriptions($req, $res, $params)
    {
        $u = $this->requireAuth();
        $cust = EcomCustomer::findByUserId((int)$u['id']);
        if (!$cust) return print $this->json(['items' => []]);
        return print $this->json(['items' => Subscription::listForCustomer((int)$cust->id)]);
    }

    public function cancelSubscription($req, $res, $params)
    {
        $u = $this->requireAuth();
        $sub = Subscription::findById((int)($params[0] ?? 0));
        if (!$sub) $this->jsonError(404, 'Not found');

        $cust = EcomCustomer::findByUserId((int)$u['id']);
        if (!$cust || $sub->customerId !== (int)$cust->id) $this->jsonError(403, 'Forbidden');

        $sub->cancelAtPeriodEnd();
        return print $this->json(['ok' => true]);
    }

    // ─── helpers ─────────────────────────────────────────────────────

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $arr = $raw ? json_decode($raw, true) : null;
        return is_array($arr) ? $arr : $_POST;
    }
}
