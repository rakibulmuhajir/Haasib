<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;
use App\Modules\Payroll\Models\LeaveType;
use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends BaseFormRequest
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
                Rule::unique(LeaveType::class, 'code')->where('company_id', $company->id),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_paid' => 'boolean',
            'accrual_rate_hours' => 'numeric|min:0',
            'max_carryover_hours' => 'nullable|numeric|min:0',
            'max_balance_hours' => 'nullable|numeric|min:0',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
