<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends BaseFormRequest
{
    protected function prepareForValidation(): void
    {
        // The UI uses a sentinel for the selectable "company default" option.
        // Convert it before UUID validation so the action can resolve the
        // customer's configured AR account.
        if ($this->input('ar_account_id') === 'company_default') {
            $this->merge(['ar_account_id' => null]);
        }
    }

    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:acct.customers,id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:acct.invoices,id'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['uuid', 'exists:acct.invoices,id'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', 'uuid', 'exists:acct.invoices,id'],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_charge' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,card,cheque,other'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'deposit_account_id' => [
                'required',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->where('is_active', true)),
            ],
            'ar_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_receivable')
                    ->where('is_active', true)),
            ],
        ];
    }
}
