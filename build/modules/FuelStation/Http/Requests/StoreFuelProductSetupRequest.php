<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreFuelProductSetupRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::FUEL_PRODUCT_SETUP)
            && $this->validateRlsContext();
    }

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
}
