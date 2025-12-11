<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreCompanyIdentityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'industry_code' => 'required|string|exists:acct.industry_coa_packs,code',
            'registration_number' => 'nullable|string|max:100',
            'trade_name' => 'nullable|string|max:255',
            'timezone' => 'required|string|max:50',
        ];
    }
}
