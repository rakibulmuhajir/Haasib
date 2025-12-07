<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::PAYMENT_CREATE) ?? false;
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
        ];
    }
}