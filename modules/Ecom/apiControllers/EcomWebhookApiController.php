<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Inbound webhook receiver for payment gateways.
 *
 *   POST /api/v1/shop/webhook/:gateway     — gateway = 'stripe' | 'paypal' | 'manual'
 *
 * Verifies the signature, idempotency-checks via `ecom_webhook_events`, then
 * applies the normalized event:
 *   - payment.succeeded     → markPaid + fulfill digital
 *   - payment.failed        → mark order failed
 *   - subscription.renewed  → extend period + email
 *   - subscription.cancelled→ flip status + email
 *   - refund.created        → record refund row (only if not already from admin UI)
 */
class EcomWebhookApiController extends ApiController
{
    public function handle($req, $res, $params)
    {
        $gatewayId = (string)($params[0] ?? '');
        if (!GatewayRegistry::has($gatewayId)) $this->jsonError(404, 'Unknown gateway');

        $rawBody = (string)file_get_contents('php://input');
        $headers = self::headers();

        $gateway = GatewayRegistry::get($gatewayId);
        if (!$gateway->verifyWebhookSignature($rawBody, $headers)) {
            Logger::channel('app')->warning('Webhook signature invalid', ['gateway' => $gatewayId]);
            $this->jsonError(401, 'Invalid signature');
        }

        $event = $gateway->handleWebhook($rawBody, $headers);

        // Idempotency: store the raw event keyed by gateway+event id.
        $eid = (string)($event['gateway_id'] ?? ($event['raw']['id'] ?? ''));
        if ($eid) {
            $existing = DB::findOne('ecom_webhook_events', ' gateway = :g AND event_id = :e ',
                [':g' => $gatewayId, ':e' => $eid]);
            if ($existing && (int)$existing['processed'] === 1) {
                http_response_code(200);
                echo '{"ok":true,"duplicate":true}';
                return;
            }
            if (!$existing) {
                DB::execute(
                    'INSERT INTO ecom_webhook_events (gateway, event_id, event_type, payload)
                     VALUES (:g, :e, :t, :p)',
                    [':g' => $gatewayId, ':e' => $eid, ':t' => (string)($event['type'] ?? 'unknown'),
                     ':p' => json_encode($event['raw'] ?? [], JSON_UNESCAPED_UNICODE)]
                );
            }
        }

        try {
            $this->apply($event, $gatewayId);
            if ($eid) {
                DB::execute('UPDATE ecom_webhook_events SET processed = 1, error = NULL WHERE gateway = :g AND event_id = :e',
                    [':g' => $gatewayId, ':e' => $eid]);
            }
            http_response_code(200);
            echo '{"ok":true}';
        } catch (\Throwable $e) {
            if ($eid) {
                DB::execute('UPDATE ecom_webhook_events SET error = :err WHERE gateway = :g AND event_id = :e',
                    [':err' => $e->getMessage(), ':g' => $gatewayId, ':e' => $eid]);
            }
            Logger::channel('app')->error('Webhook processing failed', ['gateway' => $gatewayId, 'error' => $e->getMessage()]);
            $this->jsonError(500, 'Processing failed');
        }
    }

    private function apply(array $event, string $gatewayId): void
    {
        $type = (string)($event['type'] ?? 'unknown');

        switch ($type) {
            case 'payment.succeeded':
                $orderId = (int)($event['order_id'] ?? 0);
                if (!$orderId) {
                    // Try to find by gateway_id on payment row.
                    $row = DB::findOne('ecom_payments', ' gateway_id = :g ', [':g' => (string)$event['gateway_id']]);
                    $orderId = $row ? (int)$row['order_id'] : 0;
                }
                if (!$orderId) return;
                $order = Order::findById($orderId);
                if (!$order) return;
                DB::execute(
                    'UPDATE ecom_payments SET status = "paid", paid_at = NOW() WHERE order_id = :o AND gateway_id = :g',
                    [':o' => $orderId, ':g' => (string)$event['gateway_id']]
                );
                OrderService::fulfillPaidOrder($order);
                break;

            case 'payment.failed':
                $orderId = (int)($event['order_id'] ?? 0);
                if ($orderId) {
                    DB::execute(
                        'UPDATE ecom_orders SET status = "failed" WHERE id = :o AND payment_status <> "paid"',
                        [':o' => $orderId]
                    );
                    DB::execute(
                        'UPDATE ecom_payments SET status = "failed" WHERE order_id = :o AND gateway_id = :g',
                        [':o' => $orderId, ':g' => (string)$event['gateway_id']]
                    );
                }
                break;

            case 'subscription.renewed': {
                $extId = (string)$event['subscription_id'];
                $sub = $extId ? DB::findOne('ecom_subscriptions', ' gateway = :g AND gateway_subscription_id = :id ',
                    [':g' => $gatewayId, ':id' => $extId]) : null;
                if ($sub) {
                    $next = Subscription::advancePeriod(
                        date('Y-m-d H:i:s'),
                        (string)$sub['billing_period'],
                        (int)$sub['billing_interval']
                    );
                    DB::execute(
                        'UPDATE ecom_subscriptions
                            SET status = "active",
                                current_period_start = NOW(),
                                current_period_end   = :n
                          WHERE id = :id',
                        [':n' => $next, ':id' => (int)$sub['id']]
                    );
                    $subModel = Subscription::findById((int)$sub['id']);
                    if ($subModel) {
                        Event::dispatch('ecom.subscription.renewed', $subModel);
                        if (class_exists('EcomMail')) EcomMail::subscriptionRenewed($subModel, null);
                    }
                }
                break;
            }

            case 'subscription.cancelled': {
                $extId = (string)$event['subscription_id'];
                $sub = $extId ? DB::findOne('ecom_subscriptions', ' gateway = :g AND gateway_subscription_id = :id ',
                    [':g' => $gatewayId, ':id' => $extId]) : null;
                if ($sub) {
                    DB::execute(
                        'UPDATE ecom_subscriptions SET status = "cancelled", cancelled_at = NOW() WHERE id = :id',
                        [':id' => (int)$sub['id']]
                    );
                    $subModel = Subscription::findById((int)$sub['id']);
                    if ($subModel) {
                        Event::dispatch('ecom.subscription.cancelled', $subModel);
                        if (class_exists('EcomMail')) EcomMail::subscriptionCancelled($subModel);
                    }
                }
                break;
            }

            case 'refund.created':
                $orderId = (int)($event['order_id'] ?? 0);
                $amount  = (int)($event['amount']   ?? 0);
                if ($orderId && $amount > 0) {
                    $order = Order::findById($orderId);
                    if (!$order) return;
                    $newStatus = $amount >= $order->total ? 'refunded' : 'partially_refunded';
                    DB::execute(
                        'UPDATE ecom_orders SET status = :s, payment_status = :ps, refunded_at = NOW() WHERE id = :o',
                        [':s' => $newStatus, ':ps' => $newStatus, ':o' => $orderId]
                    );
                    Event::dispatch('ecom.order.refunded', ['order' => $order, 'amount' => $amount]);
                }
                break;

            default:
                Logger::channel('app')->info('Webhook ignored', ['type' => $type, 'gateway' => $gatewayId]);
        }
    }

    private static function headers(): array
    {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            return array_change_key_case($h ?: [], CASE_LOWER);
        }
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($k, 5)));
                $out[$name] = (string)$v;
            }
        }
        return $out;
    }
}
