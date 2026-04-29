<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Subscription — recurring billing grant.
 *
 * Created on the first successful payment of a subscription product. Renewal
 * happens via the gateway's own scheduler (Stripe sends `invoice.paid` events;
 * PayPal sends `BILLING.SUBSCRIPTION.PAYMENT.COMPLETED`). Webhooks update
 * `current_period_end`, status, etc.
 *
 * For "manual" gateway, a cron `php bin/sev ecom:bill-due` will eventually
 * step through expiring subscriptions and create invoices.
 */
class Subscription extends Model
{
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_PASTDUE  = 'past_due';
    public const STATUS_PAUSED   = 'paused';
    public const STATUS_CANCELLED= 'cancelled';
    public const STATUS_EXPIRED  = 'expired';

    public ?int    $id                  = null;
    public ?int    $customerId          = null;
    public ?int    $productId           = null;
    public ?int    $variantId           = null;
    public string  $gateway             = 'manual';
    public ?string $gatewaySubscriptionId = null;
    public string  $status              = self::STATUS_ACTIVE;
    public string  $currency            = 'USD';
    public int     $unitPrice           = 0;
    public int     $quantity            = 1;
    public string  $billingPeriod       = 'month';
    public int     $billingInterval     = 1;
    public ?string $currentPeriodStart  = null;
    public ?string $currentPeriodEnd    = null;
    public ?string $trialEndsAt         = null;
    public int     $cancelAtPeriodEnd   = 0;
    public ?string $cancelledAt         = null;
    public ?string $createdAt           = null;
    public ?string $updatedAt           = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public static function findById(int $id): ?Subscription
    {
        $row = DB::findOne('ecom_subscriptions', ' id = :id ', [':id' => $id]);
        return $row ? new self($row) : null;
    }

    public static function listForCustomer(int $customerId): array
    {
        return DB::getAll(
            'SELECT * FROM ecom_subscriptions WHERE customer_id = :c ORDER BY id DESC',
            [':c' => $customerId]
        ) ?: [];
    }

    /** Compute next period end relative to a date. */
    public static function advancePeriod(string $from, string $period, int $interval): string
    {
        $unit = match ($period) {
            'day'   => 'day',
            'week'  => 'week',
            'month' => 'month',
            'year'  => 'year',
            default => 'month',
        };
        return (new DateTimeImmutable($from))
            ->modify('+' . max(1, $interval) . ' ' . $unit)
            ->format('Y-m-d H:i:s');
    }

    public function cancelAtPeriodEnd(): void
    {
        if (!$this->id) return;
        DB::execute(
            'UPDATE ecom_subscriptions SET cancel_at_period_end = 1 WHERE id = :id',
            [':id' => $this->id]
        );
        Event::dispatch('ecom.subscription.cancel_scheduled', $this);
    }

    public function cancelImmediately(): void
    {
        if (!$this->id) return;
        DB::execute(
            'UPDATE ecom_subscriptions
                SET status = :s, cancelled_at = NOW()
              WHERE id = :id',
            [':s' => self::STATUS_CANCELLED, ':id' => $this->id]
        );
        $this->status      = self::STATUS_CANCELLED;
        $this->cancelledAt = date('Y-m-d H:i:s');
        Event::dispatch('ecom.subscription.cancelled', $this);
    }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'customerId'          => $this->customerId,
            'productId'           => $this->productId,
            'variantId'           => $this->variantId,
            'gateway'             => $this->gateway,
            'gatewaySubscriptionId' => $this->gatewaySubscriptionId,
            'status'              => $this->status,
            'currency'            => $this->currency,
            'unitPrice'           => (int)$this->unitPrice,
            'quantity'            => (int)$this->quantity,
            'billingPeriod'       => $this->billingPeriod,
            'billingInterval'     => (int)$this->billingInterval,
            'currentPeriodStart'  => $this->currentPeriodStart,
            'currentPeriodEnd'    => $this->currentPeriodEnd,
            'trialEndsAt'         => $this->trialEndsAt,
            'cancelAtPeriodEnd'   => (bool)$this->cancelAtPeriodEnd,
            'cancelledAt'         => $this->cancelledAt,
        ];
    }
}
