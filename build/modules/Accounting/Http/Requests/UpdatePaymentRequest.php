<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::PAYMENT_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        // See StorePaymentRequest::rules() -- 'acct.xxx' strings are misread by
        // Laravel as connection "acct", table "xxx" and must use the model class.
        return [
            'customer_id' => ['sometimes', 'required', 'uuid', Rule::exists(Customer::class, 'id')],
            'invoice_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(Invoice::class, 'id')],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'uppercase'],
            'payment_method' => ['sometimes', 'required', 'string', 'in:cash,bank_transfer,card,cheque,other'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payment_date' => ['sometimes', 'required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
