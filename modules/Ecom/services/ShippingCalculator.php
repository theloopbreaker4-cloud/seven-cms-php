<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ShippingCalculator — picks a shipping rate from `ecom_shipping_rates`.
 *
 * Strategy:
 *   - filter by country (NULL = "any country")
 *   - filter by min/max subtotal and weight
 *   - if a specific method name is requested, prefer it; else pick cheapest match
 */
class ShippingCalculator
{
    /** Returns the rate amount in minor units, or 0 when nothing matches. */
    public static function pickRate(int $subtotal, int $weight, string $country, ?string $method = null): int
    {
        $rates = self::candidates($subtotal, $weight, $country);
        if (!$rates) return 0;

        if ($method) {
            foreach ($rates as $r) {
                $name = json_decode((string)$r['name'], true);
                $primary = is_array($name) ? ($name['en'] ?? array_values($name)[0] ?? '') : (string)$r['name'];
                if (strcasecmp($method, $primary) === 0) return (int)$r['price'];
            }
        }
        // Cheapest match.
        usort($rates, fn($a, $b) => (int)$a['price'] <=> (int)$b['price']);
        return (int)$rates[0]['price'];
    }

    /**
     * Returns all matching rates so the checkout can render a chooser.
     *
     * @return array<int,array>
     */
    public static function options(int $subtotal, int $weight, string $country): array
    {
        return self::candidates($subtotal, $weight, $country);
    }

    private static function candidates(int $subtotal, int $weight, string $country): array
    {
        $rows = DB::getAll(
            'SELECT * FROM ecom_shipping_rates
              WHERE is_active = 1
                AND (country IS NULL OR country = :c)
                AND (min_subtotal IS NULL OR :s >= min_subtotal)
                AND (max_subtotal IS NULL OR :s <= max_subtotal)
                AND (min_weight   IS NULL OR :w >= min_weight)
                AND (max_weight   IS NULL OR :w <= max_weight)
              ORDER BY sort_order ASC, price ASC',
            [':c' => strtoupper($country) ?: null, ':s' => $subtotal, ':w' => $weight]
        ) ?: [];
        return $rows;
    }
}
