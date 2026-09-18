<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\Rule;

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
            'invoice_id' => ['required', 'uuid', Rule::exists(Invoice::class, 'id')],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
