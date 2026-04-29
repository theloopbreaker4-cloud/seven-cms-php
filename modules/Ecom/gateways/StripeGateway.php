<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * StripeGateway — Stripe payments via the REST API (no SDK dependency).
 *
 * One-off charges:
 *   1. createPaymentIntent → returns client_secret
 *   2. front-end uses stripe.js → confirms with the secret
 *   3. Stripe sends `payment_intent.succeeded` to our webhook → markPaid
 *
 * Subscriptions:
 *   1. createSubscription creates a Stripe Customer + Subscription with our Price
 *   2. returns latest_invoice.payment_intent.client_secret for the first charge
 *   3. webhook `invoice.paid` extends the period each month
 *
 * Configuration:
 *   STRIPE_SECRET_KEY           — env or settings (`ecom.stripe_secret_key`)
 *   STRIPE_WEBHOOK_SECRET       — env or settings (`ecom.stripe_webhook_secret`)
 */
class StripeGateway implements PaymentGateway
{
    private string $secret;
    private string $webhookSecret;

    public function __construct(?string $secret = null, ?string $webhookSecret = null)
    {
        $this->secret        = $secret        ?? self::setting('ecom.stripe_secret_key',     'STRIPE_SECRET_KEY');
        $this->webhookSecret = $webhookSecret ?? self::setting('ecom.stripe_webhook_secret', 'STRIPE_WEBHOOK_SECRET');
    }

    public function id(): string                  { return 'stripe'; }
    public function supportsSubscriptions(): bool { return true; }

    public function createPaymentIntent(Order $order, EcomCustomer $customer, array $options = []): array
    {
        $stripeCustomerId = $this->ensureStripeCustomer($customer);

        $resp = $this->request('POST', '/v1/payment_intents', [
            'amount'                  => $order->total,
            'currency'                => strtolower($order->currency),
            'customer'                => $stripeCustomerId,
            'metadata[order_id]'      => $order->id,
            'metadata[order_number]'  => $order->number,
            'automatic_payment_methods[enabled]' => 'true',
            'description'             => 'Order ' . $order->number,
            'receipt_email'           => $customer->email,
        ]);

        if (empty($resp['id']) || empty($resp['client_secret'])) {
            throw new RuntimeException('Stripe payment intent failed: ' . json_encode($resp));
        }

        return [
            'gateway_id'    => $resp['id'],
            'client_secret' => $resp['client_secret'],
            'payload'       => ['publishable_key' => self::setting('ecom.stripe_public_key', 'STRIPE_PUBLIC_KEY')],
        ];
    }

    public function createSubscription(Order $order, EcomCustomer $customer, Product $product, ?ProductVariant $variant, array $options = []): array
    {
        if (!$product->isRecurring()) {
            throw new InvalidArgumentException('Product is not a subscription');
        }

        $stripeCustomerId = $this->ensureStripeCustomer($customer);
        $priceId = $this->ensureStripePrice($product, $variant, $order->currency);

        $params = [
            'customer'                 => $stripeCustomerId,
            'items[0][price]'          => $priceId,
            'payment_behavior'         => 'default_incomplete',
            'expand[]'                 => 'latest_invoice.payment_intent',
            'metadata[order_id]'       => $order->id,
            'metadata[order_number]'   => $order->number,
            'metadata[product_id]'     => $product->id,
        ];
        if (!empty($product->trialDays)) {
            $params['trial_period_days'] = $product->trialDays;
        }

        $resp = $this->request('POST', '/v1/subscriptions', $params);
        if (empty($resp['id'])) {
            throw new RuntimeException('Stripe subscription failed: ' . json_encode($resp));
        }

        $clientSecret = $resp['latest_invoice']['payment_intent']['client_secret'] ?? null;
        return [
            'subscription_id' => $resp['id'],
            'client_secret'   => $clientSecret,
            'payload'         => ['publishable_key' => self::setting('ecom.stripe_public_key', 'STRIPE_PUBLIC_KEY')],
        ];
    }

    public function refund(string $gatewayPaymentId, int $amount, string $currency, ?string $reason = null): ?string
    {
        $resp = $this->request('POST', '/v1/refunds', [
            'payment_intent' => $gatewayPaymentId,
            'amount'         => $amount,
            'reason'         => $reason ? 'requested_by_customer' : 'requested_by_customer',
        ]);
        return $resp['id'] ?? null;
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        if ($this->webhookSecret === '') return false;
        $sig = $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? '';
        if (!$sig) return false;

        // Stripe-Signature: t=...,v1=...
        $parts = [];
        foreach (explode(',', $sig) as $kv) {
            [$k, $v] = array_map('trim', explode('=', $kv, 2) + [1 => '']);
            $parts[$k][] = $v;
        }
        $timestamp = (int)($parts['t'][0] ?? 0);
        if (!$timestamp || abs(time() - $timestamp) > 300) return false;

        $signed   = $timestamp . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signed, $this->webhookSecret);
        foreach (($parts['v1'] ?? []) as $candidate) {
            if (hash_equals($expected, $candidate)) return true;
        }
        return false;
    }

    public function handleWebhook(string $rawBody, array $headers): array
    {
        $event = json_decode($rawBody, true) ?: [];
        $type  = (string)($event['type'] ?? '');
        $obj   = $event['data']['object'] ?? [];

        $orderId = (int)($obj['metadata']['order_id'] ?? 0);
        $subId   = (string)($obj['subscription'] ?? $obj['id'] ?? '');

        return match ($type) {
            'payment_intent.succeeded' => [
                'type' => 'payment.succeeded',
                'order_id'        => $orderId,
                'subscription_id' => null,
                'amount'          => (int)($obj['amount_received'] ?? 0),
                'gateway_id'      => (string)($obj['id'] ?? ''),
                'raw'             => $event,
            ],
            'payment_intent.payment_failed' => [
                'type' => 'payment.failed',
                'order_id'        => $orderId,
                'subscription_id' => null,
                'amount'          => (int)($obj['amount'] ?? 0),
                'gateway_id'      => (string)($obj['id'] ?? ''),
                'raw'             => $event,
            ],
            'invoice.paid' => [
                'type' => 'subscription.renewed',
                'order_id'        => $orderId ?: null,
                'subscription_id' => (string)($obj['subscription'] ?? ''),
                'amount'          => (int)($obj['amount_paid'] ?? 0),
                'gateway_id'      => (string)($obj['id'] ?? ''),
                'raw'             => $event,
            ],
            'customer.subscription.deleted' => [
                'type' => 'subscription.cancelled',
                'order_id'        => null,
                'subscription_id' => (string)($obj['id'] ?? ''),
                'amount'          => null,
                'gateway_id'      => (string)($obj['id'] ?? ''),
                'raw'             => $event,
            ],
            'charge.refunded' => [
                'type' => 'refund.created',
                'order_id'        => (int)($obj['metadata']['order_id'] ?? 0),
                'subscription_id' => null,
                'amount'          => (int)($obj['amount_refunded'] ?? 0),
                'gateway_id'      => (string)($obj['payment_intent'] ?? ''),
                'raw'             => $event,
            ],
            default => [
                'type' => 'unknown',
                'order_id' => null, 'subscription_id' => null,
                'amount' => null, 'gateway_id' => (string)($event['id'] ?? ''),
                'raw' => $event,
            ],
        };
    }

    // ──────────────────────────────────────────────────────────────────
    // Internals
    // ──────────────────────────────────────────────────────────────────

    private function ensureStripeCustomer(EcomCustomer $customer): string
    {
        if (!empty($customer->stripeCustomerId)) return $customer->stripeCustomerId;

        $resp = $this->request('POST', '/v1/customers', [
            'email' => $customer->email,
            'name'  => trim(($customer->firstName ?? '') . ' ' . ($customer->lastName ?? '')) ?: null,
            'metadata[customer_id]' => $customer->id,
        ]);
        $id = (string)($resp['id'] ?? '');
        if ($id && $customer->id) {
            DB::execute('UPDATE ecom_customers SET stripe_customer_id = :s WHERE id = :id',
                [':s' => $id, ':id' => $customer->id]);
            $customer->stripeCustomerId = $id;
        }
        return $id;
    }

    /**
     * Look up or create a Stripe Price for a recurring product.
     * Stored as Stripe metadata so we don't keep our own table.
     */
    private function ensureStripePrice(Product $product, ?ProductVariant $variant, string $currency): string
    {
        $unitAmount = $variant ? (int)$variant->price : (int)$product->basePrice;
        $period     = $product->billingPeriod ?? 'month';
        $interval   = (int)($product->billingInterval ?? 1);
        $lookupKey  = "sc_p{$product->id}" . ($variant ? "_v{$variant->id}" : '') . "_{$currency}_{$period}{$interval}";

        // Try lookup first.
        $existing = $this->request('GET', '/v1/prices?lookup_keys[]=' . urlencode($lookupKey) . '&active=true&limit=1');
        if (!empty($existing['data'][0]['id'])) return $existing['data'][0]['id'];

        // Create product (Stripe object) once per Lookup key family.
        $stripeProduct = $this->request('POST', '/v1/products', [
            'name' => $product->pickI18n('name', 'en') ?: ('Product #' . $product->id),
        ]);
        $productId = (string)($stripeProduct['id'] ?? '');
        if (!$productId) throw new RuntimeException('Stripe product creation failed');

        $price = $this->request('POST', '/v1/prices', [
            'unit_amount'           => $unitAmount,
            'currency'              => strtolower($currency),
            'product'               => $productId,
            'recurring[interval]'   => $period,
            'recurring[interval_count]' => $interval,
            'lookup_key'            => $lookupKey,
        ]);
        $priceId = (string)($price['id'] ?? '');
        if (!$priceId) throw new RuntimeException('Stripe price creation failed');
        return $priceId;
    }

    private function request(string $method, string $path, array $form = []): array
    {
        if ($this->secret === '') throw new RuntimeException('Stripe secret key is not configured');

        $url = 'https://api.stripe.com' . $path;
        $ch  = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->secret,
                'Content-Type: application/x-www-form-urlencoded',
                'Stripe-Version: 2024-06-20',
            ],
        ]);
        if ($method === 'GET') {
            // path may already include query string
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form, '', '&', PHP_QUERY_RFC3986));
        }
        $body = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === '' && $err) throw new RuntimeException('Stripe transport error: ' . $err);
        $resp = json_decode($body, true) ?: [];
        if ($code >= 400) {
            $msg = $resp['error']['message'] ?? 'Stripe API error';
            throw new RuntimeException("Stripe {$code}: {$msg}");
        }
        return $resp;
    }

    private static function setting(string $key, string $envFallback): string
    {
        $row = DB::findOne('settings', ' `key` = :k ', [':k' => $key]);
        if ($row && (string)$row['value'] !== '') return (string)$row['value'];
        return (string)Env::get($envFallback, '');
    }
}
