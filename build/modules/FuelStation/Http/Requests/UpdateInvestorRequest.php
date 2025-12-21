<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdateInvestorRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVESTOR_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'cnic' => ['nullable', 'string', 'size:13'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
