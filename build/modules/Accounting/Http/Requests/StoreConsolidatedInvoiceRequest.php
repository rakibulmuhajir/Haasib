<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;

class StoreConsolidatedInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'title' => ['nullable', 'string', 'max:60'],
            'keys' => ['required', 'array', 'min:1'],
            'keys.*' => ['string'],
            'references' => ['nullable', 'array'],
            'physical' => ['nullable', 'array'],
            'fills' => ['nullable', 'array'],
            // Per line, as the customer's documents should read: the slip's date and the detail.
            'dates' => ['nullable', 'array'],
            'dates.*' => ['nullable', 'date_format:Y-m-d'],
            'items' => ['nullable', 'array'],
            // Columns left off the paper (Amount always prints).
            'hidden_columns' => ['nullable', 'array'],
            'hidden_columns.*' => ['string', 'in:'.implode(',', \App\Modules\Accounting\Services\ConsolidatedInvoiceService::OPTIONAL_COLUMNS)],
            'headings' => ['nullable', 'array'],
            'bill_to' => ['nullable', 'array'],
            'billed_by' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return ['keys.required' => 'Pick at least one line.'];
    }
}
