<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_CREATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid', 'exists:acct.customers,id'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.income_account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(fn ($q) => $q
                    ->where('type', 'revenue')
                    ->where('is_active', true)),
            ],
            'currency' => ['nullable', 'string', 'size:3', 'uppercase'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'description' => ['nullable', 'string', 'max:500'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'invoice_date' => ['required', 'date'],
            // Owner UI uses "approved" to indicate send/post immediately
            'status' => ['nullable', 'string', 'in:draft,approved,sent'],
            'send_immediately' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Field names as they appear on the form, not as they appear in the
     * payload. Without these Laravel says "The line_items.0.description field
     * is required", which names a JSON path the person filling the form has
     * never seen and cannot act on.
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'invoice_date' => 'invoice date',
            'due_date' => 'due date',
            'payment_terms' => 'payment terms',
            'internal_notes' => 'internal note',
            'notes' => 'note to the customer',
            'line_items' => 'billed items',
            'line_items.*.description' => 'description',
            'line_items.*.quantity' => 'quantity',
            'line_items.*.unit_price' => 'unit price',
            'line_items.*.tax_rate' => 'tax rate',
            'line_items.*.discount_rate' => 'discount',
            'line_items.*.income_account_id' => 'income account',
        ];
    }

    /**
     * Say what to do, not what failed. A rejected field is the one place the
     * interface has the reader's full attention and the least room to waste it.
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Choose who this invoice is for.',
            'customer_id.exists' => 'That customer is no longer on file. Pick another.',
            'line_items.required' => 'Add at least one thing being billed.',
            'line_items.min' => 'Add at least one thing being billed.',
            'line_items.*.description.required' => 'Say what was sold.',
            'line_items.*.quantity.required' => 'Enter a quantity.',
            'line_items.*.quantity.min' => 'Quantity has to be more than zero.',
            'line_items.*.unit_price.required' => 'Enter a unit price.',
            'line_items.*.unit_price.min' => 'A unit price cannot be negative.',
            'invoice_date.required' => 'Enter the invoice date.',
            'due_date.after_or_equal' => 'The due date cannot fall before the invoice date.',
        ];
    }
}
