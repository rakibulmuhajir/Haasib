<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreFuelTankQuickCreateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::FUEL_PRODUCT_SETUP)
            && $this->validateRlsContext();
    }

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
}
