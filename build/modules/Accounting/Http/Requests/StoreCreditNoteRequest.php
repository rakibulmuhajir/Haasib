<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::CREDIT_NOTE_CREATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:acct.customers,id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:acct.invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'base_currency' => ['required', 'string', 'size:3', 'uppercase'],
            'reason' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:draft,issued,partial,applied,void'],
            'credit_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string', 'max:1000'],
        ];
    }
}