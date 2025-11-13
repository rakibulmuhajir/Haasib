<?php

namespace App\Http\Requests\Invoices;

use App\Http\Requests\BaseFormRequest;
use App\Models\Acct\Invoice;

class DeleteInvoiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('invoices.delete') && 
               $this->validateRlsContext() &&
               $this->validateInvoiceAccess() &&
               $this->validateDeletionRules();
    }

    public function rules(): array
    {
        return [
            'force_delete' => 'boolean',
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'force_delete.boolean' => 'Force delete must be a boolean',
            'reason.required' => 'Deletion reason is required',
            'reason.max' => 'Deletion reason cannot exceed 500 characters',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $invoice = $this->getInvoice();
            
            if (!$invoice) {
                return;
            }

            // Validate force delete permissions
            if ($this->boolean('force_delete')) {
                if (!$this->hasCompanyPermission('invoices.force_delete')) {
                    $validator->errors()->add('force_delete', 
                        'You do not have permission to force delete invoices');
                }
            }

            // Validate business rules
            $this->validateDeletionBusinessRules($validator, $invoice);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'force_delete' => $this->boolean('force_delete', false),
        ]);
    }

    private function validateInvoiceAccess(): bool
    {
        $invoiceId = $this->route('invoice');
        
        return Invoice::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $invoiceId)
            ->exists();
    }

    private function validateDeletionRules(): bool
    {
        $invoice = $this->getInvoice();
        
        if (!$invoice) {
            return false;
        }

        // Can always force delete with proper permissions
        if ($this->boolean('force_delete')) {
            return true;
        }

        // Regular deletion requires invoice to be draft
        return $invoice->status === 'draft';
    }

    private function validateDeletionBusinessRules($validator, Invoice $invoice): void
    {
        if ($this->boolean('force_delete')) {
            \Log::warning('Force invoice deletion requested', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $this->user()->id,
                'reason' => $this->input('reason'),
                'audit_context' => $this->getAuditContext(),
            ]);
            return;
        }

        // Regular deletion validations
        if ($invoice->status !== 'draft') {
            $validator->errors()->add('invoice', 
                'Only draft invoices can be deleted. Use force delete for other statuses.');
        }

        // Check for payments
        if ($invoice->payments()->exists()) {
            $validator->errors()->add('invoice', 
                'Cannot delete invoice with payments. Use force delete to override.');
        }
    }

    /**
     * Get the invoice being deleted
     */
    public function getInvoice(): ?Invoice
    {
        $invoiceId = $this->route('invoice');
        
        return Invoice::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $invoiceId)
            ->first();
    }

    /**
     * Check if this is a force delete operation
     */
    public function isForceDelete(): bool
    {
        return $this->boolean('force_delete');
    }

    /**
     * Get deletion reason for audit trail
     */
    public function getDeletionReason(): string
    {
        return $this->input('reason');
    }
}