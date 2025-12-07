<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permissions::CREDIT_NOTE_UPDATE) ?? false;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'required', 'uuid', 'exists:acct.customers,id'],
            'invoice_id' => ['sometimes', 'nullable', 'uuid', 'exists:acct.invoices,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'base_currency' => ['sometimes', 'required', 'string', 'size:3', 'uppercase'],
            'reason' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', 'in:draft,issued,partial,applied,void'],
            'credit_date' => ['sometimes', 'required', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}