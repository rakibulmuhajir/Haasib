<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Bill;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class ApplyVendorCreditRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::VENDOR_CREDIT_APPLY)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        return [
            'applications' => ['required', 'array', 'min:1'],
            'applications.*.bill_id' => ['required', 'uuid', Rule::exists(Bill::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'applications.*.amount_applied' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
