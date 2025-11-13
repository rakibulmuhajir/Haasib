<?php

namespace App\Http\Requests\Invoices;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('invoices.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'uuid',
                Rule::exists('acct.customers', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                })
            ],
            'invoice_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('acct.invoices', 'invoice_number')
                    ->where('company_id', $this->getCurrentCompanyId())
            ],
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after:issue_date',
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')
            ],
            'notes' => 'nullable|string|max:2000',
            'terms' => 'nullable|string|max:2000',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:1000',
            'line_items.*.quantity' => 'required|numeric|min:0.01|max:99999999.9999',
            'line_items.*.unit_price' => 'required|numeric|min:0|max:99999999.9999',
            'line_items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.account_id' => [
                'nullable',
                'uuid',
                Rule::exists('acct.accounts', 'id')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId());
                })
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required',
            'customer_id.exists' => 'Selected customer is invalid',
            'invoice_number.required' => 'Invoice number is required',
            'invoice_number.unique' => 'This invoice number already exists in your company',
            'issue_date.required' => 'Issue date is required',
            'issue_date.date' => 'Issue date must be a valid date',
            'due_date.required' => 'Due date is required',
            'due_date.after' => 'Due date must be after issue date',
            'currency.required' => 'Currency is required',
            'currency.exists' => 'Invalid currency selected',
            'line_items.required' => 'At least one line item is required',
            'line_items.min' => 'At least one line item is required',
            'line_items.*.description.required' => 'Description is required for all line items',
            'line_items.*.quantity.required' => 'Quantity is required for all line items',
            'line_items.*.quantity.min' => 'Quantity must be greater than 0',
            'line_items.*.unit_price.required' => 'Unit price is required for all line items',
            'line_items.*.unit_price.min' => 'Unit price cannot be negative',
            'line_items.*.tax_rate.max' => 'Tax rate cannot exceed 100%',
            'line_items.*.discount_percentage.max' => 'Discount cannot exceed 100%',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'line_items' => array_map(function ($item) {
                return array_merge([
                    'tax_rate' => 0,
                    'discount_percentage' => 0,
                ], $item);
            }, $this->input('line_items', []))
        ]);
    }
}