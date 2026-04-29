<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * CartService — DB-persisted shopping cart with cookie-based token for guests.
 *
 *   $cart = CartService::current();          // returns or creates a cart
 *   CartService::addItem($cart, $productId, $variantId, $qty);
 *   CartService::setQuantity($cart, $itemId, $qty);
 *   CartService::removeItem($cart, $itemId);
 *   CartService::applyDiscount($cart, 'CODE');
 *   $totals = CartService::totals($cart);
 *
 * The cart row holds: token (cookie), customer_id (if known), currency, discount_code.
 * Items reference products+variants and store the unit price snapshot taken at add-time
 * (so a price change later doesn't surprise the buyer mid-session — checkout re-validates).
 */
class CartService
{
    public const COOKIE = 'sc_cart';
    public const TTL_DAYS = 14;

    /** Get the active cart for the current session, creating one if needed. */
    public static function current(?int $customerId = null, ?string $currency = null): array
    {
        $token = (string)($_COOKIE[self::COOKIE] ?? '');
        $row   = $token ? DB::findOne('ecom_carts', ' token = :t ', [':t' => $token]) : null;

        if (!$row) {
            $token = bin2hex(random_bytes(20));
            DB::execute(
                'INSERT INTO ecom_carts (token, customer_id, currency, expires_at)
                 VALUES (:t, :c, :cur, :e)',
                [
                    ':t'   => $token,
                    ':c'   => $customerId,
                    ':cur' => $currency ?: self::defaultCurrency(),
                    ':e'   => date('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400),
                ]
            );
            $row = DB::findOne('ecom_carts', ' token = :t ', [':t' => $token]);
            self::setCookie($token);
        }
        if ($customerId && empty($row['customer_id'])) {
            DB::execute('UPDATE ecom_carts SET customer_id = :c WHERE id = :id',
                [':c' => $customerId, ':id' => (int)$row['id']]);
            $row['customer_id'] = $customerId;
        }
        return $row;
    }

    public static function items(array $cart): array
    {
        return DB::getAll(
            'SELECT ci.*, p.name AS product_name, p.kind, p.images AS product_images
               FROM ecom_cart_items ci
               JOIN ecom_products p ON p.id = ci.product_id
              WHERE ci.cart_id = :c
              ORDER BY ci.id ASC',
            [':c' => (int)$cart['id']]
        ) ?: [];
    }

    public static function addItem(array $cart, int $productId, ?int $variantId, int $quantity = 1): void
    {
        $product = Product::findById($productId);
        if (!$product || !$product->isActive) throw new InvalidArgumentException('Product not available');

        $unitPrice = (int)$product->basePrice;
        if ($variantId) {
            $variant = ProductVariant::findById($variantId);
            if (!$variant || $variant->productId !== $product->id || !$variant->isActive) {
                throw new InvalidArgumentException('Variant not available');
            }
            $unitPrice = (int)$variant->price;
            self::checkStock($product, $variant, $quantity);
        } else {
            self::checkStock($product, null, $quantity);
        }

        $existing = DB::findOne(
            'ecom_cart_items',
            ' cart_id = :c AND product_id = :p AND (variant_id <=> :v) ',
            [':c' => (int)$cart['id'], ':p' => $productId, ':v' => $variantId]
        );
        if ($existing) {
            $newQty = (int)$existing['quantity'] + max(1, $quantity);
            DB::execute(
                'UPDATE ecom_cart_items SET quantity = :q WHERE id = :id',
                [':q' => $newQty, ':id' => (int)$existing['id']]
            );
        } else {
            DB::execute(
                'INSERT INTO ecom_cart_items (cart_id, product_id, variant_id, quantity, unit_price)
                 VALUES (:c, :p, :v, :q, :price)',
                [
                    ':c'     => (int)$cart['id'],
                    ':p'     => $productId,
                    ':v'     => $variantId,
                    ':q'     => max(1, $quantity),
                    ':price' => $unitPrice,
                ]
            );
        }
        self::touch($cart);
    }

    public static function setQuantity(array $cart, int $itemId, int $quantity): void
    {
        if ($quantity < 1) { self::removeItem($cart, $itemId); return; }
        DB::execute(
            'UPDATE ecom_cart_items SET quantity = :q WHERE id = :id AND cart_id = :c',
            [':q' => $quantity, ':id' => $itemId, ':c' => (int)$cart['id']]
        );
        self::touch($cart);
    }

    public static function removeItem(array $cart, int $itemId): void
    {
        DB::execute(
            'DELETE FROM ecom_cart_items WHERE id = :id AND cart_id = :c',
            [':id' => $itemId, ':c' => (int)$cart['id']]
        );
        self::touch($cart);
    }

    public static function clear(array $cart): void
    {
        DB::execute('DELETE FROM ecom_cart_items WHERE cart_id = :c', [':c' => (int)$cart['id']]);
        DB::execute('UPDATE ecom_carts SET discount_code = NULL WHERE id = :c', [':c' => (int)$cart['id']]);
    }

    public static function applyDiscount(array $cart, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            DB::execute('UPDATE ecom_carts SET discount_code = NULL WHERE id = :c', [':c' => (int)$cart['id']]);
            return null;
        }
        $code = strtoupper(trim($code));
        $disc = Discount::findByCode($code);
        if (!$disc) return 'Code not found';

        $totals = self::totals($cart);
        $err = $disc->validate($totals['subtotal'], $cart['customer_id'] ?? null);
        if ($err) return $err;

        DB::execute('UPDATE ecom_carts SET discount_code = :code WHERE id = :c',
            [':code' => $code, ':c' => (int)$cart['id']]);
        return null;
    }

    /**
     * Compute totals for a cart, applying the saved discount code if any.
     * Tax is approximated using the global default rate (per-region calc happens at checkout
     * once a shipping address is known).
     */
    public static function totals(array $cart, ?array $shippingAddress = null, ?string $shippingMethod = null): array
    {
        $items     = self::items($cart);
        $subtotal  = 0;
        $weight    = 0;
        $hasShippable = false;
        foreach ($items as $i) {
            $subtotal += (int)$i['unit_price'] * (int)$i['quantity'];
            if ($i['kind'] === 'physical') $hasShippable = true;
        }

        // Discount
        $discountTotal = 0;
        $freeShipping  = false;
        if (!empty($cart['discount_code'])) {
            $disc = Discount::findByCode((string)$cart['discount_code']);
            if ($disc && !$disc->validate($subtotal, $cart['customer_id'] ?? null)) {
                $r = $disc->compute($subtotal, 0);
                $discountTotal = (int)$r['discount'];
                $freeShipping  = (bool)$r['free_shipping'];
            }
        }

        // Shipping
        $shippingTotal = 0;
        if ($hasShippable && $shippingAddress) {
            $shippingTotal = ShippingCalculator::pickRate($subtotal - $discountTotal, $weight, (string)($shippingAddress['country'] ?? ''), $shippingMethod);
        }
        if ($freeShipping) $shippingTotal = 0;

        // Tax
        $taxableBase = max(0, $subtotal - $discountTotal);
        $tax = TaxCalculator::compute($taxableBase, $shippingAddress, $cart['currency'] ?? 'USD');
        $taxTotal = (int)$tax['amount'];
        if ($tax['inclusive']) $taxTotal = 0; // already in unit prices

        $total = max(0, $subtotal - $discountTotal + $shippingTotal + $taxTotal);

        return compact('items', 'subtotal', 'discountTotal', 'shippingTotal', 'taxTotal', 'total', 'freeShipping');
    }

    public static function setCookie(string $token): void
    {
        $params = [
            'expires'  => time() + self::TTL_DAYS * 86400,
            'path'     => '/',
            'samesite' => 'Lax',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']),
        ];
        setcookie(self::COOKIE, $token, $params);
        $_COOKIE[self::COOKIE] = $token;
    }

    private static function touch(array $cart): void
    {
        DB::execute(
            'UPDATE ecom_carts SET updated_at = NOW(), expires_at = :e WHERE id = :id',
            [':e' => date('Y-m-d H:i:s', time() + self::TTL_DAYS * 86400), ':id' => (int)$cart['id']]
        );
    }

    private static function checkStock(Product $product, ?ProductVariant $variant, int $qty): void
    {
        if ($product->isDigital() || $product->isServiceKind()) return; // unlimited
        if (!$product->trackInventory) return;
        $available = $variant ? (int)$variant->stock : (int)$product->stock;
        if ($qty > $available) throw new InvalidArgumentException('Out of stock');
    }

    private static function defaultCurrency(): string
    {
        $row = DB::findOne('settings', ' `key` = :k ', [':k' => 'ecom.currency']);
        return $row ? (string)$row['value'] : 'USD';
    }
}
