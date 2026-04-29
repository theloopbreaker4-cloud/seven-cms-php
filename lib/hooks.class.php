<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Hooks — standardized lifecycle event names dispatched via Event::dispatch.
 *
 * Repositories/models call:
 *   Hooks::fire('beforeCreate', 'page', $data);
 *   Hooks::fire('afterCreate',  'page', $page);
 *
 * Listeners subscribe with:
 *   Event::listen('page.beforeCreate', function ($payload) { ... });
 *
 * The convention is "{entity}.{event}" — events that are entity-agnostic
 * (e.g. "auth.login") still flow through Event::dispatch directly.
 */
class Hooks
{
    public const BEFORE_CREATE = 'beforeCreate';
    public const AFTER_CREATE  = 'afterCreate';
    public const BEFORE_UPDATE = 'beforeUpdate';
    public const AFTER_UPDATE  = 'afterUpdate';
    public const BEFORE_DELETE = 'beforeDelete';
    public const AFTER_DELETE  = 'afterDelete';

    /** Fire a lifecycle event scoped to an entity. */
    public static function fire(string $event, string $entity, $payload = null): void
    {
        if (!class_exists('Event')) return;
        Event::dispatch("{$entity}.{$event}", $payload);
        Event::dispatch("any.{$event}",       ['entity' => $entity, 'payload' => $payload]);
    }

    /**
     * Convenience for "before" hooks that may want to short-circuit the operation.
     * Listeners can throw a HookAbortException to stop the action.
     */
    public static function fireOrAbort(string $event, string $entity, $payload = null): bool
    {
        try {
            self::fire($event, $entity, $payload);
            return true;
        } catch (HookAbortException $e) {
            Logger::channel('app')->info('Hook aborted operation', [
                'event' => "{$entity}.{$event}", 'reason' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

class HookAbortException extends \RuntimeException {}
