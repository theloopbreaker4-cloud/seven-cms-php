<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * OrderService — converts a cart into an order, decrements stock, fires hooks.
 *
 *   $result = OrderService::createFromCart($cart, [
 *       'customer'         => ['email' => 'a@b.com', 'firstName' => 'A', 'lastName' => 'B'],
 *       'billing_address'  => [...],
 *       'shipping_address' => [...],
 *       'shipping_method'  => 'Standard',
 *       'note'             => '',
 *       'gateway'          => 'stripe',          // payment driver id
 *       'return_url'       => '/checkout/done',
 *       'cancel_url'       => '/cart',
 *   ]);
 *   // returns ['order' => Order, 'payment' => array (gateway response), 'subscription' => Subscription|null]
 */
class OrderService
{
    /**
     * @return array{ order: Order, payment: array, subscription: ?Subscription }
     */
    public static function createFromCart(array $cart, array $payload): array
    {
        $items = CartService::items($cart);
        if (!$items) throw new InvalidArgumentException('Cart is empty');

        // Resolve customer
        $customerInput = (array)($payload['customer'] ?? []);
        $email         = strtolower(trim((string)($customerInput['email'] ?? '')));
        if ($email === '') throw new InvalidArgumentException('Customer email is required');

        $customer = EcomCustomer::findOrCreate($email, [
            'firstName'        => $customerInput['firstName'] ?? null,
            'lastName'         => $customerInput['lastName']  ?? null,
            'phone'            => $customerInput['phone']     ?? null,
            'userId'           => $cart['customer_id'] ?? null,
            'acceptsMarketing' => !empty($customerInput['acceptsMarketing']),
        ]);

        // Compute totals using the chosen shipping address
        $totals = CartService::totals(
            $cart,
            $payload['shipping_address'] ?? null,
            $payload['shipping_method']  ?? null
        );

        $currency = (string)($cart['currency'] ?? 'USD');

        // Detect mixed kinds — decides flow (one-off vs subscription).
        $hasSubscription = false;
        $hasPhysical     = false;
        $subscriptionItem = null;
        foreach ($items as $i) {
            $product = Product::findById((int)$i['product_id']);
            if (!$product) continue;
            if ($product->isPhysical()) $hasPhysical = true;
            if ($product->isRecurring()) {
                $hasSubscription = true;
                $subscriptionItem = ['product' => $product, 'cart_item' => $i,
                                     'variant' => !empty($i['variant_id']) ? ProductVariant::findById((int)$i['variant_id']) : null];
            }
        }

        if ($hasSubscription && count($items) > 1) {
            throw new InvalidArgumentException('Subscription products must be checked out alone.');
        }

        // ── Persist order
        $order = new Order();
        $order->number          = Order::generateNumber();
        $order->customerId      = (int)$customer->id;
        $order->email           = $customer->email;
        $order->currency        = $currency;
        $order->status          = 'pending';
        $order->paymentStatus   = 'unpaid';
        $order->fulfillmentStatus = $hasPhysical ? 'unfulfilled' : 'fulfilled';
        $order->subtotal        = (int)$totals['subtotal'];
        $order->discountTotal   = (int)$totals['discountTotal'];
        $order->taxTotal        = (int)$totals['taxTotal'];
        $order->shippingTotal   = (int)$totals['shippingTotal'];
        $order->total           = (int)$totals['total'];
        $order->discountCode    = $cart['discount_code'] ?? null;
        $order->billingAddress  = json_encode($payload['billing_address']  ?? [], JSON_UNESCAPED_UNICODE);
        $order->shippingAddress = json_encode($payload['shipping_address'] ?? [], JSON_UNESCAPED_UNICODE);
        $order->shippingMethod  = $payload['shipping_method'] ?? null;
        $order->note            = $payload['note'] ?? null;
        $order->placedAt        = date('Y-m-d H:i:s');
        $order->createdAt       = date('Y-m-d H:i:s');

        Hooks::fire(Hooks::BEFORE_CREATE, 'order', $order);
        $orderId = $order->save();
        if (!$orderId) throw new RuntimeException('Failed to save order');

        // ── Snapshot items (denormalized for historical accuracy)
        foreach ($items as $i) {
            $product = Product::findById((int)$i['product_id']);
            if (!$product) continue;
            $variant = !empty($i['variant_id']) ? ProductVariant::findById((int)$i['variant_id']) : null;
            $unitPrice = (int)$i['unit_price'];
            $qty       = max(1, (int)$i['quantity']);

            DB::execute(
                'INSERT INTO ecom_order_items
                    (order_id, product_id, variant_id, kind, name, sku, quantity, unit_price, total)
                 VALUES
                    (:o, :p, :v, :k, :n, :sku, :q, :up, :t)',
                [
                    ':o'   => $orderId,
                    ':p'   => $product->id,
                    ':v'   => $variant?->id,
                    ':k'   => $product->kind,
                    ':n'   => $product->pickI18n('name', 'en') ?: ('Product #' . $product->id),
                    ':sku' => $variant?->sku ?? $product->sku,
                    ':q'   => $qty,
                    ':up'  => $unitPrice,
                    ':t'   => $unitPrice * $qty,
                ]
            );

            // Decrement stock for physical (atomically; ignore for digital/service).
            if ($product->isPhysical() && $product->trackInventory) {
                if ($variant) {
                    DB::execute(
                        'UPDATE ecom_product_variants
                            SET stock = GREATEST(stock - :q, 0)
                          WHERE id = :id',
                        [':q' => $qty, ':id' => $variant->id]
                    );
                } else {
                    DB::execute(
                        'UPDATE ecom_products
                            SET stock = GREATEST(stock - :q, 0)
                          WHERE id = :id',
                        [':q' => $qty, ':id' => $product->id]
                    );
                }
            }
        }

        // ── Bookkeeping
        if ($order->discountCode) {
            $disc = Discount::findByCode($order->discountCode);
            if ($disc) $disc->recordUsage();
        }
        $customer->recordOrder($order->total);

        Hooks::fire(Hooks::AFTER_CREATE, 'order', $order);
        Event::dispatch('ecom.order.created', $order);
        if (class_exists('ActivityLog')) {
            ActivityLog::log('ecom.order.create', 'ecom_orders', (int)$order->id,
                "Order {$order->number} placed for {$customer->email}");
        }

        // ── Initiate payment
        $gatewayId = (string)($payload['gateway'] ?? 'manual');
        if (!GatewayRegistry::has($gatewayId)) $gatewayId = 'manual';
        $gateway = GatewayRegistry::get($gatewayId);

        $subscription = null;
        if ($hasSubscription && $subscriptionItem) {
            $payResp = $gateway->createSubscription(
                $order, $customer, $subscriptionItem['product'], $subscriptionItem['variant'],
                ['return_url' => $payload['return_url'] ?? '/', 'cancel_url' => $payload['cancel_url'] ?? '/']
            );

            // Persist local subscription record
            $sub = new Subscription();
            $sub->customerId            = $customer->id;
            $sub->productId             = $subscriptionItem['product']->id;
            $sub->variantId             = $subscriptionItem['variant']?->id;
            $sub->gateway               = $gatewayId;
            $sub->gatewaySubscriptionId = $payResp['subscription_id'] ?? null;
            $sub->status                = $subscriptionItem['product']->trialDays ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE;
            $sub->currency              = $currency;
            $sub->unitPrice             = (int)$subscriptionItem['cart_item']['unit_price'];
            $sub->quantity              = max(1, (int)$subscriptionItem['cart_item']['quantity']);
            $sub->billingPeriod         = (string)($subscriptionItem['product']->billingPeriod ?? 'month');
            $sub->billingInterval       = (int)($subscriptionItem['product']->billingInterval ?? 1);
            $sub->currentPeriodStart    = date('Y-m-d H:i:s');
            $sub->currentPeriodEnd      = Subscription::advancePeriod(
                date('Y-m-d H:i:s'), $sub->billingPeriod, $sub->billingInterval
            );
            if ($subscriptionItem['product']->trialDays) {
                $sub->trialEndsAt = (new DateTimeImmutable('+' . (int)$subscriptionItem['product']->trialDays . ' days'))->format('Y-m-d H:i:s');
            }
            $sub->createdAt = date('Y-m-d H:i:s');
            $sub->save();
            $subscription = $sub;
        } else {
            $payResp = $gateway->createPaymentIntent($order, $customer, [
                'return_url' => $payload['return_url'] ?? '/',
                'cancel_url' => $payload['cancel_url'] ?? '/',
            ]);
        }

        // Record payment row
        DB::execute(
            'INSERT INTO ecom_payments (order_id, gateway, gateway_id, status, amount, currency, payload)
             VALUES (:o, :g, :gid, :s, :a, :c, :p)',
            [
                ':o'   => $orderId,
                ':g'   => $gatewayId,
                ':gid' => $payResp['gateway_id'] ?? ($payResp['subscription_id'] ?? null),
                ':s'   => $gatewayId === 'manual' ? 'pending' : 'pending',
                ':a'   => $order->total,
                ':c'   => $currency,
                ':p'   => json_encode($payResp['payload'] ?? [], JSON_UNESCAPED_UNICODE),
            ]
        );

        // Empty cart now that we have an order.
        CartService::clear($cart);

        return ['order' => $order, 'payment' => $payResp, 'subscription' => $subscription];
    }

    /**
     * Mark order paid, fulfill digital deliverables, fire hooks.
     * Called from webhook handlers and the manual "Mark paid" admin action.
     */
    public static function fulfillPaidOrder(Order $order): void
    {
        if ($order->isPaid()) return;
        $order->markPaid();

        // Issue digital downloads
        $items = $order->items();
        foreach ($items as $i) {
            if (($i['kind'] ?? '') !== 'digital') continue;
            $product = Product::findById((int)$i['product_id']);
            if (!$product) continue;
            DigitalDelivery::grant($order, $product, !empty($i['variant_id']) ? (int)$i['variant_id'] : null);
        }

        // Email
        if (class_exists('EcomMail')) EcomMail::orderPaid($order);
    }
}
