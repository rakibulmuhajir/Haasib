<?php

namespace App\Http\Requests\Api\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'string', 'exists:customers,customer_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'string', 'in:bank_transfer,cash,check,credit_card,debit_card,other'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'auto_allocate' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required',
            'customer_id.exists' => 'Selected customer does not exist',
            'amount.required' => 'Payment amount is required',
            'amount.numeric' => 'Payment amount must be a number',
            'amount.min' => 'Payment amount must be greater than 0',
            'payment_method.required' => 'Payment method is required',
            'payment_method.in' => 'Invalid payment method selected',
            'payment_reference.max' => 'Payment reference cannot exceed 100 characters',
            'payment_date.required' => 'Payment date is required',
            'payment_date.date' => 'Invalid payment date format',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future',
            'currency_id.exists' => 'Selected currency does not exist',
            'exchange_rate.numeric' => 'Exchange rate must be a number',
            'exchange_rate.min' => 'Exchange rate must be positive',
            'notes.max' => 'Notes cannot exceed 1000 characters',
        ];
    }
}
