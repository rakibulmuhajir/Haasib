<?php

namespace Modules\Accounting\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use App\Models\CompanyCurrency;
use Illuminate\Validation\Rule;
use Modules\Accounting\Models\Customer;

class UpdateCustomerRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->hasCompanyPermission('acct.customers.update') &&
               $this->validateRlsContext();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = session('active_company_id');
        $customerId = $this->route('customer')?->id;
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'min:2'
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255'
            ],
            'preferred_currency_code' => [
                'sometimes',
                'nullable',
                'string',
                'size:3'
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['active', 'inactive', 'suspended'])
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20'
            ],
            'website' => [
                'sometimes',
                'nullable',
                'url',
                'max:255'
            ],
            'notes' => [
                'sometimes',
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
            'name' => 'customer name',
            'email' => 'email address',
            'preferred_currency_code' => 'preferred currency',
            'status' => 'status',
            'phone' => 'phone number',
            'website' => 'website',
            'notes' => 'notes',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
            'name.min' => 'Customer name must be at least 2 characters',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email address is already used by another customer',
            'preferred_currency_code.exists' => 'The selected currency is not available for your company',
            'preferred_currency_code.size' => 'Currency code must be exactly 3 characters',
            'status.in' => 'Status must be active, inactive, or suspended',
            'website.url' => 'Please provide a valid website URL',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $companyId = session('active_company_id');
            $customerId = $this->route('customer')?->id;
            
            // Check email uniqueness for schema-prefixed table
            if ($this->has('email') && $this->email) {
                $existingEmail = Customer::where('company_id', $companyId)
                    ->where('email', $this->email)
                    ->where('id', '!=', $customerId)
                    ->whereNull('deleted_at')
                    ->first();
                    
                if ($existingEmail) {
                    $validator->errors()->add('email', 'This email address is already used by another customer.');
                }
            }
            
            // Check currency exists and is active
            if ($this->has('preferred_currency_code') && $this->preferred_currency_code) {
                $currency = CompanyCurrency::where('company_id', $companyId)
                    ->where('currency_code', $this->preferred_currency_code)
                    ->where('is_active', true)
                    ->first();
                    
                if (!$currency) {
                    $validator->errors()->add('preferred_currency_code', 'The selected currency is not available for your company.');
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
            'name' => trim($this->name ?? ''),
            'email' => trim($this->email ?? '') ?: null,
            'preferred_currency_code' => strtoupper($this->preferred_currency_code ?? '') ?: null,
            'phone' => trim($this->phone ?? '') ?: null,
            'website' => trim($this->website ?? '') ?: null,
            'notes' => trim($this->notes ?? '') ?: null,
        ]);
    }
}