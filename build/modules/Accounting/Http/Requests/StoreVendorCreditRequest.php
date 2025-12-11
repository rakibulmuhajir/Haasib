<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Bill;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreVendorCreditRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BILL_PAY)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyContext = app(CompanyContextService::class);
        $companyId = $companyContext->getCompanyId();
        $companyBaseCurrency = $companyContext->getCompany()?->base_currency;
        $bill = $this->input('bill_id') ? Bill::find($this->input('bill_id')) : null;

        $creditNumberRule = Rule::unique('acct.vendor_credits', 'credit_number')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $vendorRule = Rule::exists('acct.vendors', 'id')
            ->where(fn ($q) => $q->where('company_id', $companyId));

        $billRule = Rule::exists('acct.bills', 'id')
            ->where(fn ($q) => $q->where('company_id', $companyId));

        $baseCurrencyRule = $companyBaseCurrency
            ? Rule::in([$companyBaseCurrency])
            : 'string';

        $currencyRule = ['required', 'string', 'size:3', 'uppercase'];
        if ($bill?->currency) {
            $currencyRule[] = Rule::in([$bill->currency]);
        } elseif ($companyBaseCurrency) {
            $currencyRule[] = Rule::in([$companyBaseCurrency]);
        }

        $exchangeRateRules = [
            'nullable',
            'numeric',
            'min:0.00000001',
            'decimal:8',
            Rule::requiredIf(fn () => $this->input('currency') && $this->input('base_currency') && $this->input('currency') !== $this->input('base_currency')),
            Rule::prohibitedIf(fn () => $this->input('currency') === $this->input('base_currency') && $this->filled('exchange_rate')),
        ];

        return [
            'vendor_id' => ['required', 'uuid', $vendorRule],
            'bill_id' => ['nullable', 'uuid', $billRule],
            'credit_number' => ['nullable', 'string', 'max:50', $creditNumberRule],
            'vendor_credit_number' => ['nullable', 'string', 'max:100'],
            'credit_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => $currencyRule,
            'base_currency' => ['required', 'string', 'size:3', 'uppercase', $baseCurrencyRule],
            'exchange_rate' => $exchangeRateRules,
            'reason' => ['required', 'string', 'max:255'],
            'status' => [Rule::in(['draft', 'received', 'applied', 'void'])],
            'notes' => ['nullable', 'string'],
            'transaction_id' => ['nullable', 'uuid', 'exists:acct.transactions,id'],
            'ap_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_payable')
                    ->where('is_active', true)),
            ],
            'line_items.*.expense_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->whereIn('type', ['expense', 'cogs', 'asset'])
                    ->where('is_active', true)),
            ],
        ];
    }
}
