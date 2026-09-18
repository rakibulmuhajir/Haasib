<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;
use App\Modules\Payroll\Models\EarningType;

class UpdateEarningTypeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYROLL_SETTINGS_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();
        $earningTypeId = $this->route('earning_type');

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(EarningType::class, 'code')->ignore($earningTypeId, 'id')->where('company_id', $company->id)->whereNull('deleted_at'),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_taxable' => 'boolean',
            'affects_overtime' => 'boolean',
            'is_recurring' => 'boolean',
            'gl_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
            'is_active' => 'boolean',
        ];
    }
}
