<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * SubscriptionBiller — drives recurring billing for the "manual" gateway.
 *
 * Stripe + PayPal subscriptions renew themselves and emit webhooks; we just
 * handle the case where a merchant wants to bill internally (e.g. invoice
 * by hand or through a custom processor).
 *
 * For each subscription whose `current_period_end <= NOW()`, this service:
 *   1. Builds a renewal Order in the same shape as a normal checkout.
 *   2. Marks it `pending` (the merchant marks it paid through the admin).
 *   3. Advances `current_period_end`.
 *   4. Sends a "subscription_renewed" email.
 *
 * Wired up via cron job `ecom.subscription.bill_due` registered in
 * EcomModule::boot(). Runs every hour.
 */
class SubscriptionBiller
{
    /** @return array{billed:int,failed:int,errors:array<int,string>} */
    public static function billDue(): array
    {
        $rows = DB::getAll(
            "SELECT * FROM ecom_subscriptions
              WHERE status IN ('active','past_due','trialing')
                AND gateway = 'manual'
                AND current_period_end IS NOT NULL
                AND current_period_end <= NOW()"
        );

        $billed = 0; $failed = 0; $errors = [];
        foreach ($rows as $row) {
            try {
                self::renew($row);
                $billed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "sub#{$row['id']}: " . $e->getMessage();
                Logger::channel('ecom')->error('Subscription renewal failed', [
                    'subscription_id' => $row['id'],
                    'error'           => $e->getMessage(),
                ]);
                DB::execute(
                    'UPDATE ecom_subscriptions SET status = "past_due" WHERE id = :id',
                    [':id' => $row['id']]
                );
            }
        }

        return ['billed' => $billed, 'failed' => $failed, 'errors' => $errors];
    }

    private static function renew(array $sub): void
    {
        $customer = DB::findOne('ecom_customers', ' id = :id ', [':id' => $sub['customer_id']]);
        if (!$customer) throw new \RuntimeException('Customer missing');

        $product = DB::findOne('ecom_products', ' id = :id ', [':id' => $sub['product_id']]);
        $title   = $product['title'] ?? 'Subscription renewal';

        $now    = date('Y-m-d H:i:s');
        $number = 'INV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $unit   = (int)$sub['unit_price'];
        $qty    = (int)$sub['quantity'];
        $total  = $unit * $qty;

        DB::execute(
            'INSERT INTO ecom_orders
                (number, customer_id, email, status, payment_status, currency,
                 subtotal, tax, shipping, discount, total, gateway, created_at, updated_at)
             VALUES (:n, :cid, :em, "pending", "pending", :cur, :st, 0, 0, 0, :tot, "manual", :ca, :ca)',
            [
                ':n'   => $number,
                ':cid' => (int)$sub['customer_id'],
                ':em'  => $customer['email'],
                ':cur' => $sub['currency'] ?: 'USD',
                ':st'  => $total,
                ':tot' => $total,
                ':ca'  => $now,
            ]
        );
        $orderId = (int)DB::getCell('SELECT LAST_INSERT_ID()');

        DB::execute(
            'INSERT INTO ecom_order_items
                (order_id, product_id, variant_id, title, quantity, unit_price, line_total)
             VALUES (:oid, :pid, :vid, :t, :q, :u, :l)',
            [
                ':oid' => $orderId,
                ':pid' => (int)$sub['product_id'],
                ':vid' => $sub['variant_id'] ?: null,
                ':t'   => $title,
                ':q'   => $qty,
                ':u'   => $unit,
                ':l'   => $total,
            ]
        );

        // Advance period.
        $nextEnd = Subscription::advancePeriod(
            (string)$sub['current_period_end'],
            (string)$sub['billing_period'],
            (int)$sub['billing_interval']
        );
        DB::execute(
            'UPDATE ecom_subscriptions
                SET current_period_start = current_period_end,
                    current_period_end   = :end,
                    status               = "active",
                    updated_at           = NOW()
              WHERE id = :id',
            [':end' => $nextEnd, ':id' => (int)$sub['id']]
        );

        // Notify merchant via in-app + email.
        if (class_exists('Notify')) {
            Notify::admins('subscription.renewed', [
                'title'   => "Subscription renewed: {$customer['email']}",
                'message' => "Invoice {$number} created — manual collection required.",
                'url'     => '/admin/ecom/orders/' . $orderId,
            ]);
        }

        $subModel = new Subscription($sub);
        $orderModel = new Order(DB::findOne('ecom_orders', ' id = :id ', [':id' => $orderId]) ?: []);
        EcomMail::subscriptionRenewed($subModel, $orderModel);

        Event::dispatch('ecom.subscription.renewed', [
            'subscription_id' => $sub['id'],
            'order_id'        => $orderId,
        ]);
    }
}
