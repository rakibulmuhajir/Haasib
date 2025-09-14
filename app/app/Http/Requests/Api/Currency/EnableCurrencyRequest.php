<?php

namespace App\Http\Requests\Api\Currency;

use Illuminate\Foundation\Http\FormRequest;

class EnableCurrencyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency_code.required' => 'Currency code is required',
            'currency_code.size' => 'Currency code must be 3 characters',
            'currency_code.exists' => 'Currency code does not exist',
        ];
    }
}
