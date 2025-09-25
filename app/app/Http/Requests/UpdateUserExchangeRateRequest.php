<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserExchangeRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'currency_id' => ['required_without:context.currencyId', 'uuid', 'exists:currencies,id'],
            'context.currencyId' => ['required_without:currency_id', 'uuid', 'exists:currencies,id'],
            'context.exchange_rate' => ['required_without:exchange_rate', 'numeric', 'gt:0'],
            'exchange_rate' => ['required_without:context.exchange_rate', 'numeric', 'gt:0'],
            'context.effectiveDate' => ['required_without:effective_date', 'date'],
            'effective_date' => ['required_without:context.effectiveDate', 'date'],
            'context.ceaseDate' => ['nullable', 'date', 'after:context.effectiveDate'],
            'cease_date' => ['nullable', 'date', 'after:effective_date'],
            'context.notes' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the custom error messages for the request.
     */
    public function messages(): array
    {
        return [
            'currency_id.required' => 'The currency field is required.',
            'currency_id.exists' => 'The selected currency is invalid.',
            'exchange_rate.required' => 'The exchange rate is required.',
            'exchange_rate.gt' => 'The exchange rate must be greater than 0.',
            'cease_date.after' => 'The cease date must be after the effective date.',
        ];
    }
}
