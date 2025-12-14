<?php

namespace Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;

class StorePayslipRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYSLIP_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'payroll_period_id' => "required|uuid|exists:pay.payroll_periods,id,company_id,{$company->id}",
            'employee_id' => "required|uuid|exists:pay.employees,id,company_id,{$company->id}",
            'currency' => 'required|string|size:3|uppercase',
            'notes' => 'nullable|string',
            'lines' => 'array',
            'lines.*.line_type' => 'required|in:earning,deduction,employer',
            'lines.*.earning_type_id' => 'nullable|uuid|exists:pay.earning_types,id',
            'lines.*.deduction_type_id' => 'nullable|uuid|exists:pay.deduction_types,id',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.rate' => 'required|numeric|min:0',
            'lines.*.amount' => 'required|numeric|min:0',
            'lines.*.sort_order' => 'integer|min:0',
        ];
    }
}
