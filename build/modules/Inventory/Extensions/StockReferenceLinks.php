<?php

namespace App\Modules\Inventory\Extensions;

use App\Modules\Inventory\Models\StockMovement;
use App\Support\Extensions\HasHooks;

/**
 * How a stock movement's source is named and linked on the movements page, and how
 * its status reads on the stock page, for documents that belong to a module.
 *
 * Source hooks run at two points of core's own order: "early" ones before the bill
 * check, "late" ones after it (HasHooks). Each is
 * fn (?string $referenceType, ?string $transactionType, StockMovement, string $base): ?array{label, href}.
 * Status hooks are fn (StockMovement): ?string.
 */
class StockReferenceLinks
{
    use HasHooks;

    /** @var array<string, callable> */
    private static array $early = [];

    /** @var array<string, callable> */
    private static array $statuses = [];

    public static function extendEarly(string $key, callable $fn): void
    {
        self::$early[$key] = $fn;
    }

    public static function extendStatus(string $key, callable $fn): void
    {
        self::$statuses[$key] = $fn;
    }

    public static function flushHooks(): void
    {
        self::$hooks = [];
        self::$early = [];
        self::$statuses = [];
    }

    /** @return array{label: string, href: ?string}|null */
    public static function early(?string $referenceType, ?string $transactionType, StockMovement $movement, string $base): ?array
    {
        return self::first(self::$early, $referenceType, $transactionType, $movement, $base);
    }

    /** @return array{label: string, href: ?string}|null */
    public static function for(?string $referenceType, ?string $transactionType, StockMovement $movement, string $base): ?array
    {
        return self::first(self::hooks(), $referenceType, $transactionType, $movement, $base);
    }

    public static function status(StockMovement $movement): ?string
    {
        foreach (self::$statuses as $hook) {
            if (($label = $hook($movement)) !== null) {
                return $label;
            }
        }

        return null;
    }

    private static function first(array $hooks, ?string $ref, ?string $txn, StockMovement $movement, string $base): ?array
    {
        foreach ($hooks as $hook) {
            if (($link = $hook($ref, $txn, $movement, $base)) !== null) {
                return $link;
            }
        }

        return null;
    }
}
