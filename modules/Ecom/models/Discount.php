<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Discount — promo code that may apply to subtotal or shipping.
 *
 *   kind = 'percent'        — value is % * 100 (e.g. 1500 = 15%)
 *   kind = 'fixed'          — value is a flat amount in minor units
 *   kind = 'free_shipping'  — sets shipping_total to 0; value ignored
 */
class Discount extends Model
{
    public ?int    $id              = null;
    public string  $code            = '';
    public string  $kind            = 'percent';
    public int     $value           = 0;
    public ?int    $minSubtotal     = null;
    public string  $appliesTo       = 'all';
    public ?int    $appliesId       = null;
    public ?int    $usageLimit      = null;
    public int     $usageCount      = 0;
    public ?int    $perCustomerLimit= null;
    public ?string $startsAt        = null;
    public ?string $endsAt          = null;
    public int     $isActive        = 1;
    public ?string $createdAt       = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public static function findByCode(string $code): ?Discount
    {
        $row = DB::findOne('ecom_discounts', ' code = :c ', [':c' => strtoupper(trim($code))]);
        return $row ? new self($row) : null;
    }

    /** Returns null when valid, or an error message string. */
    public function validate(int $subtotal, ?int $customerId = null): ?string
    {
        if (!$this->isActive)                                  return 'Code is not active';
        if ($this->startsAt && strtotime($this->startsAt) > time()) return 'Code is not yet active';
        if ($this->endsAt   && strtotime($this->endsAt)   < time()) return 'Code expired';
        if ($this->usageLimit !== null && $this->usageCount >= $this->usageLimit) return 'Code reached its usage limit';
        if ($this->minSubtotal && $subtotal < $this->minSubtotal) return 'Minimum subtotal not reached';

        if ($customerId && $this->perCustomerLimit !== null) {
            $used = (int)DB::getCell(
                'SELECT COUNT(*) FROM ecom_orders
                  WHERE customer_id = :c AND discount_code = :code',
                [':c' => $customerId, ':code' => $this->code]
            );
            if ($used >= $this->perCustomerLimit) return 'You have used this code already';
        }
        return null;
    }

    /**
     * Compute discount amount in minor units.
     *
     * @return array{discount:int, free_shipping:bool}
     */
    public function compute(int $subtotal, int $shipping): array
    {
        return match ($this->kind) {
            'percent'       => ['discount' => (int)floor($subtotal * $this->value / 10000), 'free_shipping' => false],
            'fixed'         => ['discount' => min($this->value, $subtotal), 'free_shipping' => false],
            'free_shipping' => ['discount' => 0, 'free_shipping' => true],
            default         => ['discount' => 0, 'free_shipping' => false],
        };
    }

    public function recordUsage(): void
    {
        if (!$this->id) return;
        DB::execute('UPDATE ecom_discounts SET usage_count = usage_count + 1 WHERE id = :id', [':id' => $this->id]);
    }
}
