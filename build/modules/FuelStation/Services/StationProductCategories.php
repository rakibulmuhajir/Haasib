<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\Inventory\Models\ItemCategory;
use Illuminate\Support\Str;

/**
 * The two product categories every fuel station starts with, "Fuel" and "Lubricant", filed by the
 * type of product. A company that already has a category by that name keeps and reuses it.
 */
class StationProductCategories
{
    public const FUEL = 'Fuel';
    public const LUBRICANT = 'Lubricant';

    public function ensureDefaults(string $companyId, ?string $userId = null): void
    {
        $this->ensure($companyId, self::FUEL, $userId);
        $this->ensure($companyId, self::LUBRICANT, $userId);
    }

    /** The category id for a product type (fuel / lubricant), or null for any other type. */
    public function idForType(string $companyId, string $type, ?string $userId = null): ?string
    {
        return match ($type) {
            'fuel' => $this->ensure($companyId, self::FUEL, $userId),
            'lubricant' => $this->ensure($companyId, self::LUBRICANT, $userId),
            default => null,
        };
    }

    public function ensure(string $companyId, string $name, ?string $userId = null): string
    {
        $existing = ItemCategory::where('company_id', $companyId)
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->first();
        if ($existing) {
            return $existing->id;
        }

        $base = Str::upper(Str::slug($name, '_'));
        $code = $base;
        for ($n = 1; ItemCategory::where('company_id', $companyId)->where('code', $code)->exists(); $n++) {
            $code = $base.'_'.$n;
        }

        return ItemCategory::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'sort_order' => $name === self::FUEL ? 1 : 2,
            'created_by_user_id' => $userId,
        ])->id;
    }
}
