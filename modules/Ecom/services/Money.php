<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Money — utility for amounts stored in minor units (cents/kopeks).
 *
 * The whole e-commerce module never uses floats for money; everything is
 * an integer count of the smallest currency unit. This class converts
 * those integers to/from display strings.
 *
 * Currency metadata lives here too — symbol, decimal places, format style.
 */
class Money
{
    /** @var array<string,array{symbol:string,decimals:int,prefix:bool}> */
    public const CURRENCIES = [
        'USD' => ['symbol' => '$',     'decimals' => 2, 'prefix' => true],
        'EUR' => ['symbol' => '€',     'decimals' => 2, 'prefix' => true],
        'GBP' => ['symbol' => '£',     'decimals' => 2, 'prefix' => true],
        'RUB' => ['symbol' => '₽',     'decimals' => 2, 'prefix' => false],
        'UAH' => ['symbol' => '₴',     'decimals' => 2, 'prefix' => false],
        'GEL' => ['symbol' => '₾',     'decimals' => 2, 'prefix' => false],
        'AMD' => ['symbol' => '֏',     'decimals' => 2, 'prefix' => false],
        'AZN' => ['symbol' => '₼',     'decimals' => 2, 'prefix' => false],
        'JPY' => ['symbol' => '¥',     'decimals' => 0, 'prefix' => true],
        'CHF' => ['symbol' => 'CHF',   'decimals' => 2, 'prefix' => true],
    ];

    public static function format(int $minor, string $currency = 'USD', string $locale = 'en'): string
    {
        $cur = self::meta($currency);
        $div = 10 ** $cur['decimals'];
        $whole = intdiv($minor, $div);
        $frac  = abs($minor) % $div;
        $body  = $cur['decimals'] > 0
            ? number_format($whole + ($frac / $div), $cur['decimals'], '.', ',')
            : number_format($whole, 0, '.', ',');
        return $cur['prefix']
            ? $cur['symbol'] . $body
            : $body . ' ' . $cur['symbol'];
    }

    /** Convert a "12.34" / "12,34" / "12" string to minor units for the given currency. */
    public static function fromInput(string $input, string $currency = 'USD'): int
    {
        $cur = self::meta($currency);
        $clean = str_replace([' ', ','], ['', '.'], trim($input));
        if ($clean === '' || !is_numeric($clean)) return 0;
        return (int)round(((float)$clean) * (10 ** $cur['decimals']));
    }

    public static function decimals(string $currency): int
    {
        return self::meta($currency)['decimals'];
    }

    public static function symbol(string $currency): string
    {
        return self::meta($currency)['symbol'];
    }

    private static function meta(string $currency): array
    {
        return self::CURRENCIES[strtoupper($currency)] ?? self::CURRENCIES['USD'];
    }
}
