<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

/** Applying a payment's on-account amount to the customer's invoices, from the payment's page. */
class ApplyPaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_APPLY_CREDIT)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.invoice_id' => ['required', 'uuid'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
