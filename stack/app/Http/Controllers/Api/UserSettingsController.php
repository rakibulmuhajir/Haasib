<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\TaxSettings;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get all user settings
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $this->getActiveCompany($request);

        $settings = [
            'user' => $this->getUserSettings($user),
            'company' => $company ? $this->getCompanySettings($company) : null,
            'tax' => $company ? $this->getTaxSettings($company->id) : null,
            'system' => $this->getSystemSettings(),
        ];

        return response()->json([
            'settings' => $settings,
            'active_company' => $company,
        ]);
    }

    /**
     * Get settings for a specific group
     */
    public function show(Request $request, string $group): JsonResponse
    {
        $user = $request->user();
        $company = $this->getActiveCompany($request);

        switch ($group) {
            case 'user':
                return response()->json([
                    'settings' => $this->getUserSettings($user),
                ]);

            case 'company':
                if (! $company) {
                    return response()->json([
                        'message' => 'No active company selected',
                    ], 404);
                }

                return response()->json([
                    'settings' => $this->getCompanySettings($company),
                ]);

            case 'tax':
                if (! $company) {
                    return response()->json([
                        'message' => 'No active company selected',
                    ], 404);
                }

                return response()->json([
                    'settings' => $this->getTaxSettings($company->id),
                ]);

            case 'system':
                return response()->json([
                    'settings' => $this->getSystemSettings(),
                ]);

            default:
                return response()->json([
                    'message' => 'Invalid settings group',
                ], 400);
        }
    }

    /**
     * Update multiple settings
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['required'],
            'settings.*.group' => ['required', 'string', Rule::in(['user', 'company', 'tax'])],
        ]);

        $user = $request->user();
        $company = $this->getActiveCompany($request);

        try {
            DB::beginTransaction();

            foreach ($validated['settings'] as $setting) {
                $this->updateSetting($user, $company, $setting);
            }

            DB::commit();

            return response()->json([
                'message' => 'Settings updated successfully',
                'settings' => $this->getAllSettings($user, $company),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update settings: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a specific setting
     */
    public function updateSetting(Request $request, string $group, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required'],
        ]);

        $user = $request->user();
        $company = $this->getActiveCompany($request);

        try {
            $this->updateSingleSetting($user, $company, $group, $key, $validated['value']);

            return response()->json([
                'message' => 'Setting updated successfully',
                'setting' => [
                    'group' => $group,
                    'key' => $key,
                    'value' => $validated['value'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update setting: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user-specific settings
     */
    private function getUserSettings(User $user): array
    {
        return [
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
    }

    /**
     * Get company-specific settings
     */
    private function getCompanySettings(Company $company): array
    {
        return [
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
        ];
    }

    /**
     * Get tax settings for a company
     */
    private function getTaxSettings(string $companyId): ?array
    {
        $taxSettings = TaxSettings::where('company_id', $companyId)->first();

        if (! $taxSettings) {
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

    /**
     * Get system-wide settings
     */
    private function getSystemSettings(): array
    {
        return [
            'available_locales' => [
                'en' => 'English',
                'es' => 'Spanish',
                'fr' => 'French',
                'de' => 'German',
                'it' => 'Italian',
                'pt' => 'Portuguese',
                'zh' => 'Chinese',
                'ja' => 'Japanese',
            ],
            'available_timezones' => $this->getTimezones(),
            'available_currencies' => $this->getCurrencies(),
            'available_themes' => [
                'light' => 'Light',
                'dark' => 'Dark',
                'system' => 'System',
            ],
            'available_date_formats' => [
                'Y-m-d' => '2024-01-31',
                'm/d/Y' => '01/31/2024',
                'd/m/Y' => '31/01/2024',
                'F j, Y' => 'January 31, 2024',
                'j F Y' => '31 January 2024',
            ],
            'available_time_formats' => [
                '12h' => '12-hour (2:30 PM)',
                '24h' => '24-hour (14:30)',
            ],
            'available_number_formats' => [
                '1,234.56' => '1,234.56',
                '1.234,56' => '1.234,56',
                '1234.56' => '1234.56',
                '1,235' => '1,235',
            ],
        ];
    }

    /**
     * Update a single setting
     */
    private function updateSingleSetting(User $user, ?Company $company, string $group, string $key, $value): void
    {
        switch ($group) {
            case 'user':
                $this->updateUserSetting($user, $key, $value);
                break;

            case 'company':
                if (! $company) {
                    throw new \Exception('No active company selected');
                }
                $this->updateCompanySetting($company, $key, $value);
                break;

            case 'tax':
                if (! $company) {
                    throw new \Exception('No active company selected');
                }
                $this->updateTaxSetting($company->id, $key, $value);
                break;
        }
    }

    /**
     * Update user setting
     */
    private function updateUserSetting(User $user, string $key, $value): void
    {
        $allowedKeys = [
            'locale', 'timezone', 'date_format', 'time_format',
            'currency_format', 'number_format', 'theme',
            'email_notifications', 'push_notifications', 'dashboard_layout',
            'preferred_company_id',
        ];

        if (! in_array($key, $allowedKeys)) {
            throw new \Exception("Invalid user setting key: {$key}");
        }

        $user->update([$key => $value]);
    }

    /**
     * Update company setting
     */
    private function updateCompanySetting(Company $company, string $key, $value): void
    {
        $allowedKeys = [
            'name', 'currency_code', 'timezone', 'date_format',
            'fiscal_year_start', 'fiscal_year_end', 'address',
            'phone', 'email', 'website', 'logo', 'tax_id',
            'registration_number',
        ];

        if (! in_array($key, $allowedKeys)) {
            throw new \Exception("Invalid company setting key: {$key}");
        }

        $company->update([$key => $value]);
    }

    /**
     * Update tax setting
     */
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

        if (! in_array($key, $allowedKeys)) {
            throw new \Exception("Invalid tax setting key: {$key}");
        }

        $taxSettings->update([$key => $value]);
    }

    /**
     * Update setting from array
     */
    private function updateSetting(User $user, ?Company $company, array $setting): void
    {
        $this->updateSingleSetting($user, $company, $setting['group'], $setting['key'], $setting['value']);
    }

    /**
     * Get all settings
     */
    private function getAllSettings(User $user, ?Company $company): array
    {
        return [
            'user' => $this->getUserSettings($user),
            'company' => $company ? $this->getCompanySettings($company) : null,
            'tax' => $company ? $this->getTaxSettings($company->id) : null,
            'system' => $this->getSystemSettings(),
        ];
    }

    /**
     * Get active company from session
     */
    private function getActiveCompany(Request $request): ?Company
    {
        $companyId = session('active_company_id');

        if (! $companyId) {
            return null;
        }

        return Company::find($companyId);
    }

    /**
     * Get available timezones
     */
    private function getTimezones(): array
    {
        return [
            'UTC' => 'UTC',
            'America/New_York' => 'Eastern Time',
            'America/Chicago' => 'Central Time',
            'America/Denver' => 'Mountain Time',
            'America/Los_Angeles' => 'Pacific Time',
            'Europe/London' => 'London',
            'Europe/Paris' => 'Paris',
            'Europe/Berlin' => 'Berlin',
            'Asia/Tokyo' => 'Tokyo',
            'Asia/Shanghai' => 'Shanghai',
            'Australia/Sydney' => 'Sydney',
        ];
    }

    /**
     * Get available currencies
     */
    private function getCurrencies(): array
    {
        return [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'JPY' => 'Japanese Yen',
            'CNY' => 'Chinese Yuan',
            'INR' => 'Indian Rupee',
            'CHF' => 'Swiss Franc',
            'SEK' => 'Swedish Krona',
        ];
    }
}
