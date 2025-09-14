<?php

namespace App\Http\Requests\Api\Payment;

use Illuminate\Foundation\Http\FormRequest;

class BulkPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:delete,void,auto_allocate'],
            'payment_ids' => ['required', 'array', 'min:1'],
            'payment_ids.*' => ['string', 'exists:payments,id'],
            'reason' => ['required_if:action,delete,void', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'Action is required',
            'action.in' => 'Invalid action specified',
            'payment_ids.required' => 'Payment IDs are required',
            'payment_ids.array' => 'Payment IDs must be an array',
            'payment_ids.min' => 'At least one payment ID is required',
            'payment_ids.*.exists' => 'One or more payment IDs are invalid',
            'reason.required_if' => 'Reason is required for this action',
            'reason.min' => 'Reason must be at least 3 characters',
            'reason.max' => 'Reason cannot exceed 500 characters',
        ];
    }
}
