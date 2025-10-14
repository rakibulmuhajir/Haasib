<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        $customerId = $this->route('id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'state' => ['sometimes', 'nullable', 'string', 'max:100'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'customer_code' => ['sometimes', 'nullable', 'string', 'max:50', "unique:customers,customer_code,{$customerId}"],
            'customer_type' => ['sometimes', 'nullable', 'string', 'in:individual,business,organization,government'],
            'tax_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'payment_terms' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency_id' => ['sometimes', 'nullable', 'string', 'exists:currencies,id'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'nullable', 'string', 'in:active,inactive,archived'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Customer name is required',
            'name.max' => 'Customer name cannot exceed 255 characters',
            'email.email' => 'Invalid email format',
            'email.max' => 'Email cannot exceed 255 characters',
            'phone.max' => 'Phone number cannot exceed 50 characters',
            'address.max' => 'Address cannot exceed 500 characters',
            'city.max' => 'City cannot exceed 100 characters',
            'state.max' => 'State cannot exceed 100 characters',
            'country.max' => 'Country cannot exceed 100 characters',
            'postal_code.max' => 'Postal code cannot exceed 20 characters',
            'customer_code.max' => 'Customer code cannot exceed 50 characters',
            'customer_code.unique' => 'Customer code already exists',
            'customer_type.in' => 'Invalid customer type',
            'tax_id.max' => 'Tax ID cannot exceed 100 characters',
            'registration_number.max' => 'Registration number cannot exceed 100 characters',
            'website.url' => 'Invalid website URL',
            'website.max' => 'Website cannot exceed 255 characters',
            'payment_terms.integer' => 'Payment terms must be a number',
            'payment_terms.min' => 'Payment terms cannot be negative',
            'payment_terms.max' => 'Payment terms cannot exceed 365 days',
            'credit_limit.numeric' => 'Credit limit must be a number',
            'credit_limit.min' => 'Credit limit cannot be negative',
            'currency_id.exists' => 'Selected currency does not exist',
            'notes.max' => 'Notes cannot exceed 2000 characters',
            'status.in' => 'Invalid status',
        ];
    }
}
