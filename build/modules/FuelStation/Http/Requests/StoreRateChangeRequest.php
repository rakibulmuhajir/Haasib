<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreRateChangeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::FUEL_RATE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'uuid', 'exists:inv.items,id'],
            'effective_date' => ['required', 'date'],
            'purchase_rate' => ['required', 'numeric', 'min:0'],
            'sale_rate' => ['required', 'numeric', 'min:0'],
            'stock_quantity_at_change' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
