<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Models\Acct\Customer;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('customers.update') && 
               $this->validateRlsContext() &&
               $this->validateCustomerAccess();
    }

    public function rules(): array
    {
        $customerId = $this->route('customer');
        
        return [
            // Basic customer information
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[\pL\s\-\',\.&]+$/u'
            ],
            'legal_name' => 'nullable|string|max:255',
            'customer_type' => [
                'required',
                'string',
                Rule::in(['individual', 'business', 'non_profit', 'government'])
            ],
            'tax_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('acct.customers', 'tax_id')->where(function ($query) use ($customerId) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                          ->where('id', '!=', $customerId)
                          ->whereNull('deleted_at');
                })
            ],
            'registration_number' => 'nullable|string|max:100',
            
            // Contact information
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('acct.customers', 'email')->where(function ($query) use ($customerId) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                          ->where('id', '!=', $customerId)
                          ->whereNull('deleted_at');
                })
            ],
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            
            // Address information
            'billing_address' => 'required|array',
            'billing_address.line1' => 'required|string|max:255',
            'billing_address.line2' => 'nullable|string|max:255',
            'billing_address.city' => 'required|string|max:100',
            'billing_address.state' => 'required|string|max:100',
            'billing_address.postal_code' => 'required|string|max:20',
            'billing_address.country' => 'required|string|size:2',
            
            'shipping_address' => 'nullable|array',
            'shipping_address.line1' => 'nullable|string|max:255',
            'shipping_address.line2' => 'nullable|string|max:255',
            'shipping_address.city' => 'nullable|string|max:100',
            'shipping_address.state' => 'nullable|string|max:100',
            'shipping_address.postal_code' => 'nullable|string|max:20',
            'shipping_address.country' => 'nullable|string|size:2',
            
            // Credit and payment settings
            'credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99'
            ],
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')
            ],
            
            // Status and settings
            'is_active' => 'boolean',
            'is_tax_exempt' => 'boolean',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',
            
            // Contact updates
            'contacts' => 'nullable|array|max:5',
            'contacts.*.id' => 'nullable|uuid|exists:acct.customer_contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.role' => 'nullable|string|max:100',
            'contacts.*.is_primary' => 'boolean',
            'contacts.*._destroy' => 'boolean', // For marking contacts to delete
        ];
    }

    public function messages(): array
    {
        return [
            // Basic information
            'name.required' => 'Customer name is required',
            'customer_type.required' => 'Customer type is required',
            'customer_type.in' => 'Customer type must be one of: individual, business, non_profit, or government',
            'tax_id.unique' => 'Tax ID is already used by another customer in your company',
            
            // Contact information
            'email.required' => 'Email address is required',
            'email.unique' => 'This email address is already used by another customer in your company',
            
            // Billing address
            'billing_address.required' => 'Billing address is required',
            'billing_address.line1.required' => 'Billing address line 1 is required',
            'billing_address.city.required' => 'Billing city is required',
            'billing_address.state.required' => 'Billing state is required',
            'billing_address.postal_code.required' => 'Billing postal code is required',
            'billing_address.country.required' => 'Billing country is required',
            
            // Credit and payment settings
            'credit_limit.min' => 'Credit limit cannot be negative',
            'payment_terms.min' => 'Payment terms cannot be negative',
            'currency.required' => 'Currency is required',
            
            // Contacts
            'contacts.max' => 'Cannot have more than 5 contacts',
            'contacts.*.id.exists' => 'Contact ID does not exist',
            'contacts.*.name.required' => 'Contact name is required',
            'contacts.*.email.email' => 'Please provide a valid contact email',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate business customer requirements
            $this->validateBusinessCustomerRequirements($validator);
            
            // Validate credit limit changes
            $this->validateCreditLimitChanges($validator);
            
            // Validate primary contact
            $this->validatePrimaryContact($validator);
            
            // Validate customer can be deactivated
            $this->validateDeactivationRules($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'is_tax_exempt' => $this->boolean('is_tax_exempt'),
        ]);
    }

    private function validateCustomerAccess(): bool
    {
        $customerId = $this->route('customer');
        
        return Customer::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $customerId)
            ->exists();
    }

    private function validateBusinessCustomerRequirements($validator): void
    {
        if ($this->input('customer_type') === 'business') {
            if (empty($this->input('legal_name'))) {
                $validator->errors()->add('legal_name', 
                    'Legal name is required for business customers');
            }
        }
    }

    private function validateCreditLimitChanges($validator): void
    {
        $newCreditLimit = $this->input('credit_limit');
        $customerId = $this->route('customer');
        
        if ($newCreditLimit !== null) {
            $customer = Customer::find($customerId);
            $currentLimit = $customer?->credit_limit ?? 0;
            
            // Require higher permissions for significant credit limit increases
            if ($newCreditLimit > $currentLimit * 1.5 && $newCreditLimit > 25000) {
                if (!$this->hasCompanyPermission('customers.high_credit_limit')) {
                    $validator->errors()->add('credit_limit', 
                        'You do not have permission to increase credit limits above $25,000 or by more than 50%');
                }
            }
        }
    }

    private function validatePrimaryContact($validator): void
    {
        $contacts = $this->input('contacts', []);
        
        // Filter out contacts marked for deletion
        $activeContacts = collect($contacts)->filter(fn($contact) => !($contact['_destroy'] ?? false));
        
        if ($activeContacts->isNotEmpty()) {
            $primaryContacts = $activeContacts->filter(fn($contact) => ($contact['is_primary'] ?? false));
            
            if ($primaryContacts->isEmpty()) {
                $validator->errors()->add('contacts', 
                    'At least one contact must be marked as primary');
            }
            
            if ($primaryContacts->count() > 1) {
                $validator->errors()->add('contacts', 
                    'Only one contact can be marked as primary');
            }
        }
    }

    private function validateDeactivationRules($validator): void
    {
        if ($this->boolean('is_active') === false) {
            $customerId = $this->route('customer');
            
            // Check if customer has outstanding balances
            $customer = Customer::find($customerId);
            if ($customer && $customer->total_outstanding > 0) {
                $validator->errors()->add('is_active', 
                    'Cannot deactivate customer with outstanding balance of ' . 
                    number_format($customer->total_outstanding, 2));
            }
            
            // Check if customer has unpaid invoices
            $unpaidInvoices = \App\Models\Acct\Invoice::where('customer_id', $customerId)
                ->where('company_id', $this->getCurrentCompanyId())
                ->whereIn('status', ['sent', 'partial'])
                ->count();
                
            if ($unpaidInvoices > 0) {
                $validator->errors()->add('is_active', 
                    'Cannot deactivate customer with unpaid invoices');
            }
        }
    }

    /**
     * Get the customer being updated
     */
    public function getCustomer(): ?Customer
    {
        $customerId = $this->route('customer');
        
        return Customer::where('company_id', $this->getCurrentCompanyId())
            ->where('id', $customerId)
            ->first();
    }

    /**
     * Get contacts to be created, updated, or deleted
     */
    public function getContactsData(): array
    {
        $contacts = $this->input('contacts', []);
        
        return [
            'to_create' => collect($contacts)->filter(fn($contact) => !isset($contact['id']) && !($contact['_destroy'] ?? false))->toArray(),
            'to_update' => collect($contacts)->filter(fn($contact) => isset($contact['id']) && !($contact['_destroy'] ?? false))->toArray(),
            'to_delete' => collect($contacts)->filter(fn($contact) => isset($contact['id']) && ($contact['_destroy'] ?? false))->pluck('id')->toArray(),
        ];
    }
}