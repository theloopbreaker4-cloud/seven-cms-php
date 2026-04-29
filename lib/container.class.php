<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Container — minimal PSR-11-style service container.
 *
 * Supports three styles of registration:
 *   1. Container::set('foo', new Foo())            // pre-built instance
 *   2. Container::factory('foo', fn($c) => new Foo($c->get('db')))  // build on demand, cached
 *   3. Container::bind('foo', fn($c) => new Foo()) // build on every get(), no cache
 *
 * Plus `singleton()` alias for `factory()` and `has()` for existence check.
 *
 * Usage:
 *   Container::set('db', DB::instance());
 *   Container::factory('cache', fn() => new Cache());
 *   $cache = Container::get('cache');
 */
class Container
{
    /** @var array<string,mixed> Resolved instances. */
    private static array $instances = [];

    /** @var array<string,callable> Factory closures (cache result). */
    private static array $factories = [];

    /** @var array<string,callable> Bind closures (rebuild every time). */
    private static array $binds = [];

    public static function set(string $id, $instance): void
    {
        self::$instances[$id] = $instance;
    }

    /** Cache after first build. */
    public static function factory(string $id, callable $factory): void
    {
        self::$factories[$id] = $factory;
        unset(self::$instances[$id]);
    }

    public static function singleton(string $id, callable $factory): void
    {
        self::factory($id, $factory);
    }

    /** Rebuild on every get(). */
    public static function bind(string $id, callable $factory): void
    {
        self::$binds[$id] = $factory;
    }

    public static function has(string $id): bool
    {
        return isset(self::$instances[$id])
            || isset(self::$factories[$id])
            || isset(self::$binds[$id]);
    }

    public static function get(string $id)
    {
        if (isset(self::$binds[$id])) {
            return (self::$binds[$id])(self::container());
        }
        if (isset(self::$instances[$id])) {
            return self::$instances[$id];
        }
        if (isset(self::$factories[$id])) {
            self::$instances[$id] = (self::$factories[$id])(self::container());
            return self::$instances[$id];
        }
        throw new RuntimeException("Service not registered: {$id}");
    }

    /**
     * Convenience accessor passed to factories so they can resolve other deps.
     * Returns a callable wrapper so factories can do $c->get('db').
     */
    private static function container(): object
    {
        return new class {
            public function get(string $id)  { return Container::get($id); }
            public function has(string $id): bool { return Container::has($id); }
        };
    }

    /** Clear everything — useful in tests. */
    public static function reset(): void
    {
        self::$instances = [];
        self::$factories = [];
        self::$binds     = [];
    }
}
