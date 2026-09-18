<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Validation\Rule;

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
            'warehouse_id' => ['required', 'uuid', Rule::exists(Warehouse::class, 'id')],
            'item_id' => ['required', 'uuid', Rule::exists(Item::class, 'id')],
            'quantity' => 'required|numeric|not_in:0',
            'unit_cost' => 'nullable|numeric|min:0',
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
