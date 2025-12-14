<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;

class StoreEarningTypeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYROLL_SETTINGS_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                "unique:pay.earning_types,code,NULL,id,company_id,{$company->id},deleted_at,NULL",
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_taxable' => 'boolean',
            'affects_overtime' => 'boolean',
            'is_recurring' => 'boolean',
            'gl_account_id' => 'nullable|uuid|exists:acct.accounts,id',
            'is_active' => 'boolean',
        ];
    }
}
