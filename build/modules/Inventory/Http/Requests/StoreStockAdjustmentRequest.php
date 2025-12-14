<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreStockAdjustmentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::STOCK_ADJUST)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => 'required|uuid|exists:inv.warehouses,id',
            'item_id' => 'required|uuid|exists:inv.items,id',
            'quantity' => 'required|numeric|not_in:0',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'movement_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.not_in' => 'The adjustment quantity cannot be zero.',
        ];
    }
}
