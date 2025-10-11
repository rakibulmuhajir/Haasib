<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = $this->route('company');
        
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('auth.companies', 'name')->ignore($company->id),
            ],
            'industry' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::in(['hospitality', 'retail', 'professional_services', 'technology', 'healthcare', 'education', 'manufacturing', 'other']),
            ],
            'country' => [
                'sometimes',
                'required',
                'string',
                'size:2',
                'exists:countries,code',
            ],
            'base_currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
            'timezone' => [
                'nullable',
                'string',
                'max:50',
                'timezone:all',
            ],
            'language' => [
                'nullable',
                'string',
                'max:10',
                'exists:languages,code',
            ],
            'locale' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[a-z]{2}_[A-Z]{2}$/',
            ],
            'settings' => [
                'nullable',
                'array',
            ],
            'settings.*' => [
                'string',
            ],
            'is_active' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Company name is required.',
            'name.unique' => 'A company with this name already exists.',
            'industry.required' => 'Industry is required.',
            'industry.in' => 'Invalid industry selected.',
            'country.required' => 'Country is required.',
            'country.exists' => 'Invalid country selected.',
            'base_currency.required' => 'Base currency is required.',
            'base_currency.exists' => 'Invalid currency selected.',
            'timezone.timezone' => 'Invalid timezone selected.',
            'language.exists' => 'Invalid language selected.',
            'locale.regex' => 'Locale must be in format: en_US',
            'is_active.required' => 'Status is required.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name') && !$this->has('slug')) {
            $this->merge([
                'slug' => $this->generateSlug($this->input('name')),
            ]);
        }

        if ($this->has('settings')) {
            $this->merge([
                'settings' => $this->input('settings', []),
            ]);
        }
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}