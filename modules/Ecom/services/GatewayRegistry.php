<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * GatewayRegistry — central place to resolve a payment driver by id.
 *
 *   $gw = GatewayRegistry::get('stripe');
 *   $gw->createPaymentIntent($order, $customer);
 *
 * Drivers register themselves on plugin boot; users / settings can disable
 * specific gateways without removing files.
 */
class GatewayRegistry
{
    /** @var array<string, callable(): PaymentGateway> */
    private static array $factories = [];

    public static function register(string $id, callable $factory): void
    {
        self::$factories[$id] = $factory;
    }

    public static function get(string $id): PaymentGateway
    {
        if (!isset(self::$factories[$id])) {
            throw new RuntimeException("Payment gateway '{$id}' is not registered");
        }
        return (self::$factories[$id])();
    }

    public static function has(string $id): bool { return isset(self::$factories[$id]); }

    /** @return array<int,string> */
    public static function ids(): array { return array_keys(self::$factories); }

    /**
     * Boot the default gateways. Called from the Ecom plugin's boot().
     * Each gateway is created lazily — instantiation only happens on first use.
     */
    public static function bootDefaults(): void
    {
        self::register('manual', fn() => new ManualGateway());
        self::register('stripe', fn() => new StripeGateway());
        self::register('paypal', fn() => new PayPalGateway());
    }
}
