<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreStockTransferRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::STOCK_TRANSFER)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'source_warehouse_id' => 'required|uuid|exists:inv.warehouses,id|different:destination_warehouse_id',
            'destination_warehouse_id' => 'required|uuid|exists:inv.warehouses,id',
            'item_id' => 'required|uuid|exists:inv.items,id',
            'quantity' => 'required|numeric|gt:0',
            'notes' => 'nullable|string',
            'movement_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'source_warehouse_id.different' => 'Source and destination warehouses must be different.',
            'quantity.gt' => 'Transfer quantity must be greater than zero.',
        ];
    }
}
