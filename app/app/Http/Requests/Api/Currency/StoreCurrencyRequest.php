<?php

namespace App\Http\Requests\Api\Currency;

use Illuminate\Foundation\Http\FormRequest;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('currency.crud');
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'numeric_code' => ['required', 'integer', 'unique:currencies,numeric_code'],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => ['required', 'string', 'max:10'],
            'symbol_position' => ['required', 'in:before,after'],
            'minor_unit' => ['required', 'integer', 'min:0', 'max:4'],
            'thousands_separator' => ['required', 'string', 'max:5'],
            'decimal_separator' => ['required', 'string', 'max:5'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
        ];
    }
}
