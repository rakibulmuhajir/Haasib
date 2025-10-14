<?php

namespace App\Http\Requests\Api\Currency;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExchangeRateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'rate' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['nullable', 'date', 'before_or_equal:today'],
            'source' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_currency.required' => 'From currency is required',
            'from_currency.size' => 'From currency must be 3 characters',
            'to_currency.required' => 'To currency is required',
            'to_currency.size' => 'To currency must be 3 characters',
            'rate.required' => 'Exchange rate is required',
            'rate.numeric' => 'Exchange rate must be a number',
            'rate.min' => 'Exchange rate must be positive',
            'effective_date.date' => 'Invalid effective date format',
            'effective_date.before_or_equal' => 'Effective date cannot be in the future',
            'source.max' => 'Source cannot exceed 50 characters',
        ];
    }
}
