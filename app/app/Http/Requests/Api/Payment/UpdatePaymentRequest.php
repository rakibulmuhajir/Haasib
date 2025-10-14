<?php

namespace App\Http\Requests\Api\Payment;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'payment_method' => ['sometimes', 'required', 'string', 'in:bank_transfer,cash,check,credit_card,debit_card,other'],
            'payment_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
            'payment_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method selected',
            'payment_reference.max' => 'Payment reference cannot exceed 100 characters',
            'payment_date.required' => 'Payment date is required',
            'payment_date.date' => 'Invalid payment date format',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future',
            'notes.max' => 'Notes cannot exceed 1000 characters',
        ];
    }
}
