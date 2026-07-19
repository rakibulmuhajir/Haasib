<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyIdentityRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $industryCodes = collect(config('company-industries', []))->pluck('code')->all();

        return [
            'industry_code' => [
                'required',
                'string',
                Rule::in($industryCodes),
                Rule::exists('acct.industry_coa_packs', 'code')->where('is_active', true),
            ],
            'registration_number' => 'nullable|string|max:100',
            'trade_name' => 'nullable|string|max:255',
            'timezone' => 'required|string|max:50',
        ];
    }
}
