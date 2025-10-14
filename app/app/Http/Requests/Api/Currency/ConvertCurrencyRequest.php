<?php

namespace App\Http\Requests\Api\Currency;

use Illuminate\Foundation\Http\FormRequest;

class ConvertCurrencyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0'],
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
            'custom_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be positive',
            'from_currency.required' => 'From currency is required',
            'from_currency.size' => 'From currency must be 3 characters',
            'to_currency.required' => 'To currency is required',
            'to_currency.size' => 'To currency must be 3 characters',
            'date.date' => 'Invalid date format',
            'custom_rate.numeric' => 'Custom rate must be a number',
            'custom_rate.min' => 'Custom rate must be positive',
        ];
    }
}
