<?php

namespace App\Modules\Accounting\Http\Requests;

use App\Constants\Permissions;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission(Permissions::INVOICE_UPDATE)
            && $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['sometimes', 'required', 'uuid', 'exists:acct.customers,id'],
            'line_items' => ['sometimes', 'required', 'array', 'min:1'],
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
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'uppercase'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'description' => ['nullable', 'string', 'max:500'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'invoice_date' => ['sometimes', 'required', 'date'],
        ];
    }

    /** Form field names, not payload paths. See StoreInvoiceRequest. */
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
