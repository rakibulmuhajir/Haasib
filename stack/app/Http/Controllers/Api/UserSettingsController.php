<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserSettings\UpdateUserSettingsRequest;
use App\Models\Company;
use App\Models\TaxSettings;
use App\Models\User;
use App\Services\ServiceContextHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('throttle:60,1');
    }

    /**
     * Get all user settings
     */
    public function index(UpdateUserSettingsRequest $request): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $user = $context->getUser();
            $company = $context->getCompany();

            $settings = [
                'user' => $this->getUserSettings($user),
                'company' => $company ? $this->getCompanySettings($company) : null,
                'tax' => $company ? $this->getTaxSettings($company->id) : null,
                'system' => $this->getSystemSettings(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'settings' => $settings,
                    'active_company' => $company,
                ],
                'message' => 'Settings retrieved successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('User settings retrieval failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId() ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Get settings for a specific group
     */
    public function show(UpdateUserSettingsRequest $request, string $group): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);
            $user = $context->getUser();
            $company = $context->getCompany();

            switch ($group) {
                case 'user':
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'settings' => $this->getUserSettings($user),
                        ],
                        'message' => 'User settings retrieved successfully',
                    ]);

                case 'company':
                    if (! $company) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No active company selected',
                        ], 404);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'settings' => $this->getCompanySettings($company),
                        ],
                        'message' => 'Company settings retrieved successfully',
                    ]);

                case 'tax':
                    if (! $company) {
                        return response()->json([
                            'success' => false,
                            'message' => 'No active company selected',
                        ], 404);
                    }

                    return response()->json([
                        'success' => true,
                        'data' => [
                            'settings' => $this->getTaxSettings($company->id),
                        ],
                        'message' => 'Tax settings retrieved successfully',
                    ]);

                case 'system':
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'settings' => $this->getSystemSettings(),
                        ],
                        'message' => 'System settings retrieved successfully',
                    ]);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid settings group',
                        'errors' => ['group' => ['Invalid settings group: '.$group]],
                    ], 400);
            }

        } catch (\Exception $e) {
            Log::error('User settings group retrieval failed', [
                'error' => $e->getMessage(),
                'group' => $group,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve settings',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Update multiple settings
     */
    public function update(UpdateUserSettingsRequest $request): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            $result = Bus::dispatch('user_settings.update', [
                'settings' => $request->validated('settings'),
            ], $context);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Settings updated successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('User settings update failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
            ], 500);
        }
    }

    /**
     * Update a specific setting
     */
    public function updateSetting(UpdateUserSettingsRequest $request, string $group, string $key): JsonResponse
    {
        try {
            $context = ServiceContextHelper::fromRequest($request);

            // Convert single setting to array format for command
            $settings = [[
                'group' => $group,
                'key' => $key,
                'value' => $request->validated('value'),
            ]];

            $result = Bus::dispatch('user_settings.update', [
                'settings' => $settings,
            ], $context);

            return response()->json([
                'success' => true,
                'data' => [
                    'setting' => [
                        'group' => $group,
                        'key' => $key,
                        'value' => $request->validated('value'),
                    ],
                    'updated_settings' => $result['updated_settings'] ?? [],
                ],
                'message' => 'Setting updated successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('User setting update failed', [
                'error' => $e->getMessage(),
                'group' => $group,
                'key' => $key,
                'user_id' => $request->user()->id,
                'company_id' => $context->getCompanyId(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update setting',
                'errors' => config('app.debug') ? [$e->getMessage()] : [],
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
            'available_timezones' => [
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
            ],
            'available_currencies' => [
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
            ],
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
}
