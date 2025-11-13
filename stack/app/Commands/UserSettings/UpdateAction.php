<?php

namespace App\Commands\UserSettings;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\User;
use App\Models\Company;
use App\Models\TaxSettings;
use Illuminate\Support\Facades\DB;
use Exception;

class UpdateAction extends BaseCommand
{
    public function handle(): array
    {
        return $this->executeInTransaction(function () {
            $userId = $this->context->getUserId();
            $companyId = $this->context->getCompanyId();
            
            if (!$userId) {
                throw new Exception('Invalid service context: missing user ID');
            }

            // Find and validate user
            $user = User::findOrFail($userId);
            $company = $companyId ? Company::find($companyId) : null;

            $settings = $this->getValue('settings', []);
            $updatedSettings = [];

            // Process each setting
            foreach ($settings as $setting) {
                $group = $setting['group'];
                $key = $setting['key'];
                $value = $setting['value'];

                $result = $this->updateSingleSetting($user, $company, $group, $key, $value);
                $updatedSettings[] = $result;
            }

            $this->audit('user_settings.updated', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'updated_settings_count' => count($updatedSettings),
                'settings_updated' => array_column($updatedSettings, 'key'),
            ]);

            return [
                'success' => true,
                'message' => 'Settings updated successfully',
                'updated_settings' => $updatedSettings,
                'all_settings' => $this->getAllSettings($user, $company),
            ];
        });
    }

    private function updateSingleSetting(User $user, ?Company $company, string $group, string $key, $value): array
    {
        $originalValue = $this->getOriginalValue($user, $company, $group, $key);

        switch ($group) {
            case 'user':
                $this->updateUserSetting($user, $key, $value);
                break;

            case 'company':
                if (!$company) {
                    throw new Exception('No active company selected for company settings');
                }
                $this->updateCompanySetting($company, $key, $value);
                break;

            case 'tax':
                if (!$company) {
                    throw new Exception('No active company selected for tax settings');
                }
                $this->updateTaxSetting($company->id, $key, $value);
                break;

            default:
                throw new Exception("Invalid setting group: {$group}");
        }

        // Log individual setting change
        $this->audit('setting.updated', [
            'user_id' => $user->id,
            'company_id' => $company?->id,
            'setting_group' => $group,
            'setting_key' => $key,
            'original_value' => $originalValue,
            'new_value' => $value,
        ]);

        return [
            'group' => $group,
            'key' => $key,
            'value' => $value,
            'original_value' => $originalValue,
        ];
    }

    private function updateUserSetting(User $user, string $key, $value): void
    {
        $allowedKeys = [
            'locale', 'timezone', 'date_format', 'time_format',
            'currency_format', 'number_format', 'theme',
            'email_notifications', 'push_notifications', 'dashboard_layout',
            'preferred_company_id',
        ];

        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid user setting key: {$key}");
        }

        // Validate specific settings
        $this->validateUserSetting($key, $value);

        $user->update([$key => $value]);
    }

    private function updateCompanySetting(Company $company, string $key, $value): void
    {
        $allowedKeys = [
            'name', 'currency_code', 'timezone', 'date_format',
            'fiscal_year_start', 'fiscal_year_end', 'address',
            'phone', 'email', 'website', 'logo', 'tax_id',
            'registration_number',
        ];

        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid company setting key: {$key}");
        }

        // Validate company settings
        $this->validateCompanySetting($key, $value);

        $company->update([$key => $value]);
    }

    private function updateTaxSetting(string $companyId, string $key, $value): void
    {
        $taxSettings = TaxSettings::firstOrCreate(['company_id' => $companyId]);

        $allowedKeys = [
            'tax_inclusive_pricing', 'round_tax_per_line', 'allow_compound_tax',
            'rounding_precision', 'tax_registration_number', 'vat_number',
            'tax_country_code', 'default_reporting_frequency', 'auto_file_tax_returns',
            'tax_year_end_month', 'tax_year_end_day', 'calculate_sales_tax',
            'charge_tax_on_shipping', 'tax_exempt_customers', 'default_sales_tax_rate_id',
            'calculate_purchase_tax', 'track_input_tax', 'default_purchase_tax_rate_id',
            'auto_calculate_tax', 'validate_tax_rates', 'track_tax_by_jurisdiction',
        ];

        if (!in_array($key, $allowedKeys)) {
            throw new Exception("Invalid tax setting key: {$key}");
        }

        // Validate tax settings
        $this->validateTaxSetting($key, $value);

        $taxSettings->update([$key => $value]);
    }

    private function validateUserSetting(string $key, $value): void
    {
        switch ($key) {
            case 'locale':
                $allowedLocales = ['en', 'es', 'fr', 'de', 'it', 'pt', 'zh', 'ja'];
                if (!in_array($value, $allowedLocales)) {
                    throw new Exception("Invalid locale: {$value}");
                }
                break;

            case 'timezone':
                $allowedTimezones = [
                    'UTC', 'America/New_York', 'America/Chicago', 
                    'America/Denver', 'America/Los_Angeles',
                    'Europe/London', 'Europe/Paris', 'Europe/Berlin',
                    'Asia/Tokyo', 'Asia/Shanghai', 'Australia/Sydney'
                ];
                if (!in_array($value, $allowedTimezones)) {
                    throw new Exception("Invalid timezone: {$value}");
                }
                break;

            case 'theme':
                if (!in_array($value, ['light', 'dark', 'system'])) {
                    throw new Exception("Invalid theme: {$value}");
                }
                break;

            case 'date_format':
                $allowedFormats = ['Y-m-d', 'm/d/Y', 'd/m/Y', 'F j, Y', 'j F Y'];
                if (!in_array($value, $allowedFormats)) {
                    throw new Exception("Invalid date format: {$value}");
                }
                break;

            case 'time_format':
                if (!in_array($value, ['12h', '24h'])) {
                    throw new Exception("Invalid time format: {$value}");
                }
                break;
        }
    }

    private function validateCompanySetting(string $key, $value): void
    {
        switch ($key) {
            case 'currency_code':
                $allowedCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'CHF', 'SEK'];
                if (!in_array($value, $allowedCurrencies)) {
                    throw new Exception("Invalid currency code: {$value}");
                }
                break;

            case 'fiscal_year_start':
            case 'fiscal_year_end':
                if (!preg_match('/^\d{2}-\d{2}$/', $value)) {
                    throw new Exception("Invalid fiscal year date format: {$value}. Use MM-DD format.");
                }
                break;
        }
    }

    private function validateTaxSetting(string $key, $value): void
    {
        switch ($key) {
            case 'rounding_precision':
                if (!is_numeric($value) || $value < 0 || $value > 10) {
                    throw new Exception("Rounding precision must be a number between 0 and 10");
                }
                break;

            case 'tax_year_end_month':
                if (!is_numeric($value) || $value < 1 || $value > 12) {
                    throw new Exception("Tax year end month must be between 1 and 12");
                }
                break;

            case 'tax_year_end_day':
                if (!is_numeric($value) || $value < 1 || $value > 31) {
                    throw new Exception("Tax year end day must be between 1 and 31");
                }
                break;
        }
    }

    private function getOriginalValue(User $user, ?Company $company, string $group, string $key)
    {
        switch ($group) {
            case 'user':
                return $user->$key;
            
            case 'company':
                return $company?->$key;
            
            case 'tax':
                if ($company) {
                    $taxSettings = TaxSettings::where('company_id', $company->id)->first();
                    return $taxSettings?->$key;
                }
                return null;
            
            default:
                return null;
        }
    }

    private function getAllSettings(User $user, ?Company $company): array
    {
        $userSettings = [
            'locale' => $user->locale ?? 'en',
            'timezone' => $user->timezone ?? 'UTC',
            'date_format' => $user->date_format ?? 'Y-m-d',
            'time_format' => $user->time_format ?? '12h',
            'currency_format' => $user->currency_format ?? 'symbol',
            'number_format' => $user->number_format ?? '1,234.56',
            'theme' => $user->theme ?? 'light',
            'email_notifications' => $user->email_notifications ?? true,
            'push_notifications' => $user->push_notifications ?? true,
            'dashboard_layout' => $user->dashboard_layout ?? 'default',
            'preferred_company_id' => $user->preferred_company_id,
        ];

        $companySettings = $company ? [
            'name' => $company->name,
            'currency_code' => $company->currency_code ?? 'USD',
            'timezone' => $company->timezone ?? 'UTC',
            'date_format' => $company->date_format ?? 'Y-m-d',
            'fiscal_year_start' => $company->fiscal_year_start,
            'fiscal_year_end' => $company->fiscal_year_end,
            'address' => $company->address,
            'phone' => $company->phone,
            'email' => $company->email,
            'website' => $company->website,
            'logo' => $company->logo,
            'tax_id' => $company->tax_id,
            'registration_number' => $company->registration_number,
        ] : null;

        $taxSettings = $company ? $this->getTaxSettings($company->id) : null;

        return [
            'user' => $userSettings,
            'company' => $companySettings,
            'tax' => $taxSettings,
        ];
    }

    private function getTaxSettings(string $companyId): ?array
    {
        $taxSettings = TaxSettings::where('company_id', $companyId)->first();

        if (!$taxSettings) {
            return null;
        }

        return [
            'tax_inclusive_pricing' => $taxSettings->tax_inclusive_pricing,
            'round_tax_per_line' => $taxSettings->round_tax_per_line,
            'allow_compound_tax' => $taxSettings->allow_compound_tax,
            'rounding_precision' => $taxSettings->rounding_precision,
            'tax_registration_number' => $taxSettings->tax_registration_number,
            'vat_number' => $taxSettings->vat_number,
            'tax_country_code' => $taxSettings->tax_country_code,
            'default_reporting_frequency' => $taxSettings->default_reporting_frequency,
            'auto_file_tax_returns' => $taxSettings->auto_file_tax_returns,
            'tax_year_end_month' => $taxSettings->tax_year_end_month,
            'tax_year_end_day' => $taxSettings->tax_year_end_day,
            'calculate_sales_tax' => $taxSettings->calculate_sales_tax,
            'charge_tax_on_shipping' => $taxSettings->charge_tax_on_shipping,
            'tax_exempt_customers' => $taxSettings->tax_exempt_customers,
            'default_sales_tax_rate_id' => $taxSettings->default_sales_tax_rate_id,
            'calculate_purchase_tax' => $taxSettings->calculate_purchase_tax,
            'track_input_tax' => $taxSettings->track_input_tax,
            'default_purchase_tax_rate_id' => $taxSettings->default_purchase_tax_rate_id,
            'auto_calculate_tax' => $taxSettings->auto_calculate_tax,
            'validate_tax_rates' => $taxSettings->validate_tax_rates,
            'track_tax_by_jurisdiction' => $taxSettings->track_tax_by_jurisdiction,
        ];
    }
}