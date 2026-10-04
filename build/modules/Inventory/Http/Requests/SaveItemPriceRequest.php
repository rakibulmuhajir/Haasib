<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SaveItemPriceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::ITEM_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'effective_date' => 'required|date',
            'sale_price' => 'required|numeric|min:0|max:999999999999',
            'purchase_price' => 'nullable|numeric|min:0|max:999999999999',
            'notes' => 'nullable|string|max:255',
        ];
    }
}
