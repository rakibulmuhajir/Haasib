<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $company = $this->route('company');

        if (!$company) {
            return false;
        }

        // Check if user is member of this company
        return $user->companies()->where('company_id', $company->id)->exists();
    }

    public function rules(): array
    {
        $company = $this->route('company');

        return [
            'legal_name' => 'required|string|max:255',
            'display_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'tax_id' => 'nullable|string|max:50',
            'registration_number' => 'nullable|string|max:50',
            'industry' => 'nullable|string|max:100',
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|max:50',
            'date_format' => 'nullable|string|max:20',
            'fiscal_year_start' => 'required|date_format:m-d',
            'fiscal_year_end' => 'required|date_format:m-d',
            'logo' => 'nullable|image|max:2048',
            'settings' => 'nullable|array',
            'settings.invoice_prefix' => 'nullable|string|max:10',
            'settings.invoice_numbering' => 'nullable|in:sequential,yearly,monthly',
            'settings.default_payment_terms' => 'nullable|integer|min:0|max:365',
            'settings.tax_inclusive' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'legal_name.required' => 'Legal name is required',
            'display_name.required' => 'Display name is required',
            'email.email' => 'Please provide a valid email address',
            'website.url' => 'Please provide a valid website URL',
            'currency.required' => 'Currency is required',
            'currency.size' => 'Currency must be a 3-character code',
            'timezone.required' => 'Timezone is required',
            'fiscal_year_start.required' => 'Fiscal year start is required',
            'fiscal_year_start.date_format' => 'Fiscal year start must be in MM-DD format',
            'fiscal_year_end.required' => 'Fiscal year end is required',
            'fiscal_year_end.date_format' => 'Fiscal year end must be in MM-DD format',
            'logo.image' => 'Logo must be an image file',
            'logo.max' => 'Logo cannot exceed 2MB',
            'settings.invoice_numbering.in' => 'Invoice numbering must be one of: sequential, yearly, monthly',
            'settings.default_payment_terms.integer' => 'Default payment terms must be a number',
            'settings.default_payment_terms.min' => 'Default payment terms cannot be negative',
            'settings.default_payment_terms.max' => 'Default payment terms cannot exceed 365 days',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fiscalYearStart = $this->input('fiscal_year_start');
            $fiscalYearEnd = $this->input('fiscal_year_end');

            // Validate fiscal year dates
            if ($fiscalYearStart && $fiscalYearEnd) {
                if ($fiscalYearStart === $fiscalYearEnd) {
                    $validator->errors()->add('fiscal_year_end', 
                        'Fiscal year start and end dates cannot be the same');
                }
            }
        });
    }
}