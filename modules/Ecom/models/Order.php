<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Order — central record. Created at checkout, updated through fulfillment.
 *
 * Money fields are integers in `currency` minor units. Status fields:
 *   - status            — overall lifecycle
 *   - payment_status    — money state
 *   - fulfillment_status— shipping state
 * They evolve independently because an order can be "paid + unfulfilled" (paid,
 * waiting to ship) or "fulfilled + unpaid" (manual cash on delivery).
 */
class Order extends Model
{
    public ?int    $id                  = null;
    public string  $number              = '';
    public ?int    $customerId          = null;
    public string  $email               = '';
    public string  $currency            = 'USD';
    public string  $status              = 'pending';
    public string  $paymentStatus       = 'unpaid';
    public string  $fulfillmentStatus   = 'unfulfilled';
    public int     $subtotal            = 0;
    public int     $discountTotal       = 0;
    public int     $taxTotal            = 0;
    public int     $shippingTotal       = 0;
    public int     $total               = 0;
    public ?string $discountCode        = null;
    public string  $billingAddress      = '{}';
    public string  $shippingAddress     = '{}';
    public ?string $shippingMethod      = null;
    public ?string $note                = null;
    public string  $meta                = '{}';
    public ?string $placedAt            = null;
    public ?string $paidAt              = null;
    public ?string $cancelledAt         = null;
    public ?string $refundedAt          = null;
    public ?string $createdAt           = null;
    public ?string $updatedAt           = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public static function findById(int $id): ?Order
    {
        $row = DB::findOne('ecom_orders', ' id = :id ', [':id' => $id]);
        return $row ? new self($row) : null;
    }

    public static function findByNumber(string $number): ?Order
    {
        $row = DB::findOne('ecom_orders', ' number = :n ', [':n' => $number]);
        return $row ? new self($row) : null;
    }

    /** Generate a human-readable order number like "SC-20260426-A12B". */
    public static function generateNumber(): string
    {
        return 'SC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    public function items(): array
    {
        if (!$this->id) return [];
        return DB::getAll('SELECT * FROM ecom_order_items WHERE order_id = :o ORDER BY id', [':o' => $this->id]) ?: [];
    }

    public function payments(): array
    {
        if (!$this->id) return [];
        return DB::getAll('SELECT * FROM ecom_payments WHERE order_id = :o ORDER BY id DESC', [':o' => $this->id]) ?: [];
    }

    public function isPaid(): bool { return $this->paymentStatus === 'paid'; }

    public function markPaid(): void
    {
        if (!$this->id) return;
        DB::execute(
            'UPDATE ecom_orders
                SET status = "paid", payment_status = "paid", paid_at = NOW()
              WHERE id = :id AND payment_status <> "paid"',
            [':id' => $this->id]
        );
        $this->paymentStatus = 'paid';
        $this->status        = 'paid';
        $this->paidAt        = date('Y-m-d H:i:s');

        if (class_exists('Hooks')) Hooks::fire('afterUpdate', 'order', $this);
        Event::dispatch('ecom.order.paid', $this);
        if (class_exists('ActivityLog')) {
            ActivityLog::log('ecom.order.paid', 'ecom_orders', (int)$this->id, "Order {$this->number} paid");
        }
    }

    public function markCancelled(?string $reason = null): void
    {
        if (!$this->id) return;
        DB::execute(
            'UPDATE ecom_orders SET status = "cancelled", cancelled_at = NOW() WHERE id = :id',
            [':id' => $this->id]
        );
        $this->status      = 'cancelled';
        $this->cancelledAt = date('Y-m-d H:i:s');

        Event::dispatch('ecom.order.cancelled', ['order' => $this, 'reason' => $reason]);
        if (class_exists('ActivityLog')) {
            ActivityLog::log('ecom.order.cancelled', 'ecom_orders', (int)$this->id, "Order {$this->number} cancelled");
        }
    }

    public function markFulfilled(): void
    {
        if (!$this->id) return;
        DB::execute(
            'UPDATE ecom_orders
                SET status = IF(payment_status = "paid", "fulfilled", status),
                    fulfillment_status = "fulfilled"
              WHERE id = :id',
            [':id' => $this->id]
        );
        $this->fulfillmentStatus = 'fulfilled';
        Event::dispatch('ecom.order.fulfilled', $this);
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'number'            => $this->number,
            'customerId'        => $this->customerId,
            'email'             => $this->email,
            'currency'          => $this->currency,
            'status'            => $this->status,
            'paymentStatus'     => $this->paymentStatus,
            'fulfillmentStatus' => $this->fulfillmentStatus,
            'subtotal'          => (int)$this->subtotal,
            'discountTotal'     => (int)$this->discountTotal,
            'taxTotal'          => (int)$this->taxTotal,
            'shippingTotal'     => (int)$this->shippingTotal,
            'total'             => (int)$this->total,
            'discountCode'      => $this->discountCode,
            'billingAddress'    => json_decode($this->billingAddress  ?: '{}', true) ?: [],
            'shippingAddress'   => json_decode($this->shippingAddress ?: '{}', true) ?: [],
            'shippingMethod'    => $this->shippingMethod,
            'note'              => $this->note,
            'meta'              => json_decode($this->meta ?: '{}', true) ?: [],
            'placedAt'          => $this->placedAt,
            'paidAt'            => $this->paidAt,
            'cancelledAt'       => $this->cancelledAt,
        ];
    }
}
