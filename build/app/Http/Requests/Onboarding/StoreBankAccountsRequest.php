<?php

namespace App\Http\Requests\Onboarding;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreBankAccountsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::COMPANY_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'bank_accounts' => 'required|array|min:1',
            'bank_accounts.*.id' => 'nullable|uuid',
            'bank_accounts.*.account_name' => 'required|string|max:255',
            'bank_accounts.*.currency' => 'required|string|size:3|uppercase',
            'bank_accounts.*.account_type' => 'required|in:bank,cash',
        ];
    }
}
