<?php

namespace App\Http\Requests\UserSettings;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateUserSettingsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        // Users can update their own settings
        return $this->user()->id === $this->route('user')?->id;
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array',
            'settings.*.key' => [
                'required',
                'string',
                Rule::in($this->getAllowedUserSettingKeys())
            ],
            'settings.*.value' => 'required',
            'settings.*.group' => [
                'required',
                'string',
                Rule::in(['user', 'company', 'tax'])
            ],
            
            // Direct update validation
            'key' => [
                'required_without:settings',
                'string',
                Rule::in($this->getAllowedUserSettingKeys())
            ],
            'value' => 'required_without:settings',
            'group' => [
                'required_without:settings',
                'string',
                Rule::in(['user', 'company', 'tax'])
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.required' => 'Settings array is required',
            'settings.*.key.required' => 'Setting key is required',
            'settings.*.key.in' => 'Invalid setting key provided',
            'settings.*.value.required' => 'Setting value is required',
            'settings.*.group.required' => 'Setting group is required',
            'settings.*.group.in' => 'Setting group must be one of: user, company, tax',
            'key.required_without' => 'Setting key is required when not using settings array',
            'key.in' => 'Invalid setting key provided',
            'value.required_without' => 'Setting value is required when not using settings array',
            'group.required_without' => 'Setting group is required when not using settings array',
            'group.in' => 'Setting group must be one of: user, company, tax',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate company-specific settings require active company
            if ($this->hasCompanySettings()) {
                $company = $this->user()->currentCompany();
                if (!$company) {
                    $validator->errors()->add('company_context', 
                        'Company settings require an active company context');
                }
            }

            // Validate user permission for company updates
            if ($this->hasCompanySettings() && $this->user()->currentCompany()) {
                if (!$this->user()->can('company.settings.update')) {
                    $validator->errors()->add('permission', 
                        'You do not have permission to update company settings');
                }
            }

            // Validate user permission for tax settings
            if ($this->hasTaxSettings() && $this->user()->currentCompany()) {
                if (!$this->user()->can('tax.settings.update')) {
                    $validator->errors()->add('permission', 
                        'You do not have permission to update tax settings');
                }
            }
        });
    }

    private function getAllowedUserSettingKeys(): array
    {
        return [
            // User settings
            'locale', 'timezone', 'date_format', 'time_format',
            'currency_format', 'number_format', 'theme',
            'email_notifications', 'push_notifications', 'dashboard_layout',
            'preferred_company_id',
            
            // Company settings (if user has permission)
            'name', 'currency_code', 'timezone', 'date_format',
            'fiscal_year_start', 'fiscal_year_end', 'address',
            'phone', 'email', 'website', 'logo', 'tax_id',
            'registration_number',
            
            // Tax settings (if user has permission)
            'tax_inclusive_pricing', 'round_tax_per_line', 'allow_compound_tax',
            'rounding_precision', 'tax_registration_number', 'vat_number',
            'tax_country_code', 'default_reporting_frequency', 'auto_file_tax_returns',
            'tax_year_end_month', 'tax_year_end_day', 'calculate_sales_tax',
            'charge_tax_on_shipping', 'tax_exempt_customers', 'default_sales_tax_rate_id',
            'calculate_purchase_tax', 'track_input_tax', 'default_purchase_tax_rate_id',
            'auto_calculate_tax', 'validate_tax_rates', 'track_tax_by_jurisdiction',
        ];
    }

    private function hasCompanySettings(): bool
    {
        $settings = $this->input('settings', []);
        
        if (!empty($settings)) {
            return collect($settings)->contains('group', 'company');
        }
        
        return $this->input('group') === 'company';
    }

    private function hasTaxSettings(): bool
    {
        $settings = $this->input('settings', []);
        
        if (!empty($settings)) {
            return collect($settings)->contains('group', 'tax');
        }
        
        return $this->input('group') === 'tax';
    }
}