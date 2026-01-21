<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $countryCodes = array_keys(config('countries', []));

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'industry_code' => ['required', 'string', 'exists:acct.industry_coa_packs,code'],
            'country' => ['required', 'string', 'size:2', Rule::in($countryCodes)],
            'country_id' => ['nullable', 'uuid'],
            'base_currency' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'timezone' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:10'],
            'locale' => ['nullable', 'string', 'max:10'],
            'settings' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'country.required' => 'Please select your country.',
            'country.in' => 'Please select a valid country from the list.',
            'industry_code.required' => 'Please select your industry.',
        ];
    }
}
