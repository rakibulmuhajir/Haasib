<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ApplyPostCloseDiscountRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::DAILY_CLOSE_CREATE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|uuid',
            'litres' => 'nullable|numeric|min:0.01',
            'discount_amount' => 'required|numeric|min:0.01',
        ];
    }
}
