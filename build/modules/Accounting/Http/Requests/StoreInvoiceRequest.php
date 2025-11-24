<?php

namespace Modules\Accounting\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Models\CompanyCurrency;
use Modules\Accounting\Models\Customer;

class StoreInvoiceRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('acct.invoices.create') &&
               $this->validateRlsContext();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                'uuid'
            ],
            'issue_date' => [
                'required',
                'date',
                'before_or_equal:today'
            ],
            'due_date' => [
                'required',
                'date',
                'after_or_equal:issue_date'
            ],
            'currency_code' => [
                'required',
                'string',
                'size:3'
            ],
            'exchange_rate' => [
                'required',
                'numeric',
                'min:0.000001',
                'max:999999.999999'
            ],
            'line_items' => [
                'required',
                'array',
                'min:1'
            ],
            'line_items.*.description' => [
                'required',
                'string',
                'max:255'
            ],
            'line_items.*.quantity' => [
                'required',
                'numeric',
                'min:0.01'
            ],
            'line_items.*.unit_price' => [
                'required',
                'numeric',
                'min:0'
            ],
            'line_items.*.total' => [
                'required',
                'numeric',
                'min:0'
            ],
            'subtotal' => [
                'required',
                'numeric',
                'min:0'
            ],
            'discount_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99'
            ],
            'shipping_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999.99'
            ],
            'total_amount' => [
                'required',
                'numeric',
                'min:0'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000'
            ]
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'customer',
            'issue_date' => 'issue date',
            'due_date' => 'due date',
            'currency_code' => 'currency',
            'exchange_rate' => 'exchange rate',
            'line_items' => 'line items',
            'line_items.*.description' => 'item description',
            'line_items.*.quantity' => 'quantity',
            'line_items.*.unit_price' => 'unit price',
            'line_items.*.total' => 'line total',
            'subtotal' => 'subtotal',
            'discount_amount' => 'discount amount',
            'shipping_amount' => 'shipping amount',
            'total_amount' => 'total amount',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'customer_id.required' => 'Please select a customer',
            'customer_id.exists' => 'The selected customer is not valid',
            'issue_date.before_or_equal' => 'Issue date cannot be in the future',
            'due_date.after_or_equal' => 'Due date must be on or after the issue date',
            'currency_code.exists' => 'The selected currency is not available for your company',
            'currency_code.size' => 'Currency code must be exactly 3 characters',
            'exchange_rate.min' => 'Exchange rate must be greater than 0',
            'exchange_rate.max' => 'Exchange rate is too large',
            'line_items.required' => 'At least one line item is required',
            'line_items.min' => 'At least one line item is required',
            'line_items.*.description.required' => 'Item description is required',
            'line_items.*.quantity.required' => 'Quantity is required',
            'line_items.*.quantity.min' => 'Quantity must be greater than 0',
            'line_items.*.unit_price.required' => 'Unit price is required',
            'line_items.*.unit_price.min' => 'Unit price cannot be negative',
            'line_items.*.total.required' => 'Line total is required',
            'total_amount.required' => 'Total amount is required',
            'notes.max' => 'Notes cannot exceed 1000 characters',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $companyId = session('active_company_id');
            
            // Check if customer exists and belongs to company
            if ($this->customer_id) {
                $customer = Customer::where('company_id', $companyId)
                    ->where('id', $this->customer_id)
                    ->where('status', 'active')
                    ->first();
                    
                if (!$customer) {
                    $validator->errors()->add('customer_id', 'The selected customer is not valid or inactive.');
                }
            }
            
            // Check if currency exists and is active for company
            if ($this->currency_code) {
                $currency = CompanyCurrency::where('company_id', $companyId)
                    ->where('currency_code', $this->currency_code)
                    ->where('is_active', true)
                    ->first();
                    
                if (!$currency) {
                    $validator->errors()->add('currency_code', 'The selected currency is not available for your company.');
                }
            }
            
            // Validate line item totals match quantity * unit_price
            if ($this->line_items && is_array($this->line_items)) {
                foreach ($this->line_items as $index => $item) {
                    if (isset($item['quantity'], $item['unit_price'], $item['total'])) {
                        $expectedTotal = round($item['quantity'] * $item['unit_price'], 2);
                        $actualTotal = round($item['total'], 2);
                        
                        if (abs($expectedTotal - $actualTotal) > 0.01) {
                            $validator->errors()->add(
                                "line_items.{$index}.total",
                                'Line total does not match quantity × unit price.'
                            );
                        }
                    }
                }
            }
            
            // Validate total calculations
            if ($this->line_items && is_array($this->line_items)) {
                $calculatedSubtotal = collect($this->line_items)->sum('total');
                $discountAmount = floatval($this->discount_amount ?? 0);
                $shippingAmount = floatval($this->shipping_amount ?? 0);
                $calculatedTotal = $calculatedSubtotal - $discountAmount + $shippingAmount;
                
                // Check subtotal
                if (abs($calculatedSubtotal - floatval($this->subtotal ?? 0)) > 0.01) {
                    $validator->errors()->add('subtotal', 'Subtotal does not match line item totals.');
                }
                
                // Check total amount
                if (abs($calculatedTotal - floatval($this->total_amount ?? 0)) > 0.01) {
                    $validator->errors()->add('total_amount', 'Total amount calculation is incorrect.');
                }
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => strtoupper($this->currency_code ?? ''),
            'exchange_rate' => floatval($this->exchange_rate ?? 1.0),
            'subtotal' => floatval($this->subtotal ?? 0),
            'discount_amount' => floatval($this->discount_amount ?? 0),
            'shipping_amount' => floatval($this->shipping_amount ?? 0),
            'total_amount' => floatval($this->total_amount ?? 0),
        ]);

        // Clean line items
        if ($this->line_items && is_array($this->line_items)) {
            $cleanedItems = [];
            foreach ($this->line_items as $item) {
                $cleanedItems[] = [
                    'description' => trim($item['description'] ?? ''),
                    'quantity' => floatval($item['quantity'] ?? 0),
                    'unit_price' => floatval($item['unit_price'] ?? 0),
                    'total' => floatval($item['total'] ?? 0),
                ];
            }
            $this->merge(['line_items' => $cleanedItems]);
        }
    }
}