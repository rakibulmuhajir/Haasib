<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Services\CompanyContextService;
use Illuminate\Validation\Rule;

class StoreBillRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BILL_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        $companyContext = app(CompanyContextService::class);
        $companyId = $companyContext->getCompanyId();
        $companyBaseCurrency = $companyContext->getCompany()?->base_currency;

        $billNumberRule = Rule::unique('acct.bills', 'bill_number')
            ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'));

        $baseCurrencyRule = $companyBaseCurrency
            ? Rule::in([$companyBaseCurrency])
            : 'string';

        $statusValues = ['draft', 'received', 'partial', 'paid', 'overdue', 'void', 'cancelled'];

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
            'bill_number' => ['required', 'string', 'max:50', $billNumberRule],
            'vendor_invoice_number' => ['nullable', 'string', 'max:100'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:bill_date'],
            'status' => ['in:' . implode(',', $statusValues)],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'base_currency' => ['required', 'string', 'size:3', 'uppercase', $baseCurrencyRule],
            'exchange_rate' => $exchangeRateRules,
            'payment_terms' => ['integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:500'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
        ];
    }
}
