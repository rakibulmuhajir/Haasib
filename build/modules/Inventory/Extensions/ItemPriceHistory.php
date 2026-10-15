<?php

namespace App\Modules\Inventory\Extensions;

use App\Modules\Inventory\Models\Item;

/**
 * Where a module changes how a product's price history behaves. Three kinds of
 * hook, each keyed by module so registering twice replaces rather than doubles:
 *
 *  - timeline:   fn (Item, companyId, companySlug, today): ?array  rows that
 *                replace the sale-price rows, as ['replaces_sale_prices' => bool, 'rows' => array]
 *  - guard:      fn (Item, companyId, date): void  throws to refuse a price save
 *  - lockedMonths: fn (companyId): string[]  'Y-m' months whose prices are settled
 *  - correction types: transaction types whose metadata carries per-item unit costs
 */
class ItemPriceHistory
{
    /** @var array<string, callable> */
    private static array $timeline = [];

    /** @var array<string, callable> */
    private static array $guards = [];

    /** @var array<string, callable> */
    private static array $lockedMonths = [];

    /** @var array<string, array<int, string>> */
    private static array $correctionTypes = [];

    public static function extendTimeline(string $key, callable $fn): void
    {
        self::$timeline[$key] = $fn;
    }

    public static function extendGuard(string $key, callable $fn): void
    {
        self::$guards[$key] = $fn;
    }

    public static function extendLockedMonths(string $key, callable $fn): void
    {
        self::$lockedMonths[$key] = $fn;
    }

    public static function extendCorrectionTypes(string $key, array $transactionTypes): void
    {
        self::$correctionTypes[$key] = $transactionTypes;
    }

    public static function flushHooks(): void
    {
        self::$timeline = self::$guards = self::$lockedMonths = self::$correctionTypes = [];
    }

    /** @return array{replaces_sale_prices: bool, rows: array}|null first module that answers wins */
    public function timelineRows(Item $item, string $companyId, string $companySlug, string $today): ?array
    {
        foreach (self::$timeline as $hook) {
            if (($result = $hook($item, $companyId, $companySlug, $today)) !== null) {
                return $result;
            }
        }

        return null;
    }

    /** Throws when a module refuses to price this item. */
    public function assertCanPrice(Item $item, string $companyId, string $date): void
    {
        foreach (self::$guards as $hook) {
            $hook($item, $companyId, $date);
        }
    }

    /** @return array<int, string> */
    public function lockedMonths(string $companyId): array
    {
        $months = [];
        foreach (self::$lockedMonths as $hook) {
            $months = array_merge($months, $hook($companyId));
        }

        return array_values(array_unique($months));
    }

    /** @return array<int, string> */
    public function correctionTypes(): array
    {
        return array_values(array_unique(array_merge([], ...array_values(self::$correctionTypes))));
    }
}
