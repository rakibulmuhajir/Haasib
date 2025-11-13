<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CreateCustomerRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('customers.create') &&
               $this->validateRlsContext();
    }

    public function rules(): array
    {
        return [
            // Basic customer information
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                'regex:/^[\pL\s\-\',\.&]+$/u', // Allow letters, spaces, hyphens, apostrophes, periods, ampersands
            ],
            'legal_name' => 'nullable|string|max:255',
            'customer_type' => [
                'required',
                'string',
                Rule::in(['individual', 'business', 'non_profit', 'government']),
            ],
            'tax_id' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:100',

            // Contact information
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('acct.customers', 'email')->where(function ($query) {
                    $query->where('company_id', $this->getCurrentCompanyId())
                        ->whereNull('deleted_at');
                }),
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
            'credit_limit' => 'nullable|numeric|min:0|max:999999999.99',
            'payment_terms' => 'nullable|integer|min:0|max:365',
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code'),
            ],

            // Additional settings
            'is_active' => 'boolean',
            'is_tax_exempt' => 'boolean',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'string|max:50',

            // Contacts (optional array)
            'contacts' => 'nullable|array|max:5',
            'contacts.*.name' => 'required_with:contacts|string|max:255',
            'contacts.*.email' => 'nullable|required_with:contacts.*.name|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.role' => 'nullable|string|max:100',
            'contacts.*.is_primary' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            // Basic information
            'name.required' => 'Customer name is required',
            'name.min' => 'Customer name must be at least 2 characters',
            'name.max' => 'Customer name cannot exceed 255 characters',
            'name.regex' => 'Customer name can only contain letters, spaces, hyphens, apostrophes, periods, and ampersands',
            'customer_type.required' => 'Customer type is required',
            'customer_type.in' => 'Customer type must be one of: individual, business, non_profit, or government',

            // Contact information
            'email.required' => 'Email address is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already registered in your company',
            'phone.max' => 'Phone number cannot exceed 50 characters',
            'website.url' => 'Please provide a valid website URL',

            // Billing address
            'billing_address.required' => 'Billing address is required',
            'billing_address.line1.required' => 'Billing address line 1 is required',
            'billing_address.city.required' => 'Billing city is required',
            'billing_address.state.required' => 'Billing state is required',
            'billing_address.postal_code.required' => 'Billing postal code is required',
            'billing_address.country.required' => 'Billing country is required',
            'billing_address.country.size' => 'Country must be a 2-letter country code',

            // Shipping address
            'shipping_address.line1.max' => 'Shipping address line 1 cannot exceed 255 characters',
            'shipping_address.city.max' => 'Shipping city cannot exceed 100 characters',
            'shipping_address.state.max' => 'Shipping state cannot exceed 100 characters',
            'shipping_address.postal_code.max' => 'Shipping postal code cannot exceed 20 characters',
            'shipping_address.country.size' => 'Country must be a 2-letter country code',

            // Credit and payment settings
            'credit_limit.min' => 'Credit limit cannot be negative',
            'credit_limit.max' => 'Credit limit cannot exceed 999,999,999.99',
            'payment_terms.min' => 'Payment terms cannot be negative',
            'payment_terms.max' => 'Payment terms cannot exceed 365 days',
            'currency.required' => 'Currency is required',
            'currency.exists' => 'Invalid currency selected',

            // Additional settings
            'notes.max' => 'Notes cannot exceed 2000 characters',
            'tags.max' => 'Cannot add more than 10 tags',
            'tags.*.max' => 'Each tag cannot exceed 50 characters',

            // Contacts
            'contacts.max' => 'Cannot add more than 5 contacts',
            'contacts.*.name.required_with' => 'Contact name is required when adding contacts',
            'contacts.*.name.max' => 'Contact name cannot exceed 255 characters',
            'contacts.*.email.required_with' => 'Contact email is required when contact name is provided',
            'contacts.*.email.email' => 'Please provide a valid contact email',
            'contacts.*.phone.max' => 'Contact phone cannot exceed 50 characters',
            'contacts.*.role.max' => 'Contact role cannot exceed 100 characters',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that at least one contact is primary if contacts are provided
            $this->validatePrimaryContact($validator);

            // Validate business customer requirements
            $this->validateBusinessCustomerRequirements($validator);

            // Validate shipping address format if different from billing
            $this->validateShippingAddress($validator);

            // Validate credit limits based on user permissions
            $this->validateCreditLimitPermissions($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
            'is_tax_exempt' => $this->boolean('is_tax_exempt', false),

            // Set shipping address to billing address if not provided
            'shipping_address' => $this->input('shipping_address') ?: $this->input('billing_address'),
        ]);
    }

    private function validatePrimaryContact($validator): void
    {
        $contacts = $this->input('contacts', []);

        if (! empty($contacts)) {
            $primaryContacts = collect($contacts)->filter(fn ($contact) => ($contact['is_primary'] ?? false));

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

    private function validateBusinessCustomerRequirements($validator): void
    {
        if ($this->input('customer_type') === 'business') {
            if (empty($this->input('legal_name'))) {
                $validator->errors()->add('legal_name',
                    'Legal name is required for business customers');
            }
        }
    }

    private function validateShippingAddress($validator): void
    {
        $billingAddress = $this->input('billing_address', []);
        $shippingAddress = $this->input('shipping_address', []);

        // If shipping address is different from billing, validate it's complete
        if ($shippingAddress && $this->addressesAreDifferent($billingAddress, $shippingAddress)) {
            $requiredFields = ['line1', 'city', 'state', 'postal_code', 'country'];

            foreach ($requiredFields as $field) {
                if (empty($shippingAddress[$field])) {
                    $validator->errors()->add("shipping_address.{$field}",
                        "Shipping address {$field} is required when different from billing address");
                }
            }
        }
    }

    private function validateCreditLimitPermissions($validator): void
    {
        $creditLimit = $this->input('credit_limit');

        if ($creditLimit !== null && $creditLimit > 10000) {
            // Require higher permissions for large credit limits
            if (! $this->hasCompanyPermission('customers.high_credit_limit')) {
                $validator->errors()->add('credit_limit',
                    'You do not have permission to set credit limits above $10,000');
            }
        }
    }

    private function addressesAreDifferent(array $address1, array $address2): bool
    {
        $fields = ['line1', 'line2', 'city', 'state', 'postal_code', 'country'];

        foreach ($fields as $field) {
            if (($address1[$field] ?? '') !== ($address2[$field] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
