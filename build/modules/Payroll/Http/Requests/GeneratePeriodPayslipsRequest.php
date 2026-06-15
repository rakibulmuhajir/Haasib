<?php

namespace App\Modules\Payroll\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class GeneratePeriodPayslipsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYSLIP_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [];
    }
}
