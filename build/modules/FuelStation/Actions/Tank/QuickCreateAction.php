<?php

namespace App\Modules\FuelStation\Actions\Tank;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuickCreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'product' => 'required|array',
            'product.type' => 'required|in:fuel,lubricant,other',
            'product.name' => 'required|string|max:255',
            'product.sku' => 'nullable|string|max:100',
            'product.fuel_category' => 'nullable|in:petrol,diesel,high_octane,hi_octane,lubricant',
            'product.lubricant_format' => 'nullable|in:open,packaged',
            'product.packaging' => 'nullable|in:open,packaged',
            'product.unit_of_measure' => 'nullable|string|max:50',
            'product.track_inventory' => 'nullable|boolean',
            'tank' => 'required|array',
            'tank.name' => 'required|string|max:255',
            'tank.code' => 'required|string|max:50',
            'tank.capacity' => 'required|numeric|min:1',
            'tank.low_level_alert' => 'nullable|numeric|min:0',
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

        $product = $params['product'];
        $tank = $params['tank'];
        $userId = Auth::id();
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        $type = $product['type'];
        $name = trim((string) $product['name']);
        $fuelCategory = null;
        $packaging = $product['packaging'] ?? null;
        $trackInventory = $product['track_inventory'] ?? true;
        $itemType = 'product';
        $unit = trim((string) ($product['unit_of_measure'] ?? ''));

        if ($type === 'fuel') {
            if (empty($product['fuel_category'])) {
                throw ValidationException::withMessages([
                    'product.fuel_category' => 'Select a fuel type before adding a tank.',
                ]);
            }
            $fuelCategory = $this->normalizeFuelCategory($product['fuel_category']);
            $packaging = 'open';
            $trackInventory = true;
            $unit = $unit !== '' ? $unit : 'liters';
        } elseif ($type === 'lubricant') {
            if (empty($product['lubricant_format'])) {
                throw ValidationException::withMessages([
                    'product.lubricant_format' => 'Select lubricant packaging before adding a tank.',
                ]);
            }
            $packaging = $product['lubricant_format'];
            $trackInventory = true;
            if ($packaging === 'open') {
                $fuelCategory = 'lubricant';
                $unit = $unit !== '' ? $unit : 'liters';
            } else {
                $unit = $unit !== '' ? $unit : 'bottle';
            }
        } else {
            $packaging = $packaging ?: 'packaged';
            $trackInventory = (bool) $trackInventory;
            $itemType = $trackInventory ? 'product' : 'non_inventory';
            $unit = $unit !== '' ? $unit : 'unit';
        }

        if (! $trackInventory || $packaging !== 'open') {
            throw ValidationException::withMessages([
                'tank' => 'Tanks can only be created for open/bulk inventory items.',
            ]);
        }

        $skuInput = trim((string) ($product['sku'] ?? ''));
        $sku = $skuInput !== '' ? $skuInput : $this->generateSku($company->id, $type, $fuelCategory);

        $item = $this->resolveExistingItem($company->id, $fuelCategory, $sku);
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
            'cost_price' => 0,
            'avg_cost' => 0,
            'is_active' => true,
        ];

        $tankRecord = DB::transaction(function () use ($company, $tank, $sku, $payload, $item, $userId) {
            if ($item) {
                $item->update($payload + [
                    'updated_by_user_id' => $userId,
                ]);
                $itemId = $item->id;
            } else {
                $item = Item::create($payload + [
                    'company_id' => $company->id,
                    'sku' => $sku,
                    'created_by_user_id' => $userId,
                ]);
                $itemId = $item->id;
            }

            $code = trim((string) $tank['code']);
            $existingTank = Warehouse::where('company_id', $company->id)
                ->where('code', $code)
                ->whereNull('deleted_at')
                ->exists();

            if ($existingTank) {
                throw ValidationException::withMessages([
                    'tank.code' => 'Tank code already exists.',
                ]);
            }

            $tankRecord = Warehouse::create([
                'company_id' => $company->id,
                'code' => $code,
                'name' => trim((string) $tank['name']),
                'warehouse_type' => 'tank',
                'capacity' => $tank['capacity'],
                'low_level_alert' => $tank['low_level_alert'] ?? null,
                'linked_item_id' => $itemId,
                'is_active' => true,
                'created_by_user_id' => $userId,
            ]);

            return [$tankRecord, $item];
        });

        [$tankRecord, $itemRecord] = $tankRecord;

        return [
            'message' => 'Tank created successfully.',
            'data' => [
                'tank' => [
                    'id' => $tankRecord->id,
                    'name' => $tankRecord->name,
                    'code' => $tankRecord->code,
                    'capacity' => $tankRecord->capacity,
                    'linked_item_id' => $tankRecord->linked_item_id,
                ],
                'item' => [
                    'id' => $itemRecord->id,
                    'sku' => $itemRecord->sku,
                    'name' => $itemRecord->name,
                    'fuel_category' => $itemRecord->fuel_category,
                ],
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

    private function resolveExistingItem(string $companyId, ?string $fuelCategory, string $sku): ?Item
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

    private function generateSku(string $companyId, string $type, ?string $fuelCategory): string
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
            $exists = Item::where('company_id', $companyId)->where('sku', $sku)->exists();
            if (! $exists) {
                return $sku;
            }
        }

        return $prefix . Str::upper(Str::random(6));
    }
}
