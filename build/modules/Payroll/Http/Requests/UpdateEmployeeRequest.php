<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CurrentCompany;

class UpdateEmployeeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::EMPLOYEE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();
        $employeeId = $this->route('employee');

        return [
            'employee_number' => [
                'required',
                'string',
                'max:50',
                "unique:pay.employees,employee_number,{$employeeId},id,company_id,{$company->id},deleted_at,NULL",
            ],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'national_id' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:100',
            'address' => 'nullable|array',
            'address.street' => 'nullable|string|max:255',
            'address.city' => 'nullable|string|max:100',
            'address.state' => 'nullable|string|max:100',
            'address.zip' => 'nullable|string|max:20',
            'address.country' => 'nullable|string|max:100',
            'hire_date' => 'required|date',
            'termination_date' => 'nullable|date|after_or_equal:hire_date',
            'termination_reason' => 'nullable|required_with:termination_date|string|max:255',
            'employment_type' => 'required|in:full_time,part_time,contract,intern',
            'employment_status' => 'required|in:active,on_leave,suspended,terminated',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'manager_id' => "nullable|uuid|exists:pay.employees,id|not_in:{$employeeId}",
            'pay_frequency' => 'required|in:weekly,biweekly,semimonthly,monthly',
            'base_salary' => 'required|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3|uppercase',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_routing_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
