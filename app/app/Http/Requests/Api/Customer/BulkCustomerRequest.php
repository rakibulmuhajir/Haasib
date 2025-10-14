<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class BulkCustomerRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:delete,activate,deactivate'],
            'customer_ids' => ['required', 'array', 'min:1'],
            'customer_ids.*' => ['string', 'exists:customers,customer_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Action is required',
            'action.in' => 'Invalid action specified',
            'customer_ids.required' => 'Customer IDs are required',
            'customer_ids.array' => 'Customer IDs must be an array',
            'customer_ids.min' => 'At least one customer ID is required',
            'customer_ids.*.exists' => 'One or more customer IDs are invalid',
        ];
    }
}
