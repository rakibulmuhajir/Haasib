<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::CREDIT_NOTE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', Rule::exists(Customer::class, 'id')->where('company_id', app(\App\Services\CurrentCompany::class)->get()->id)],
            'invoice_id' => ['nullable', 'uuid', Rule::exists(Invoice::class, 'id')->where('company_id', app(\App\Services\CurrentCompany::class)->get()->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'uppercase'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'base_currency' => ['required', 'string', 'size:3', 'uppercase'],
            'reason' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:draft,issued'],
            'credit_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
