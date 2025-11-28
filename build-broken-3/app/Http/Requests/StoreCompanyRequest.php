<?php

namespace App\Http\Requests;

use App\Constants\Permissions;

class StoreCompanyRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Anyone authenticated can create their first company
        // This is a global operation, not company-scoped
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', 'unique:auth.companies,slug'],
            'country' => ['required', 'string', 'size:2'], // 2-letter country code
            'currency' => ['required', 'string', 'size:3'], // 3-letter currency code
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug must only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This company slug is already taken.',
        ];
    }
}