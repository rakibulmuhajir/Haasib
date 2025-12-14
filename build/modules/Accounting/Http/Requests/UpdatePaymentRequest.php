<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class UpdatePaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'required', 'uuid', 'exists:acct.customers,id'],
            'invoice_id' => ['sometimes', 'nullable', 'uuid', 'exists:acct.invoices,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'uppercase'],
            'payment_method' => ['sometimes', 'required', 'string', 'in:cash,bank_transfer,card,cheque,other'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payment_date' => ['sometimes', 'required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
