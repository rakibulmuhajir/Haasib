<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends BaseFormRequest
{
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
            'amount' => ['required', 'numeric', 'min:0.01'],
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
