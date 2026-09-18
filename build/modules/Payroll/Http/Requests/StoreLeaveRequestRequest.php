<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\LeaveType;
use App\Services\CurrentCompany;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::LEAVE_REQUEST_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $company = app(CurrentCompany::class)->get();

        return [
            'employee_id' => [
                'required',
                'uuid',
                Rule::exists(Employee::class, 'id')->where('company_id', $company->id),
            ],
            'leave_type_id' => [
                'required',
                'uuid',
                Rule::exists(LeaveType::class, 'id')->where('company_id', $company->id),
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'hours' => 'required|numeric|min:0.5',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ];
    }
}
