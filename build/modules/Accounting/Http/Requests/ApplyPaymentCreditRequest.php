<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class ApplyPaymentCreditRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_APPLY_CREDIT)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'uuid', 'exists:acct.invoices,id'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
