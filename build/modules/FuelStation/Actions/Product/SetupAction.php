<?php

namespace App\Modules\FuelStation\Actions\Product;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\ItemCategory;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SetupAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'effective_date' => 'required|date',
            'products' => 'required|array|min:1',
            'products.*.type' => 'required|in:fuel,lubricant,other',
            'products.*.name' => 'required|string|max:255',
            'products.*.sku' => 'nullable|string|max:100',
            'products.*.fuel_category' => 'nullable|in:petrol,diesel,high_octane,hi_octane,lubricant',
            'products.*.lubricant_format' => 'nullable|in:open,packaged',
            'products.*.packaging' => 'nullable|in:open,packaged',
            'products.*.category_name' => 'nullable|string|max:255',
            'products.*.unit_of_measure' => 'nullable|string|max:50',
            'products.*.track_inventory' => 'nullable|boolean',
            'products.*.purchase_rate' => 'required|numeric|min:0',
            'products.*.sale_rate' => 'required|numeric|min:0',
            'products.*.opening_quantity' => 'nullable|numeric|min:0',
            'products.*.tank_id' => 'nullable|uuid',
            'products.*.new_tank' => 'nullable|array',
            'products.*.new_tank.name' => 'required_with:products.*.new_tank|string|max:255',
            'products.*.new_tank.code' => 'required_with:products.*.new_tank|string|max:50',
            'products.*.new_tank.capacity' => 'required_with:products.*.new_tank|numeric|min:1',
            'products.*.new_tank.low_level_alert' => 'nullable|numeric|min:0',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::FUEL_PRODUCT_SETUP;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        CompanyContext::setContext($company);

        $effectiveDate = $params['effective_date'];
        $products = $params['products'];
        $userId = Auth::id();
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));
        $priceColumns = $this->resolveItemPriceColumns();

        $created = [];
        $updated = [];
        $movements = 0;
        $tanksCreated = 0;
        $categoriesCreated = 0;

        $seenSkus = [];

        DB::transaction(function () use (
            $company,
            $products,
            $effectiveDate,
            $userId,
            $baseCurrency,
            $priceColumns,
            &$created,
            &$updated,
            &$movements,
            &$tanksCreated,
            &$categoriesCreated,
            &$seenSkus
        ) {
            foreach ($products as $index => $product) {
                $type = $product['type'];
                $name = trim((string) $product['name']);
                $purchaseRate = (float) $product['purchase_rate'];
                $saleRate = (float) $product['sale_rate'];
                $openingQty = (float) ($product['opening_quantity'] ?? 0);

                if ($type === 'fuel' && empty($product['fuel_category'])) {
                    throw ValidationException::withMessages([
                        "products.{$index}.fuel_category" => 'Select a fuel type.',
                    ]);
                }

                if ($type === 'lubricant' && empty($product['lubricant_format'])) {
                    throw ValidationException::withMessages([
                        "products.{$index}.lubricant_format" => 'Select lubricant packaging.',
                    ]);
                }

                if ($type === 'other' && empty($product['packaging'])) {
                    throw ValidationException::withMessages([
                        "products.{$index}.packaging" => 'Select packaging.',
                    ]);
                }

                $fuelCategory = null;
                $packaging = $product['packaging'] ?? null;
                $trackInventory = $product['track_inventory'] ?? true;
                $itemType = 'product';
                $unit = trim((string) ($product['unit_of_measure'] ?? ''));

                if ($type === 'fuel') {
                    $fuelCategory = $this->normalizeFuelCategory($product['fuel_category']);
                    $packaging = 'open';
                    $trackInventory = true;
                    $unit = $unit !== '' ? $unit : 'liters';
                } elseif ($type === 'lubricant') {
                    $packaging = $product['lubricant_format'];
                    $trackInventory = true;
                    if ($packaging === 'open') {
                        $fuelCategory = 'lubricant';
                        $unit = $unit !== '' ? $unit : 'liters';
                    } else {
                        $unit = $unit !== '' ? $unit : 'bottle';
                    }
                } else {
                    $trackInventory = (bool) $trackInventory;
                    $itemType = $trackInventory ? 'product' : 'non_inventory';
                    $unit = $unit !== '' ? $unit : 'unit';
                }

                if (! $trackInventory && $openingQty > 0) {
                    throw ValidationException::withMessages([
                        "products.{$index}.opening_quantity" => 'Opening stock requires inventory tracking.',
                    ]);
                }

                $categoryName = trim((string) ($product['category_name'] ?? ''));
                $categoryId = null;
                if ($type === 'other' && $categoryName !== '') {
                    $categoryId = $this->resolveCategoryId($company->id, $categoryName, $userId, $categoriesCreated);
                }

                $skuInput = trim((string) ($product['sku'] ?? ''));
                $sku = $skuInput !== '' ? $skuInput : $this->generateSku($company->id, $type, $fuelCategory, $seenSkus);

                if (in_array($sku, $seenSkus, true)) {
                    throw ValidationException::withMessages([
                        "products.{$index}.sku" => 'Duplicate SKU in this batch.',
                    ]);
                }
                $seenSkus[] = $sku;

                $item = $this->resolveExistingItem($company->id, $type, $fuelCategory, $sku);
                $payload = [
                    'name' => $name,
                    'item_type' => $itemType,
                    'fuel_category' => $fuelCategory,
                    'unit_of_measure' => $unit,
                    'track_inventory' => $trackInventory,
                    'delivery_mode' => $trackInventory ? 'requires_receiving' : 'immediate',
                    'is_purchasable' => true,
                    'is_sellable' => true,
                    'currency' => $baseCurrency,
                    'cost_price' => $purchaseRate,
                    'avg_cost' => $purchaseRate,
                    'category_id' => $categoryId,
                    'is_active' => true,
                ];

                if ($item) {
                    $item->update($payload + [
                        'updated_by_user_id' => $userId,
                    ]);
                    $itemId = $item->id;
                    $updated[] = $name;
                } else {
                    $item = Item::create($payload + [
                        'company_id' => $company->id,
                        'sku' => $sku,
                        'created_by_user_id' => $userId,
                    ]);
                    $itemId = $item->id;
                    $created[] = $name;
                }

                $this->updateItemPrices($itemId, $purchaseRate, $saleRate, $userId, $priceColumns);

                if ($fuelCategory !== null) {
                    RateChange::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'item_id' => $itemId,
                            'effective_date' => $effectiveDate,
                        ],
                        [
                            'purchase_rate' => $purchaseRate,
                            'sale_rate' => $saleRate,
                            'created_by_user_id' => $userId,
                        ]
                    );
                }

                if (! $trackInventory) {
                    continue;
                }

                if ($packaging === 'open') {
                    Log::info('Fuel product setup: resolving tank', [
                        'company_id' => $company->id,
                        'item_id' => $itemId,
                        'product_index' => $index,
                        'tank_id' => $product['tank_id'] ?? null,
                        'new_tank' => $product['new_tank'] ?? null,
                        'opening_quantity' => $openingQty,
                    ]);
                    $tank = $this->resolveTank($company->id, $itemId, $product, $userId, $tanksCreated, $index, $openingQty);

                    if ($tank && $openingQty > 0) {
                        $this->recordOpeningTankBalance(
                            $company->id,
                            $tank->id,
                            $itemId,
                            $effectiveDate,
                            $openingQty,
                            $purchaseRate,
                            $userId
                        );
                        $movements++;
                    }
                } elseif ($openingQty > 0) {
                    $warehouse = $this->resolveStandardWarehouse($company->id, $userId);
                    $this->recordOpeningStockMovement(
                        $company->id,
                        $warehouse->id,
                        $itemId,
                        $effectiveDate,
                        $openingQty,
                        $purchaseRate,
                        $userId,
                        'Opening balance from product setup'
                    );
                    $movements++;
                }
            }
        });

        return [
            'message' => 'Products saved successfully.',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'movements' => $movements,
                'tanks_created' => $tanksCreated,
                'categories_created' => $categoriesCreated,
            ],
        ];
    }

    private function normalizeFuelCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        return $category === 'hi_octane' ? 'high_octane' : $category;
    }

    private function resolveExistingItem(string $companyId, string $type, ?string $fuelCategory, string $sku): ?Item
    {
        if ($fuelCategory !== null) {
            $query = Item::where('company_id', $companyId);
            if ($fuelCategory === 'high_octane') {
                $query->whereIn('fuel_category', ['high_octane', 'hi_octane']);
            } else {
                $query->where('fuel_category', $fuelCategory);
            }

            return $query->first();
        }

        return Item::where('company_id', $companyId)
            ->where('sku', $sku)
            ->first();
    }

    private function resolveItemPriceColumns(): array
    {
        try {
            $columns = DB::table('information_schema.columns')
                ->where('table_schema', 'inv')
                ->where('table_name', 'items')
                ->whereIn('column_name', ['sale_price', 'selling_price'])
                ->pluck('column_name')
                ->all();
        } catch (\Throwable $e) {
            return [
                'sale_price' => false,
                'selling_price' => false,
            ];
        }

        return [
            'sale_price' => in_array('sale_price', $columns, true),
            'selling_price' => in_array('selling_price', $columns, true),
        ];
    }

    private function updateItemPrices(string $itemId, float $purchaseRate, float $saleRate, ?string $userId, array $priceColumns): void
    {
        $payload = [
            'avg_cost' => $purchaseRate,
            'updated_by_user_id' => $userId,
            'updated_at' => now(),
        ];

        if (! empty($priceColumns['sale_price'])) {
            $payload['sale_price'] = $saleRate;
        }

        if (! empty($priceColumns['selling_price'])) {
            $payload['selling_price'] = $saleRate;
        }

        if (! empty($payload)) {
            DB::table('inv.items')->where('id', $itemId)->update($payload);
        }
    }

    private function resolveCategoryId(string $companyId, string $name, ?string $userId, int &$createdCount): string
    {
        $existing = ItemCategory::where('company_id', $companyId)
            ->whereRaw('lower(name) = ?', [Str::lower($name)])
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        $codeBase = Str::upper(Str::slug($name, '_'));
        $codeBase = $codeBase === '' ? 'CATEGORY' : $codeBase;
        $codeBase = substr($codeBase, 0, 45);
        $code = $codeBase;
        $suffix = 1;

        while (ItemCategory::where('company_id', $companyId)->where('code', $code)->whereNull('deleted_at')->exists()) {
            $code = substr($codeBase, 0, 45) . '_' . $suffix;
            $suffix++;
        }

        $category = ItemCategory::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $name,
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
            'created_by_user_id' => $userId,
        ]);

        $createdCount++;

        return $category->id;
    }

    private function generateSku(string $companyId, string $type, ?string $fuelCategory, array $seenSkus): string
    {
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

        $prefix = Str::upper($prefix);
        $prefix = rtrim($prefix, '-') . '-';

        for ($i = 1; $i <= 9999; $i++) {
            $sku = $prefix . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            if (in_array($sku, $seenSkus, true)) {
                continue;
            }
            $exists = Item::where('company_id', $companyId)->where('sku', $sku)->exists();
            if (! $exists) {
                return $sku;
            }
        }

        return $prefix . Str::upper(Str::random(6));
    }

    private function resolveTank(
        string $companyId,
        string $itemId,
        array $product,
        ?string $userId,
        int &$createdCount,
        int $index,
        float $openingQty
    ): ?Warehouse {
        $tankId = $product['tank_id'] ?? null;
        $newTank = $product['new_tank'] ?? null;

        if ($tankId) {
            $tank = Warehouse::where('company_id', $companyId)
                ->where('id', $tankId)
                ->where('warehouse_type', 'tank')
                ->first();

            if (! $tank) {
                Log::warning('Fuel product setup: selected tank not found', [
                    'company_id' => $companyId,
                    'item_id' => $itemId,
                    'tank_id' => $tankId,
                    'product_index' => $index,
                ]);
                throw ValidationException::withMessages([
                    "products.{$index}.tank_id" => 'Selected tank is invalid.',
                ]);
            }

            if ($tank->linked_item_id && $tank->linked_item_id !== $itemId) {
                Log::warning('Fuel product setup: selected tank linked to different item', [
                    'company_id' => $companyId,
                    'item_id' => $itemId,
                    'tank_id' => $tankId,
                    'linked_item_id' => $tank->linked_item_id,
                    'product_index' => $index,
                ]);
                throw ValidationException::withMessages([
                    "products.{$index}.tank_id" => 'Selected tank is linked to another product.',
                ]);
            }

            if (! $tank->linked_item_id) {
                $tank->update(['linked_item_id' => $itemId]);
                Log::info('Fuel product setup: linked existing tank to item', [
                    'company_id' => $companyId,
                    'item_id' => $itemId,
                    'tank_id' => $tank->id,
                ]);
            }

            return $tank;
        }

        if (! $newTank) {
            if ($openingQty > 0) {
                Log::warning('Fuel product setup: opening stock without tank', [
                    'company_id' => $companyId,
                    'item_id' => $itemId,
                    'product_index' => $index,
                    'opening_quantity' => $openingQty,
                ]);
                throw ValidationException::withMessages([
                    "products.{$index}.tank_id" => 'Select or add a tank for opening stock.',
                ]);
            }

            return null;
        }

        $code = trim((string) $newTank['code']);
        $existing = Warehouse::where('company_id', $companyId)
            ->where('code', $code)
            ->whereNull('deleted_at')
            ->exists();

        if ($existing) {
            Log::warning('Fuel product setup: tank code already exists', [
                'company_id' => $companyId,
                'item_id' => $itemId,
                'tank_code' => $code,
                'product_index' => $index,
            ]);
            throw ValidationException::withMessages([
                "products.{$index}.new_tank.code" => 'Tank code already exists.',
            ]);
        }

        $tank = Warehouse::create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => trim((string) $newTank['name']),
            'warehouse_type' => 'tank',
            'capacity' => $newTank['capacity'],
            'low_level_alert' => $newTank['low_level_alert'] ?? null,
            'linked_item_id' => $itemId,
            'is_active' => true,
            'created_by_user_id' => $userId,
        ]);

        $createdCount++;
        Log::info('Fuel product setup: tank created', [
            'company_id' => $companyId,
            'item_id' => $itemId,
            'tank_id' => $tank->id,
            'tank_code' => $tank->code,
        ]);

        return $tank;
    }

    private function resolveStandardWarehouse(string $companyId, ?string $userId): Warehouse
    {
        $warehouse = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'standard')
            ->where('is_active', true)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->first();

        if ($warehouse) {
            return $warehouse;
        }

        return Warehouse::create([
            'company_id' => $companyId,
            'code' => 'WH-MAIN',
            'name' => 'Main Warehouse',
            'warehouse_type' => 'standard',
            'is_active' => true,
            'created_by_user_id' => $userId,
        ]);
    }

    private function recordOpeningTankBalance(
        string $companyId,
        string $tankId,
        string $itemId,
        string $date,
        float $liters,
        float $unitCost,
        ?string $userId
    ): void {
        $reading = TankReading::where('company_id', $companyId)
            ->where('tank_id', $tankId)
            ->where('reading_type', 'opening')
            ->where('reading_date', $date)
            ->first();

        $payload = [
            'tank_id' => $tankId,
            'item_id' => $itemId,
            'reading_date' => $date,
            'reading_type' => 'opening',
            'stick_reading' => null,
            'dip_measurement_liters' => $liters,
            'system_calculated_liters' => $liters,
            'status' => 'posted',
            'notes' => 'Opening balance from product setup',
            'recorded_by_user_id' => $userId,
        ];

        if ($reading) {
            $reading->update($payload + [
                'updated_by_user_id' => $userId,
            ]);
        } else {
            TankReading::create($payload + [
                'company_id' => $companyId,
                'created_by_user_id' => $userId,
            ]);
        }

        $this->recordOpeningStockMovement(
            $companyId,
            $tankId,
            $itemId,
            $date,
            $liters,
            $unitCost,
            $userId,
            'Opening fuel balance from product setup'
        );
    }

    private function recordOpeningStockMovement(
        string $companyId,
        string $warehouseId,
        string $itemId,
        string $date,
        float $quantity,
        float $unitCost,
        ?string $userId,
        string $notes
    ): void {
        $movement = StockMovement::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('movement_type', 'opening')
            ->where('movement_date', $date)
            ->where('notes', $notes)
            ->first();

        $payload = [
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
            'movement_date' => $date,
        ];

        if ($movement) {
            $movement->update($payload);
            return;
        }

        StockMovement::create($payload + [
            'company_id' => $companyId,
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'movement_type' => 'opening',
            'notes' => $notes,
            'created_by_user_id' => $userId,
        ]);
    }
}
