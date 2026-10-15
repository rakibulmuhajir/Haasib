<?php

namespace App\Support\Extensions;

/**
 * A keyed static hook list for a core extension point. A module registers from
 * its provider's boot(); the key (normally the module key) makes registration
 * idempotent, because providers boot again on every app instance (Octane, each
 * test) while static state survives.
 */
trait HasHooks
{
    /** @var array<string, callable> */
    private static array $hooks = [];

    public static function extend(string $key, callable $hook): void
    {
        self::$hooks[$key] = $hook;
    }

    public static function flushHooks(): void
    {
        self::$hooks = [];
    }

    /** @return array<string, callable> */
    public static function hooks(): array
    {
        return self::$hooks;
    }
}
