<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * TaxCalculator — picks a rate from `ecom_tax_rates` based on shipping country/state,
 * falling back to the global default in settings.
 *
 *   $r = TaxCalculator::compute($subtotalAfterDiscount, $shippingAddress, $currency);
 *   // returns ['amount' => int, 'rate_bp' => int, 'inclusive' => bool]
 *
 * "rate_bp" is basis points (rate * 10000). 1500 = 15%.
 */
class TaxCalculator
{
    public static function compute(int $base, ?array $address, string $currency, string $taxClass = 'standard'): array
    {
        if ($base <= 0) return ['amount' => 0, 'rate_bp' => 0, 'inclusive' => false];

        // 1. Per-region rate when we have an address.
        if ($address && !empty($address['country'])) {
            $row = DB::findOne(
                'ecom_tax_rates',
                ' tax_class = :tc AND country = :c AND (state IS NULL OR state = :s) ',
                [':tc' => $taxClass, ':c' => strtoupper((string)$address['country']), ':s' => (string)($address['state'] ?? '')]
            );
            if ($row) {
                $rateBp    = (int)$row['rate_bp'];
                $inclusive = (bool)$row['is_inclusive'];
                $amount    = $inclusive
                    ? (int)round($base - ($base * 10000 / (10000 + $rateBp))) // tax already in price
                    : (int)round($base * $rateBp / 10000);
                return ['amount' => $amount, 'rate_bp' => $rateBp, 'inclusive' => $inclusive];
            }
        }

        // 2. Global default.
        $defaultRate = (float)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.tax_rate"') ?? 0);
        $inclusive   = (bool)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.tax_inclusive"') ?? false);
        if ($defaultRate <= 0) return ['amount' => 0, 'rate_bp' => 0, 'inclusive' => $inclusive];

        $rateBp = (int)round($defaultRate * 100);
        $amount = $inclusive
            ? (int)round($base - ($base * 10000 / (10000 + $rateBp)))
            : (int)round($base * $rateBp / 10000);
        return ['amount' => $amount, 'rate_bp' => $rateBp, 'inclusive' => $inclusive];
    }
}
