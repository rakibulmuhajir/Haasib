<?php

namespace App\Modules\FuelStation\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class PayCommissionRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENTS_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_date' => ['nullable', 'date'],
            'payment_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
        ];
    }
}
