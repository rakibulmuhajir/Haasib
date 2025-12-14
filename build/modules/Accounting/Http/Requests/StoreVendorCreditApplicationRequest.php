<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreVendorCreditApplicationRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BILL_PAY)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyId = app(CompanyContextService::class)->getCompanyId();

        $vendorCreditRule = Rule::exists('acct.vendor_credits', 'id')
            ->where(fn ($q) => $q->where('company_id', $companyId));

        $billRule = Rule::exists('acct.bills', 'id')
            ->where(fn ($q) => $q->where('company_id', $companyId));

        return [
            'vendor_credit_id' => ['required', 'uuid', $vendorCreditRule],
            'bill_id' => ['required', 'uuid', $billRule],
            'amount_applied' => ['required', 'numeric', 'min:0.01'],
            'applied_at' => ['nullable', 'date'],
            'user_id' => ['nullable', 'uuid', 'exists:auth.users,id'],
            'notes' => ['nullable', 'string'],
            'bill_balance_before' => ['required', 'numeric', 'decimal:2'],
            'bill_balance_after' => ['required', 'numeric', 'decimal:2'],
        ];
    }
}
