<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * PayPalGateway — PayPal Orders v2 API + Subscriptions API.
 *
 * Implementation status: payment intent and webhook verification are wired up;
 * subscriptions stub-out a minimal call against /v1/billing/subscriptions and
 * are best-tested in sandbox.
 *
 *   PAYPAL_CLIENT_ID, PAYPAL_SECRET, PAYPAL_MODE   ('sandbox' | 'live')
 *   PAYPAL_WEBHOOK_ID                                used for webhook signature verification
 */
class PayPalGateway implements PaymentGateway
{
    private string $clientId;
    private string $secret;
    private string $mode;
    private string $webhookId;

    public function __construct(?string $clientId = null, ?string $secret = null, ?string $mode = null, ?string $webhookId = null)
    {
        $this->clientId  = $clientId  ?? self::setting('ecom.paypal_client_id',  'PAYPAL_CLIENT_ID');
        $this->secret    = $secret    ?? self::setting('ecom.paypal_secret',     'PAYPAL_SECRET');
        $this->mode      = $mode      ?? self::setting('ecom.paypal_mode',       'PAYPAL_MODE') ?: 'sandbox';
        $this->webhookId = $webhookId ?? self::setting('ecom.paypal_webhook_id', 'PAYPAL_WEBHOOK_ID');
    }

    public function id(): string                  { return 'paypal'; }
    public function supportsSubscriptions(): bool { return true; }

    public function createPaymentIntent(Order $order, EcomCustomer $customer, array $options = []): array
    {
        $resp = $this->request('POST', '/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->number,
                'amount' => [
                    'currency_code' => $order->currency,
                    'value'         => number_format($order->total / (10 ** Money::decimals($order->currency)), Money::decimals($order->currency), '.', ''),
                ],
                'description' => 'Order ' . $order->number,
            ]],
            'application_context' => [
                'brand_name'  => self::setting('ecom.brand_name', 'BRAND_NAME') ?: 'SevenCMS',
                'user_action' => 'PAY_NOW',
                'return_url'  => $options['return_url'] ?? '/',
                'cancel_url'  => $options['cancel_url'] ?? '/',
            ],
        ]);

        $approve = null;
        foreach ((array)($resp['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') { $approve = $link['href']; break; }
        }

        return [
            'gateway_id'   => $resp['id'] ?? null,
            'redirect_url' => $approve,
            'payload'      => $resp,
        ];
    }

    public function createSubscription(Order $order, EcomCustomer $customer, Product $product, ?ProductVariant $variant, array $options = []): array
    {
        // Requires a Plan in the merchant's PayPal account; we don't auto-create it.
        // The plan id is expected in product.meta or settings.
        $planId = (string)($options['plan_id'] ?? '');
        if (!$planId) throw new RuntimeException('PayPal subscription requires a configured plan_id');

        $resp = $this->request('POST', '/v1/billing/subscriptions', [
            'plan_id'     => $planId,
            'subscriber'  => [
                'email_address' => $customer->email,
                'name'          => [
                    'given_name' => $customer->firstName ?: '',
                    'surname'    => $customer->lastName  ?: '',
                ],
            ],
            'application_context' => [
                'brand_name'   => 'SevenCMS',
                'user_action'  => 'SUBSCRIBE_NOW',
                'return_url'   => $options['return_url'] ?? '/',
                'cancel_url'   => $options['cancel_url'] ?? '/',
            ],
        ]);

        $approve = null;
        foreach ((array)($resp['links'] ?? []) as $link) {
            if (($link['rel'] ?? '') === 'approve') { $approve = $link['href']; break; }
        }

        return [
            'subscription_id' => $resp['id'] ?? null,
            'redirect_url'    => $approve,
            'payload'         => $resp,
        ];
    }

    public function refund(string $gatewayPaymentId, int $amount, string $currency, ?string $reason = null): ?string
    {
        // For PayPal, gateway_id is the capture id.
        $resp = $this->request('POST', "/v2/payments/captures/{$gatewayPaymentId}/refund", [
            'amount' => [
                'currency_code' => $currency,
                'value'         => number_format($amount / (10 ** Money::decimals($currency)), Money::decimals($currency), '.', ''),
            ],
            'note_to_payer' => $reason ?: 'Refund',
        ]);
        return $resp['id'] ?? null;
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        if ($this->webhookId === '') return false;
        $payload = [
            'auth_algo'         => $headers['paypal-auth-algo']         ?? '',
            'cert_url'          => $headers['paypal-cert-url']          ?? '',
            'transmission_id'   => $headers['paypal-transmission-id']   ?? '',
            'transmission_sig'  => $headers['paypal-transmission-sig']  ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id'        => $this->webhookId,
            'webhook_event'     => json_decode($rawBody, true) ?: new stdClass(),
        ];
        $resp = $this->request('POST', '/v1/notifications/verify-webhook-signature', $payload);
        return ($resp['verification_status'] ?? '') === 'SUCCESS';
    }

    public function handleWebhook(string $rawBody, array $headers): array
    {
        $event   = json_decode($rawBody, true) ?: [];
        $type    = (string)($event['event_type'] ?? '');
        $resource = $event['resource'] ?? [];

        return match (true) {
            in_array($type, ['CHECKOUT.ORDER.APPROVED', 'PAYMENT.CAPTURE.COMPLETED'], true) => [
                'type' => 'payment.succeeded',
                'order_id'        => null,
                'subscription_id' => null,
                'amount'          => isset($resource['amount']['value'])
                                    ? (int)round(((float)$resource['amount']['value']) * (10 ** Money::decimals((string)($resource['amount']['currency_code'] ?? 'USD'))))
                                    : null,
                'gateway_id'      => (string)($resource['id'] ?? ''),
                'raw'             => $event,
            ],
            in_array($type, ['BILLING.SUBSCRIPTION.PAYMENT.COMPLETED'], true) => [
                'type' => 'subscription.renewed',
                'order_id'        => null,
                'subscription_id' => (string)($resource['billing_agreement_id'] ?? $resource['id'] ?? ''),
                'amount'          => null,
                'gateway_id'      => (string)($resource['id'] ?? ''),
                'raw'             => $event,
            ],
            in_array($type, ['BILLING.SUBSCRIPTION.CANCELLED', 'BILLING.SUBSCRIPTION.EXPIRED'], true) => [
                'type' => 'subscription.cancelled',
                'order_id'        => null,
                'subscription_id' => (string)($resource['id'] ?? ''),
                'amount'          => null,
                'gateway_id'      => (string)($resource['id'] ?? ''),
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

    private function request(string $method, string $path, array $body = []): array
    {
        $base = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        $token = $this->accessToken();

        $ch = curl_init($base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        if ($method !== 'GET' && $body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $resp = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($resp, true) ?: [];
        if ($code >= 400) {
            throw new RuntimeException("PayPal {$code}: " . ($decoded['message'] ?? 'API error'));
        }
        return $decoded;
    }

    private function accessToken(): string
    {
        if ($this->clientId === '' || $this->secret === '') {
            throw new RuntimeException('PayPal credentials not configured');
        }
        $base = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        $ch = curl_init($base . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->clientId . ':' . $this->secret,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($resp, true) ?: [];
        if ($code >= 400 || empty($data['access_token'])) {
            throw new RuntimeException('PayPal auth failed');
        }
        return (string)$data['access_token'];
    }

    private static function setting(string $key, string $envFallback): string
    {
        $row = DB::findOne('settings', ' `key` = :k ', [':k' => $key]);
        if ($row && (string)$row['value'] !== '') return (string)$row['value'];
        return (string)Env::get($envFallback, '');
    }
}
