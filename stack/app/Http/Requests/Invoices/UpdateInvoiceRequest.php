<?php

namespace App\Http\Requests\Invoices;

use App\Http\Requests\BaseFormRequest;
use App\Models\Acct\Invoice;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('invoices.update') && 
               $this->validateRlsContext() &&
               $this->validateInvoiceAccess() &&
               $this->validateInvoiceModifiable();
    }

    public function rules(): array
    {
        $invoice = $this->getInvoice();
        $invoiceId = $invoice?->id;
        
        return [
            // Basic invoice information
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
                    ->ignore($invoiceId)
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
            
            // Line items
            'line_items' => 'required|array|min:1',
            'line_items.*.id' => 'nullable|uuid|exists:acct.invoice_line_items,id',
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
            'line_items.*._destroy' => 'boolean', // For marking line items to delete
            
            // Status changes
            'status' => [
                'nullable',
                'string',
                Rule::in(['draft', 'sent', 'void'])
            ],
            
            // Additional metadata
            'purchase_order_number' => 'nullable|string|max:100',
            'payment_terms' => 'nullable|integer|min:0|max:365',
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
            'due_date.required' => 'Due date is required',
            'due_date.after' => 'Due date must be after issue date',
            'currency.required' => 'Currency is required',
            'currency.exists' => 'Invalid currency selected',
            'line_items.required' => 'At least one line item is required',
            'line_items.min' => 'At least one line item is required',
            'line_items.*.id.exists' => 'Line item ID does not exist',
            'line_items.*.description.required' => 'Description is required for all line items',
            'line_items.*.quantity.required' => 'Quantity is required for all line items',
            'line_items.*.quantity.min' => 'Quantity must be greater than 0',
            'line_items.*.unit_price.required' => 'Unit price is required for all line items',
            'line_items.*.unit_price.min' => 'Unit price cannot be negative',
            'line_items.*.tax_rate.max' => 'Tax rate cannot exceed 100%',
            'line_items.*.discount_percentage.max' => 'Discount cannot exceed 100%',
            'status.in' => 'Status must be one of: draft, sent, or void',
            'purchase_order_number.max' => 'Purchase order number cannot exceed 100 characters',
            'payment_terms.min' => 'Payment terms cannot be negative',
            'payment_terms.max' => 'Payment terms cannot exceed 365 days',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $invoice = $this->getInvoice();
            
            if (!$invoice) {
                return;
            }

            // Validate status change permissions
            $this->validateStatusChange($validator, $invoice);
            
            // Validate line item changes
            $this->validateLineItemChanges($validator, $invoice);
            
            // Validate customer changes
            $this->validateCustomerChange($validator, $invoice);
        });
    }

    protected function prepareForValidation(): void
    {
        // Set default values for line items
        $lineItems = collect($this->input('line_items', []))->map(function ($item) {
            return array_merge([
                'tax_rate' => 0,
                'discount_percentage' => 0,
                '_destroy' => false,
            ], $item);
        })->toArray();

        $this->merge(['line_items' => $lineItems]);
    }

    private function validateInvoiceAccess(): bool
    {
        $invoiceId = $this->route('invoice');
        
        return Invoice::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $invoiceId)
            ->exists();
    }

    private function validateInvoiceModifiable(): bool
    {
        $invoice = $this->getInvoice();
        
        if (!$invoice) {
            return false;
        }

        // Only draft invoices can have major modifications
        if (in_array($invoice->status, ['paid', 'partial'])) {
            // Only allow voiding paid invoices
            return $this->input('status') === 'void';
        }

        return true;
    }

    private function validateStatusChange($validator, Invoice $invoice): void
    {
        $newStatus = $this->input('status');
        
        if ($newStatus && $newStatus !== $invoice->status) {
            if ($newStatus === 'void') {
                // Cannot void invoices with payments
                if ($invoice->payments()->exists()) {
                    $validator->errors()->add('status', 
                        'Cannot void an invoice that has payments applied');
                }
                
                // Require special permission for voiding
                if (!$this->hasCompanyPermission('invoices.void')) {
                    $validator->errors()->add('status', 
                        'You do not have permission to void invoices');
                }
            }
            
            // Can't change from sent to draft
            if ($invoice->status === 'sent' && $newStatus === 'draft') {
                $validator->errors()->add('status', 
                    'Cannot change sent invoice back to draft');
            }
        }
    }

    private function validateLineItemChanges($validator, Invoice $invoice): void
    {
        $lineItems = $this->input('line_items', []);
        
        // Filter out items marked for deletion
        $activeLineItems = collect($lineItems)->filter(fn($item) => !($item['_destroy'] ?? false));
        
        if ($activeLineItems->isEmpty()) {
            $validator->errors()->add('line_items', 
                'At least one line item must remain');
        }

        // Check modifications based on invoice status
        if (in_array($invoice->status, ['sent', 'partial'])) {
            $originalLineItems = $invoice->line_items->keyBy('id');
            
            foreach ($activeLineItems as $lineItem) {
                if (isset($lineItem['id'])) {
                    $originalItem = $originalLineItems->get($lineItem['id']);
                    
                    if ($originalItem && $this->lineItemChanged($originalItem, $lineItem)) {
                        $validator->errors()->add('line_items', 
                            'Cannot modify line items on ' . $invoice->status . ' invoices');
                        break;
                    }
                } else {
                    // Adding new line items to sent/paid invoice
                    $validator->errors()->add('line_items', 
                        'Cannot add new line items to ' . $invoice->status . ' invoices');
                    break;
                }
            }
        }
    }

    private function lineItemChanged($originalItem, $newItem): bool
    {
        $fields = ['description', 'quantity', 'unit_price', 'tax_rate', 'discount_percentage'];
        
        foreach ($fields as $field) {
            if (($originalItem->$field ?? 0) != ($newItem[$field] ?? 0)) {
                return true;
            }
        }
        
        return false;
    }

    private function validateCustomerChange($validator, Invoice $invoice): void
    {
        $newCustomerId = $this->input('customer_id');
        
        if ($newCustomerId && $newCustomerId !== $invoice->customer_id) {
            // Cannot change customer if invoice has payments
            if ($invoice->payments()->exists()) {
                $validator->errors()->add('customer_id', 
                    'Cannot change customer on an invoice that has payments');
            }
            
            // Cannot change customer on sent invoices
            if ($invoice->status === 'sent') {
                $validator->errors()->add('customer_id', 
                    'Cannot change customer on sent invoices');
            }
        }
    }

    /**
     * Get the invoice being updated
     */
    public function getInvoice(): ?Invoice
    {
        $invoiceId = $this->route('invoice');
        
        return Invoice::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $invoiceId)
            ->first();
    }

    /**
     * Get line items data separated by operation
     */
    public function getLineItemsData(): array
    {
        $lineItems = $this->input('line_items', []);
        
        return [
            'to_create' => collect($lineItems)->filter(fn($item) => !isset($item['id']) && !($item['_destroy'] ?? false))->toArray(),
            'to_update' => collect($lineItems)->filter(fn($item) => isset($item['id']) && !($item['_destroy'] ?? false))->toArray(),
            'to_delete' => collect($lineItems)->filter(fn($item) => isset($item['id']) && ($item['_destroy'] ?? false))->pluck('id')->toArray(),
        ];
    }
}