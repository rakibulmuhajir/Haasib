<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:auth.companies,name',
            ],
            'industry' => [
                'required',
                'string',
                'max:100',
                Rule::in(['hospitality', 'retail', 'professional_services', 'technology', 'healthcare', 'education', 'manufacturing', 'other']),
            ],
            'slug' => [
                'required',
                'string',
                'max:100',
                'unique:auth.companies,slug',
                'regex:/^[a-z0-9-]+$/',
            ],
            'country' => [
                'required',
                'string',
                'size:2',
                'exists:countries,code',
            ],
            'base_currency' => [
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
            'auto_setup' => [
                'nullable',
                'boolean',
            ],
            'create_fiscal_year' => [
                'nullable',
                'boolean',
            ],
            'fiscal_year_start' => [
                'nullable',
                'required_if:create_fiscal_year,true',
                'date',
                'before_or_equal:fiscal_year_end',
            ],
            'fiscal_year_end' => [
                'nullable',
                'required_if:create_fiscal_year,true',
                'date',
                'after_or_equal:fiscal_year_start',
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
            'slug.required' => 'Company slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, and hyphens.',
            'country.required' => 'Country is required.',
            'country.exists' => 'Invalid country selected.',
            'base_currency.required' => 'Base currency is required.',
            'base_currency.exists' => 'Invalid currency selected.',
            'timezone.timezone' => 'Invalid timezone selected.',
            'language.exists' => 'Invalid language selected.',
            'locale.regex' => 'Locale must be in format: en_US',
            'fiscal_year_start.required_if' => 'Fiscal year start date is required when creating fiscal year.',
            'fiscal_year_start.before_or_equal' => 'Fiscal year start must be before or equal to end date.',
            'fiscal_year_end.required_if' => 'Fiscal year end date is required when creating fiscal year.',
            'fiscal_year_end.after_or_equal' => 'Fiscal year end must be after or equal to start date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->generateSlug($this->input('name')),
            'settings' => $this->input('settings', []),
            'auto_setup' => $this->boolean('auto_setup', false),
            'create_fiscal_year' => $this->boolean('create_fiscal_year', true),
        ]);
    }

    private function generateSlug(string $name): string
    {
        if ($this->input('slug')) {
            return $this->input('slug');
        }

        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}