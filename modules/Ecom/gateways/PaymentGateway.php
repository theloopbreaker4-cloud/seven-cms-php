<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * PaymentGateway — interface every payment driver implements.
 *
 *   createPaymentIntent   — for one-off charges; returns gateway-specific data the
 *                           frontend uses to confirm payment (e.g. Stripe client_secret,
 *                           PayPal approval URL, or a redirect URL for hosted checkout)
 *   createSubscription    — for recurring billing; only required when the driver supports it
 *   verifyWebhookSignature — used by the webhook controller to authenticate inbound events
 *   refund                — partial or full refund of a captured payment
 */
interface PaymentGateway
{
    public function id(): string;
    public function supportsSubscriptions(): bool;

    /**
     * Build a payment intent for an order.
     *
     * @return array{
     *   client_secret?: string,
     *   redirect_url?:  string,
     *   gateway_id?:    string,
     *   payload?:       array
     * }
     */
    public function createPaymentIntent(Order $order, EcomCustomer $customer, array $options = []): array;

    /**
     * Create a recurring subscription. Implementations may return data needed for the
     * checkout (e.g. Stripe `client_secret` for the initial invoice, or a redirect URL).
     *
     * @return array{ subscription_id?: string, redirect_url?: string, client_secret?: string, payload?: array }
     */
    public function createSubscription(Order $order, EcomCustomer $customer, Product $product, ?ProductVariant $variant, array $options = []): array;

    /** Refund (partial or full). Returns the gateway refund id or null on failure. */
    public function refund(string $gatewayPaymentId, int $amount, string $currency, ?string $reason = null): ?string;

    /** True when the inbound webhook is genuinely from this gateway. */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool;

    /**
     * Translate a verified webhook event into a normalized form:
     *
     *   ['type' => 'payment.succeeded'|'payment.failed'|'subscription.renewed'
     *             |'subscription.cancelled'|'refund.created'|'unknown',
     *    'order_id'   => int|null,
     *    'subscription_id' => int|null,
     *    'amount'     => int|null,
     *    'gateway_id' => string|null,
     *    'raw'        => array]
     */
    public function handleWebhook(string $rawBody, array $headers): array;
}
