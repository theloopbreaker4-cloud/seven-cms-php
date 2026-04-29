<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * EcomCustomer — shop-side customer record.
 *
 * One-to-one with `users.id` for authenticated buyers; standalone (user_id NULL)
 * for guest checkouts. Email is the natural key — duplicate guest checkouts with
 * the same email collapse onto one customer row.
 */
class EcomCustomer extends Model
{
    public ?int    $id                = null;
    public ?int    $userId            = null;
    public string  $email             = '';
    public ?string $firstName         = null;
    public ?string $lastName          = null;
    public ?string $phone             = null;
    public int     $acceptsMarketing  = 0;
    public ?string $stripeCustomerId  = null;
    public ?string $paypalPayerId     = null;
    public ?string $notes             = null;
    public int     $totalSpent        = 0;
    public int     $ordersCount       = 0;
    public ?string $createdAt         = null;
    public ?string $updatedAt         = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public static function findByEmail(string $email): ?EcomCustomer
    {
        $row = DB::findOne('ecom_customers', ' email = :e ', [':e' => strtolower($email)]);
        return $row ? new self($row) : null;
    }

    public static function findByUserId(int $userId): ?EcomCustomer
    {
        $row = DB::findOne('ecom_customers', ' user_id = :u ', [':u' => $userId]);
        return $row ? new self($row) : null;
    }

    public static function findOrCreate(string $email, array $defaults = []): EcomCustomer
    {
        $existing = self::findByEmail($email);
        if ($existing) return $existing;

        $c = new self();
        $c->email           = strtolower(trim($email));
        $c->firstName       = $defaults['firstName'] ?? null;
        $c->lastName        = $defaults['lastName']  ?? null;
        $c->phone           = $defaults['phone']     ?? null;
        $c->userId          = $defaults['userId']    ?? null;
        $c->acceptsMarketing= !empty($defaults['acceptsMarketing']) ? 1 : 0;
        $c->createdAt       = date('Y-m-d H:i:s');
        $c->save();
        return $c;
    }

    public function addresses(): array
    {
        if (!$this->id) return [];
        return DB::getAll(
            'SELECT * FROM ecom_addresses WHERE customer_id = :c ORDER BY is_default DESC, id ASC',
            [':c' => $this->id]
        ) ?: [];
    }

    public function recordOrder(int $orderTotal): void
    {
        if (!$this->id) return;
        DB::execute(
            'UPDATE ecom_customers
                SET orders_count = orders_count + 1, total_spent = total_spent + :t
              WHERE id = :id',
            [':t' => $orderTotal, ':id' => $this->id]
        );
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'userId'           => $this->userId,
            'email'            => $this->email,
            'firstName'        => $this->firstName,
            'lastName'         => $this->lastName,
            'phone'            => $this->phone,
            'acceptsMarketing' => (bool)$this->acceptsMarketing,
            'totalSpent'       => (int)$this->totalSpent,
            'ordersCount'      => (int)$this->ordersCount,
            'stripeCustomerId' => $this->stripeCustomerId,
            'createdAt'        => $this->createdAt,
        ];
    }
}
