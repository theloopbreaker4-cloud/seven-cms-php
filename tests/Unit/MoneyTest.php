<?php

declare(strict_types=1);

namespace Seven\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Money lives under modules/, classmap autoload covers it.
        $f = ROOT_DIR . '/modules/Ecom/services/Money.php';
        if (!class_exists('Money') && is_file($f)) require_once $f;
    }

    public function testFormatUsdPrefixesSymbol(): void
    {
        $this->assertSame('$10.00', \Money::format(1000, 'USD'));
        $this->assertSame('$1,234.56', \Money::format(123456, 'USD'));
    }

    public function testFormatRubSuffixesSymbol(): void
    {
        $this->assertSame('100.00 ₽', \Money::format(10000, 'RUB'));
    }

    public function testZeroDecimalCurrency(): void
    {
        // JPY has 0 decimals: 1500 minor units = 1500 JPY
        $this->assertSame('¥1,500', \Money::format(1500, 'JPY'));
    }

    public function testFromInputParsesDotAndComma(): void
    {
        $this->assertSame(1234, \Money::fromInput('12.34', 'USD'));
        $this->assertSame(1234, \Money::fromInput('12,34', 'USD'));
        $this->assertSame(1200, \Money::fromInput('12',    'USD'));
        $this->assertSame(0,    \Money::fromInput('',      'USD'));
        $this->assertSame(0,    \Money::fromInput('abc',   'USD'));
    }

    public function testUnknownCurrencyFallsBackToUsd(): void
    {
        $this->assertSame('$10.00', \Money::format(1000, 'XYZ'));
        $this->assertSame(2,        \Money::decimals('XYZ'));
    }

    public function testRoundTrip(): void
    {
        $minor = \Money::fromInput('99.99', 'USD');
        $this->assertSame('$99.99', \Money::format($minor, 'USD'));
    }
}
