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
            'base_currency' => ['nullable', 'string', 'size:3', 'exists:currencies,code'],
            'language' => ['nullable', 'string', 'max:8', 'exists:languages,code'],
            'locale' => ['nullable', 'string', 'max:35', 'exists:locales,tag'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
