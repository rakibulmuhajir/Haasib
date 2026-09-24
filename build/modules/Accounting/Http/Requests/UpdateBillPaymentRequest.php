<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use Illuminate\Validation\Rule;

class UpdateBillPaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::BILL_PAY)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash', 'check', 'card', 'fuel_card', 'bank_transfer', 'ach', 'wire', 'other'])],
            'payment_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q
                    ->whereIn('subtype', ['bank', 'cash', 'credit_card'])
                    ->where('is_active', true)),
            ],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'ap_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where(fn ($q) => $q
                    ->where('subtype', 'accounts_payable')
                    ->where('is_active', true)),
            ],
        ];
    }
}
