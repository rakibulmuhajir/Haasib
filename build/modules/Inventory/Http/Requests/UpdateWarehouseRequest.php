<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdateWarehouseRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::WAREHOUSE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|size:2',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ];
    }
}
