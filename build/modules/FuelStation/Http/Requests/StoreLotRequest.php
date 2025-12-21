<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreLotRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVESTOR_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'investment_amount' => ['required', 'numeric', 'min:1000'],
            'deposit_date' => ['nullable', 'date'],
            'item_id' => ['nullable', 'uuid', 'exists:inv.items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'investment_amount.min' => 'Minimum investment amount is PKR 1,000.',
        ];
    }
}
