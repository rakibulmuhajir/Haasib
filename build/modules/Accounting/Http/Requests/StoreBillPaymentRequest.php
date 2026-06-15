<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Vendor;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreBillPaymentRequest extends BaseFormRequest
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
        $companyApAccountId = $companyContext->getCompany()?->ap_account_id;
        $vendorApAccountId = null;

        if ($this->filled('vendor_id')) {
            $vendorApAccountId = Vendor::where('company_id', $companyId)
                ->where('id', $this->input('vendor_id'))
                ->value('ap_account_id');
        }

        $hasPaymentSplits = collect($this->input('payment_splits', []))
            ->filter(fn ($split) => (float) ($split['amount'] ?? 0) > 0)
            ->isNotEmpty();
        $requiresApAccount = $this->filled('vendor_id') && ! $companyApAccountId && ! $vendorApAccountId;

        $paymentNumberRule = Rule::unique('acct.bill_payments', 'payment_number')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $baseCurrencyRule = $companyBaseCurrency
            ? Rule::in([$companyBaseCurrency])
            : 'string';

        $exchangeRateRules = [
            'nullable',
            'numeric',
            'min:0.00000001',
            'decimal:8',
            Rule::requiredIf(fn () => $this->input('currency') && $this->input('base_currency') && $this->input('currency') !== $this->input('base_currency')),
            Rule::prohibitedIf(fn () => $this->input('currency') === $this->input('base_currency') && $this->filled('exchange_rate')),
        ];

        return [
            'vendor_id' => ['required', 'uuid', 'exists:acct.vendors,id'],
            'payment_number' => ['nullable', 'string', 'max:50', $paymentNumberRule],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'exchange_rate' => $exchangeRateRules,
            'base_currency' => ['required', 'string', 'size:3', 'uppercase', $baseCurrencyRule],
            'payment_method' => [$hasPaymentSplits ? 'nullable' : 'required', Rule::in(['cash', 'check', 'card', 'fuel_card', 'bank_transfer', 'ach', 'wire', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'payment_account_id' => [
                $hasPaymentSplits ? 'nullable' : 'required',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
                    ->where('is_active', true)),
            ],
            'payment_splits' => ['nullable', 'array'],
            'payment_splits.*.payment_account_id' => [
                'required_with:payment_splits',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
                    ->where('is_active', true)),
            ],
            'payment_splits.*.amount' => ['required_with:payment_splits', 'numeric', 'min:0.01'],
            'payment_splits.*.payment_method' => ['required_with:payment_splits', Rule::in(['cash', 'check', 'card', 'fuel_card', 'bank_transfer', 'ach', 'wire', 'other'])],
            'payment_splits.*.reference_number' => ['nullable', 'string', 'max:100'],
            'ap_account_id' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn () => $requiresApAccount),
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_payable')
                    ->where('is_active', true)),
            ],
            // Allocations
            'allocations' => ['nullable', 'array'],
            'allocations.*.bill_id' => ['required_with:allocations', 'uuid', 'exists:acct.bills,id'],
            'allocations.*.amount_allocated' => ['required_with:allocations', 'numeric', 'min:0'],
        ];
    }
}
