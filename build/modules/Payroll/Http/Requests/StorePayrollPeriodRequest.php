<?php

namespace Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StorePayrollPeriodRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYROLL_RUN_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'payment_date' => 'required|date|after_or_equal:period_end',
        ];
    }
}
