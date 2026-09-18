<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreAmanatMovementRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE) && $this->validateRlsContext();
    }
    public function rules(): array
    {
        return [
            'business_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_account_id' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
