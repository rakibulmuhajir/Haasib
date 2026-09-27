<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class SetAmanatOpeningRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE) && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'opening_kind' => ['required', 'in:holds,owes'],
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'opening_date' => ['nullable', 'date'],
        ];
    }
}
