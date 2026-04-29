<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ManualGateway — bank transfer / cash on delivery / "we'll invoice you" flows.
 *
 * No external API call. The order goes to "pending" payment status; an admin marks
 * it paid manually from /admin/ecom/orders/:id. Useful as a fallback or for
 * shops that don't take card payments online.
 */
class ManualGateway implements PaymentGateway
{
    public function id(): string                     { return 'manual'; }
    public function supportsSubscriptions(): bool    { return false; }

    public function createPaymentIntent(Order $order, EcomCustomer $customer, array $options = []): array
    {
        return [
            'gateway_id'   => 'manual_' . $order->number,
            'redirect_url' => null,
            'payload'      => ['instruction' => 'Awaiting manual confirmation by admin.'],
        ];
    }

    public function createSubscription(Order $order, EcomCustomer $customer, Product $product, ?ProductVariant $variant, array $options = []): array
    {
        // Manual subscriptions: we just record the schedule and rely on cron to bill. Not implemented in MVP.
        return [
            'subscription_id' => 'manual_sub_' . $order->number,
            'payload'         => ['note' => 'Manual recurring billing requires cron — see ECOM.md'],
        ];
    }

    public function refund(string $gatewayPaymentId, int $amount, string $currency, ?string $reason = null): ?string
    {
        // Money was never charged through us; just record the intent.
        return 'manual_refund_' . bin2hex(random_bytes(4));
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool { return true; }

    public function handleWebhook(string $rawBody, array $headers): array
    {
        return ['type' => 'unknown', 'order_id' => null, 'subscription_id' => null, 'amount' => null, 'gateway_id' => null, 'raw' => []];
    }
}
