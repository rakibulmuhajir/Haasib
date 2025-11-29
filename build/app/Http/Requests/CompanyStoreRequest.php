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
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'uuid'],
            'base_currency' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
            'language' => ['nullable', 'string', 'max:10'],
            'locale' => ['nullable', 'string', 'max:10'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
