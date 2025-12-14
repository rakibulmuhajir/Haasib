<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreTaxSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'tax_registered' => 'required|boolean',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'tax_inclusive' => 'required|boolean',
        ];
    }
}
