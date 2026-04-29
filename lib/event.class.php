<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Lightweight synchronous event bus.
 *
 * Usage:
 *   Event::on('user.login', function(array $data) { ... });
 *   Event::on('user.login', [SomeClass::class, 'handleLogin']);
 *
 *   Event::emit('user.login', ['user' => $user]);
 *
 *   Event::off('user.login');  // remove all listeners for event
 */
class Event
{
    private static array $listeners = [];

    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    public static function off(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    public static function emit(string $event, array $data = []): void
    {
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $listener($data);
        }
    }

    public static function hasListeners(string $event): bool
    {
        return !empty(self::$listeners[$event]);
    }

    // ── Compatibility aliases ─────────────────────────────────────────
    //
    // Newer modules (Content, Ecom, PageBuilder, GraphQL, Multi-site)
    // were written against PSR-14-style names. Both styles dispatch through
    // the same listener table.

    /** Alias for on(); accepts any callable. */
    public static function listen(string $event, callable $listener): void
    {
        self::on($event, $listener);
    }

    /**
     * Dispatch any payload (object / array / scalar / null). Listeners are
     * invoked with the payload as their single argument — matching how
     * `emit()` passes its `$data` array.
     */
    public static function dispatch(string $event, $payload = null): void
    {
        foreach (self::$listeners[$event] ?? [] as $listener) {
            $listener($payload);
        }
    }
}
