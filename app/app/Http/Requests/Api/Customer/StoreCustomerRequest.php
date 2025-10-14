<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'customer_code' => ['nullable', 'string', 'max:50', 'unique:customers,customer_code'],
            'customer_type' => ['nullable', 'string', 'in:individual,business,organization,government'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'url', 'max:255'],
            'payment_terms' => ['nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:active,inactive,archived'],
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
