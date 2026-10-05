<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class UpdateVendorCreditRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::VENDOR_CREDIT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyContext = app(CompanyContextService::class);
        $companyId = $companyContext->getCompanyId();
        $base = $companyContext->getCompany()?->base_currency;

        return [
            'vendor_id' => ['required', 'uuid', Rule::exists(Vendor::class, 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'vendor_credit_number' => ['nullable', 'string', 'max:100'],
            'credit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'base_currency' => ['required', 'string', 'size:3', 'uppercase', $base ? Rule::in([$base]) : 'string'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001', 'decimal:8'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'ap_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_payable')
                    ->where('is_active', true)),
            ],
            'line_items' => ['nullable', 'array'],
            'line_items.*.description' => ['sometimes', 'required', 'string', 'max:500'],
            'line_items.*.quantity' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'line_items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.expense_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q
                    ->where('company_id', $companyId)
                    ->whereIn('type', ['expense', 'cogs', 'asset', 'other_expense'])
                    ->where('is_active', true)),
            ],
        ];
    }
}
