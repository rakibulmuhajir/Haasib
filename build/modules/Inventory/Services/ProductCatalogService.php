<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Item;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCatalogService
{
    /**
     * Create or update the canonical inventory product record.
     *
     * Fuel station setup, onboarding, tank setup, and the inventory item form
     * should all pass through this method instead of writing inv.items directly.
     *
     * @param array<string, mixed> $data
     */
    public function save(array $data): Item
    {
        $companyId = (string) ($data['company_id'] ?? '');
        if ($companyId === '') {
            throw new \InvalidArgumentException('company_id is required to save a product.');
        }

        $userId = $data['user_id'] ?? $data['created_by_user_id'] ?? $data['updated_by_user_id'] ?? null;
        $sku = trim((string) ($data['sku'] ?? ''));

        $item = $data['item'] ?? null;
        $providedFuelCategory = array_key_exists('fuel_category', $data)
            ? $this->normalizeFuelCategory($data['fuel_category'])
            : null;
        $fuelCategory = $providedFuelCategory;
        if (! $item instanceof Item) {
            $item = $this->findExisting(
                $companyId,
                $fuelCategory,
                $sku !== '' ? $sku : null,
                isset($data['id']) ? (string) $data['id'] : null
            );
        }
        if (! array_key_exists('fuel_category', $data)) {
            $fuelCategory = $this->normalizeFuelCategory($item?->fuel_category)
                ?? $this->inferFuelCategory($sku !== '' ? $sku : $item?->sku, $data['name'] ?? $item?->name ?? null);
        }

        if (! $item && $sku === '') {
            $sku = $this->generateSku(
                $companyId,
                (string) ($data['type'] ?? $data['item_type'] ?? 'product'),
                $fuelCategory,
                $data['reserved_skus'] ?? []
            );
        }

        if (! $item && $sku !== '' && $this->skuExists($companyId, $sku)) {
            throw ValidationException::withMessages([
                'sku' => 'A product with this SKU already exists.',
            ]);
        }

        if ($item && $sku !== '' && $sku !== $item->sku && $this->skuExists($companyId, $sku, $item->id)) {
            throw ValidationException::withMessages([
                'sku' => 'A product with this SKU already exists.',
            ]);
        }

        $trackInventory = array_key_exists('track_inventory', $data)
            ? (bool) $data['track_inventory']
            : (bool) ($item?->track_inventory ?? true);

        $itemType = (string) ($data['item_type'] ?? $item?->item_type ?? ($trackInventory ? 'product' : 'non_inventory'));
        if (in_array($itemType, ['service', 'non_inventory'], true)) {
            $trackInventory = false;
        }

        $costPrice = $this->decimalValue($data['cost_price'] ?? $data['purchase_rate'] ?? null, (float) ($item?->cost_price ?? 0));
        $salePrice = $this->decimalValue($data['selling_price'] ?? $data['sale_rate'] ?? null, (float) ($item?->selling_price ?? 0));
        $avgCost = $this->decimalValue($data['avg_cost'] ?? null, (float) ($item?->avg_cost ?? $costPrice));

        $payload = [
            'category_id' => $data['category_id'] ?? null,
            'sku' => $sku !== '' ? $sku : ($item?->sku ?? null),
            'name' => trim((string) ($data['name'] ?? $item?->name ?? '')),
            'description' => $data['description'] ?? null,
            'item_type' => $itemType,
            'fuel_category' => $fuelCategory,
            'unit_of_measure' => trim((string) ($data['unit_of_measure'] ?? $item?->unit_of_measure ?? 'unit')),
            'track_inventory' => $trackInventory,
            'delivery_mode' => $trackInventory
                ? (string) ($data['delivery_mode'] ?? $item?->delivery_mode ?? 'requires_receiving')
                : 'immediate',
            'is_purchasable' => array_key_exists('is_purchasable', $data) ? (bool) $data['is_purchasable'] : (bool) ($item?->is_purchasable ?? true),
            'is_sellable' => array_key_exists('is_sellable', $data) ? (bool) $data['is_sellable'] : (bool) ($item?->is_sellable ?? true),
            'cost_price' => $costPrice,
            'avg_cost' => $avgCost,
            'selling_price' => $salePrice,
            'currency' => strtoupper((string) ($data['currency'] ?? $item?->currency ?? 'PKR')),
            'tax_rate_id' => $data['tax_rate_id'] ?? null,
            'income_account_id' => $data['income_account_id'] ?? $item?->income_account_id,
            'expense_account_id' => $data['expense_account_id'] ?? $item?->expense_account_id,
            'asset_account_id' => $data['asset_account_id'] ?? $item?->asset_account_id,
            'reorder_point' => $this->decimalValue($data['reorder_point'] ?? $item?->reorder_point ?? null, 0),
            'reorder_quantity' => $this->decimalValue($data['reorder_quantity'] ?? $item?->reorder_quantity ?? null, 0),
            'weight' => $data['weight'] ?? null,
            'weight_unit' => $data['weight_unit'] ?? null,
            'dimensions' => $data['dimensions'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'manufacturer' => $data['manufacturer'] ?? null,
            'brand' => $data['brand'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : (bool) ($item?->is_active ?? true),
            'notes' => $data['notes'] ?? null,
        ];

        if ($payload['name'] === '') {
            throw ValidationException::withMessages([
                'name' => 'Product name is required.',
            ]);
        }

        if (! $payload['sku']) {
            throw ValidationException::withMessages([
                'sku' => 'Product SKU is required.',
            ]);
        }

        if ($item) {
            $item->update($payload + [
                'updated_by_user_id' => $userId,
            ]);
        } else {
            $item = Item::create($payload + [
                'company_id' => $companyId,
                'created_by_user_id' => $userId,
            ]);
        }

        $this->syncOptionalPriceColumns($item->id, $costPrice, $salePrice, $userId);

        return $item->refresh();
    }

    public function normalizeFuelCategory(?string $category): ?string
    {
        if ($category === null || trim($category) === '') {
            return null;
        }

        return $category === 'hi_octane' ? 'high_octane' : $category;
    }

    public function inferFuelCategory(?string $sku, ?string $name = null): ?string
    {
        $value = Str::lower(trim((string) $sku) . ' ' . trim((string) $name));

        return match (true) {
            str_contains($value, 'diesel') || str_contains($value, 'dsl') => 'diesel',
            str_contains($value, 'hi-octane') || str_contains($value, 'high_octane') || str_contains($value, 'hoc') => 'high_octane',
            str_contains($value, 'petrol') || str_contains($value, 'fuel-pet') => 'petrol',
            str_contains($value, 'lubricant') || str_contains($value, 'lub') => 'lubricant',
            default => null,
        };
    }

    public function findExisting(string $companyId, ?string $fuelCategory = null, ?string $sku = null, ?string $id = null): ?Item
    {
        if ($id) {
            $item = Item::where('company_id', $companyId)->where('id', $id)->first();
            if ($item) {
                return $item;
            }
        }

        $fuelCategory = $this->normalizeFuelCategory($fuelCategory);
        if ($fuelCategory !== null) {
            $query = Item::where('company_id', $companyId);
            if ($fuelCategory === 'high_octane') {
                $query->whereIn('fuel_category', ['high_octane', 'hi_octane']);
            } else {
                $query->where('fuel_category', $fuelCategory);
            }

            $item = $query->first();
            if ($item) {
                return $item;
            }
        }

        if ($sku !== null && trim($sku) !== '') {
            return Item::where('company_id', $companyId)
                ->where('sku', trim($sku))
                ->first();
        }

        return null;
    }

    /**
     * @param array<int, string> $reservedSkus
     */
    public function generateSku(string $companyId, string $type, ?string $fuelCategory = null, array $reservedSkus = []): string
    {
        $fuelCategory = $this->normalizeFuelCategory($fuelCategory);
        $prefix = match ($type) {
            'fuel' => match ($fuelCategory) {
                'petrol' => 'FUEL-PET',
                'diesel' => 'FUEL-DSL',
                'high_octane' => 'FUEL-HOC',
                default => 'FUEL',
            },
            'lubricant' => $fuelCategory === 'lubricant' ? 'FUEL-LUB' : 'LUB',
            default => 'PROD',
        };

        $prefix = Str::upper(rtrim($prefix, '-')) . '-';

        for ($i = 1; $i <= 9999; $i++) {
            $sku = $prefix . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (in_array($sku, $reservedSkus, true)) {
                continue;
            }
            if (! $this->skuExists($companyId, $sku)) {
                return $sku;
            }
        }

        return $prefix . Str::upper(Str::random(6));
    }

    public function syncOptionalPriceColumns(string $itemId, float $purchaseRate, float $saleRate, ?string $userId = null): void
    {
        $columns = $this->itemPriceColumns();
        $payload = [
            'updated_at' => now(),
        ];

        if ($userId !== null) {
            $payload['updated_by_user_id'] = $userId;
        }

        if ($columns['avg_cost']) {
            $payload['avg_cost'] = $purchaseRate;
        }
        if ($columns['sale_price']) {
            $payload['sale_price'] = $saleRate;
        }
        if ($columns['selling_price']) {
            $payload['selling_price'] = $saleRate;
        }

        if (count($payload) > 1) {
            DB::table('inv.items')->where('id', $itemId)->update($payload);
        }
    }

    private function skuExists(string $companyId, string $sku, ?string $exceptItemId = null): bool
    {
        $query = Item::where('company_id', $companyId)
            ->where('sku', $sku);

        if ($exceptItemId) {
            $query->where('id', '!=', $exceptItemId);
        }

        return $query->exists();
    }

    /**
     * @return array{avg_cost: bool, sale_price: bool, selling_price: bool}
     */
    private function itemPriceColumns(): array
    {
        static $columns = null;

        if ($columns !== null) {
            return $columns;
        }

        try {
            $names = DB::table('information_schema.columns')
                ->where('table_schema', 'inv')
                ->where('table_name', 'items')
                ->whereIn('column_name', ['avg_cost', 'sale_price', 'selling_price'])
                ->pluck('column_name')
                ->all();
        } catch (\Throwable) {
            $names = ['avg_cost', 'selling_price'];
        }

        return $columns = [
            'avg_cost' => in_array('avg_cost', $names, true),
            'sale_price' => in_array('sale_price', $names, true),
            'selling_price' => in_array('selling_price', $names, true),
        ];
    }

    private function decimalValue(mixed $value, float $default): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return (float) $value;
    }
}
