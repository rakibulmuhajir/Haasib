<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
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
            'payment_number' => ['required', 'string', 'max:50', $paymentNumberRule],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:6'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'exchange_rate' => $exchangeRateRules,
            'base_currency' => ['required', 'string', 'size:3', 'uppercase', $baseCurrencyRule],
            'payment_method' => ['required', Rule::in(['cash', 'check', 'card', 'bank_transfer', 'ach', 'wire', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
