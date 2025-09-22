<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:190'],
            'base_currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'country' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'language' => ['nullable', 'string', 'exists:languages,code'],
            'locale' => ['nullable', 'string', 'exists:locales,code'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
