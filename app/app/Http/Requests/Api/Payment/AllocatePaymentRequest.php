<?php

namespace App\Http\Requests\Api\Payment;

use Illuminate\Foundation\Http\FormRequest;

class AllocatePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'string', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'allocation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice is required',
            'invoice_id.exists' => 'Selected invoice does not exist',
            'amount.required' => 'Allocation amount is required',
            'amount.numeric' => 'Allocation amount must be a number',
            'amount.min' => 'Allocation amount must be greater than 0',
            'allocation_date.date' => 'Invalid allocation date format',
            'allocation_date.before_or_equal' => 'Allocation date cannot be in the future',
            'notes.max' => 'Notes cannot exceed 500 characters',
        ];
    }
}
