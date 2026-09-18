<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use Illuminate\Validation\Rule;

class SettlePaymentChannelRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'clearing_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'bank_account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')],
            'amount_received' => ['required', 'numeric', 'min:0.01'],
            'fees' => ['nullable', 'numeric', 'min:0'],
            'settlement_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
