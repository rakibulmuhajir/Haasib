<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Models\CompanyCurrency;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Services\CurrentCompany;
use Closure;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
    }

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
            'currency' => [
                'required',
                'string',
                'size:3',
                'uppercase',
                function (string $attribute, mixed $value, Closure $fail) use ($company): void {
                    if ($value !== $company->base_currency && ! CompanyCurrency::query()->where('company_id', $company->id)->where('currency_code', $value)->exists()) {
                        $fail('The selected currency is not enabled for this company.');
                    }
                },
            ],
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:100',
            'bank_name' => 'nullable|string|max:255',
            'bank_routing_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('currency')) {
                return;
            }
            $company = app(CurrentCompany::class)->get();
            if (Payslip::query()->where('company_id', $company->id)->where('employee_id', $this->route('employee'))->whereIn('status', ['approved', 'paid'])->exists()) {
                $current = Employee::query()->where('company_id', $company->id)->whereKey($this->route('employee'))->value('currency');
                if ($current !== $this->input('currency')) {
                    $validator->errors()->add('currency', 'Employee currency cannot change after payroll has been posted.');
                }
            }
        }];
    }
}
