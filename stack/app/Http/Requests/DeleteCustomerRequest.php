<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Models\Acct\Customer;

class DeleteCustomerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('customers.delete') && 
               $this->validateRlsContext() &&
               $this->validateCustomerAccess() &&
               $this->validateDeletionRules();
    }

    public function rules(): array
    {
        return [
            // No additional data needed for deletion - just authorization
            'force_delete' => 'boolean',
            'confirmation_reason' => 'nullable|string|max:500',
            'transfer_data_to_customer_id' => 'nullable|uuid|exists:acct.customers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'force_delete.boolean' => 'Force delete must be a boolean',
            'confirmation_reason.max' => 'Confirmation reason cannot exceed 500 characters',
            'transfer_data_to_customer_id.uuid' => 'Transfer customer ID must be a valid UUID',
            'transfer_data_to_customer_id.exists' => 'Transfer customer does not exist',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $customer = $this->getCustomer();
            
            if (!$customer) {
                return;
            }

            // Validate force delete permissions
            if ($this->boolean('force_delete')) {
                if (!$this->hasCompanyPermission('customers.force_delete')) {
                    $validator->errors()->add('force_delete', 
                        'You do not have permission to force delete customers');
                }
            }

            // Validate data transfer customer
            $this->validateDataTransferCustomer($validator, $customer);

            // Validate deletion business rules
            $this->validateDeletionBusinessRules($validator, $customer);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'force_delete' => $this->boolean('force_delete', false),
        ]);
    }

    private function validateCustomerAccess(): bool
    {
        $customerId = $this->route('customer');
        
        return Customer::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $customerId)
            ->exists();
    }

    private function validateDeletionRules(): bool
    {
        $customer = $this->getCustomer();
        
        if (!$customer) {
            return false;
        }

        // Can always force delete with proper permissions
        if ($this->boolean('force_delete')) {
            return true;
        }

        // Regular deletion requires customer to have no associated data
        return $this->canSoftDelete($customer);
    }

    private function canSoftDelete(Customer $customer): bool
    {
        // Check for outstanding balances
        if ($customer->total_outstanding > 0) {
            return false;
        }

        // Check for related records that would prevent soft deletion
        $hasInvoices = \App\Models\Acct\Invoice::where('customer_id', $customer->id)
            ->where('company_id', $this->getCurrentCompanyId())
            ->exists();
            
        if ($hasInvoices) {
            return false;
        }

        $hasPayments = \App\Models\Acct\Payment::where('customer_id', $customer->id)
            ->where('company_id', $this->getCurrentCompanyId())
            ->exists();
            
        if ($hasPayments) {
            return false;
        }

        return true;
    }

    private function validateDataTransferCustomer($validator, Customer $customer): void
    {
        $transferCustomerId = $this->input('transfer_data_to_customer_id');
        
        if ($transferCustomerId) {
            // Cannot transfer to self
            if ($transferCustomerId === $customer->id) {
                $validator->errors()->add('transfer_data_to_customer_id', 
                    'Cannot transfer data to the same customer being deleted');
                return;
            }

            // Validate transfer customer exists and belongs to company
            $transferCustomer = Customer::where('company_id', $this->getCurrentCompanyId())
                ->where('id', $transferCustomerId)
                ->first();
                
            if (!$transferCustomer) {
                $validator->errors()->add('transfer_data_to_customer_id', 
                    'Transfer customer does not exist in your company');
                return;
            }

            // Check if transfer customer is active
            if (!$transferCustomer->is_active) {
                $validator->errors()->add('transfer_data_to_customer_id', 
                    'Cannot transfer data to an inactive customer');
            }
        }
    }

    private function validateDeletionBusinessRules($validator, Customer $customer): void
    {
        if ($this->boolean('force_delete')) {
            // For force delete, log warning but allow
            \Log::warning('Force customer deletion requested', [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'user_id' => $this->user()->id,
                'reason' => $this->input('confirmation_reason'),
                'audit_context' => $this->getAuditContext(),
            ]);
            return;
        }

        // Regular deletion validations
        if ($customer->total_outstanding > 0) {
            $validator->errors()->add('customer', 
                'Cannot delete customer with outstanding balance of ' . 
                number_format($customer->total_outstanding, 2) . 
                '. Please collect all outstanding payments or use force delete.');
        }

        // Check for unpaid invoices
        $unpaidInvoices = \App\Models\Acct\Invoice::where('customer_id', $customer->id)
            ->where('company_id', $this->getCurrentCompanyId())
            ->whereIn('status', ['sent', 'partial'])
            ->count();
            
        if ($unpaidInvoices > 0) {
            $validator->errors()->add('customer', 
                "Cannot delete customer with {$unpaidInvoices} unpaid invoice(s). " .
                'Please resolve all invoices or use force delete.');
        }

        // Check for recent activity (last 30 days)
        $recentActivity = $this->hasRecentActivity($customer);
        if ($recentActivity) {
            $validator->errors()->add('customer', 
                'Cannot delete customer with recent activity (within last 30 days). ' .
                'Please wait for activity to age or use force delete.');
        }
    }

    private function hasRecentActivity(Customer $customer): bool
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        // Check for recent invoices
        $recentInvoices = \App\Models\Acct\Invoice::where('customer_id', $customer->id)
            ->where('company_id', $this->getCurrentCompanyId())
            ->where('created_at', '>', $thirtyDaysAgo)
            ->count();
            
        if ($recentInvoices > 0) {
            return true;
        }

        // Check for recent payments
        $recentPayments = \App\Models\Acct\Payment::where('customer_id', $customer->id)
            ->where('company_id', $this->getCurrentCompanyId())
            ->where('created_at', '>', $thirtyDaysAgo)
            ->count();
            
        return $recentPayments > 0;
    }

    /**
     * Get the customer being deleted
     */
    public function getCustomer(): ?Customer
    {
        $customerId = $this->route('customer');
        
        return Customer::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $customerId)
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
     * Get the customer to transfer data to
     */
    public function getTransferCustomer(): ?Customer
    {
        $transferCustomerId = $this->input('transfer_data_to_customer_id');
        
        if (!$transferCustomerId) {
            return null;
        }

        return Customer::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $transferCustomerId)
            ->first();
    }

    /**
     * Get deletion reason for audit trail
     */
    public function getDeletionReason(): string
    {
        return $this->input('confirmation_reason', 'No reason provided');
    }
}