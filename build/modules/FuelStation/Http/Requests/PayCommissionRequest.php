<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Account;
use Illuminate\Validation\Rule;

class PayCommissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_date' => ['nullable', 'date'],
            'payment_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')],
        ];
    }
}
