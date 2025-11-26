<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CurrencySettingsController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    /**
     * Display currency settings page.
     */
    public function index(Request $request): Response
    {
        $user = auth()->user();
        $contextService = app(\App\Services\ContextService::class);
        $company = $contextService->getCurrentCompany($user);
        $companyId = $company?->id;
        
        \Log::info('Currency Settings Index - Company Context:', [
            'user_id' => $user?->id,
            'company_id' => $companyId,
            'company_name' => $company?->name ?? 'Not found'
        ]);
        
        if (!$companyId) {
            \Log::error('Currency Settings Index - No Company ID found!');
            return Inertia::render('Settings/CurrencySettings', [
                'companyCurrencies' => [],
                'baseCurrency' => null,
                'exchangeRates' => [],
                'availableCurrencies' => [],
                'isMultiCurrencyEnabled' => false,
            ]);
        }

        // Get company currencies with exchange rate information
        // Note: getCompanyCurrencies respects multi-currency setting internally
        $companyCurrencies = CompanyCurrency::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_base_currency')
            ->orderBy('currency_name')
            ->get()
            ->map(function ($currency) {
                return [
                    'id' => $currency->id,
                    'currency_code' => $currency->currency_code,
                    'currency_name' => $currency->currency_name,
                    'currency_symbol' => $currency->currency_symbol,
                    'is_base_currency' => $currency->is_base_currency,
                    'default_exchange_rate' => $currency->default_exchange_rate,
                    'is_active' => $currency->is_active,
                    'created_at' => $currency->created_at,
                ];
            });

        $baseCurrency = $this->currencyService->getBaseCurrency($companyId);

        // Get latest exchange rates
        $exchangeRates = $this->currencyService->getLatestExchangeRates($companyId);
        
        // Check multi-currency setting
        $isMultiCurrencyEnabled = $this->currencyService->isMultiCurrencyEnabled($companyId);
        
        \Log::info('Currency Settings Index - Data Summary:', [
            'currencies_count' => $companyCurrencies->count(),
            'base_currency' => $baseCurrency?->currency_code ?? 'None',
            'exchange_rates_count' => count($exchangeRates),
            'is_multi_currency_enabled' => $isMultiCurrencyEnabled,
        ]);

        // Get available currencies from catalog that aren't already added
        $availableCurrencies = collect([
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'Fr'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$'],
        ])->filter(function ($currency) use ($companyCurrencies) {
            return !$companyCurrencies->contains('currency_code', $currency['code']);
        })->values();

        return Inertia::render('Settings/CurrencySettings', [
            'companyCurrencies' => $companyCurrencies,
            'baseCurrency' => $baseCurrency ? [
                'code' => $baseCurrency->currency_code,
                'name' => $baseCurrency->currency_name,
                'symbol' => $baseCurrency->currency_symbol,
            ] : null,
            'exchangeRates' => $exchangeRates,
            'availableCurrencies' => $availableCurrencies,
            'isMultiCurrencyEnabled' => $isMultiCurrencyEnabled,
        ]);
    }

    /**
     * Add a new currency to the company.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();
        $contextService = app(\App\Services\ContextService::class);
        $company = $contextService->getCurrentCompany($user);
        $companyId = $company?->id;

        $request->validate([
            'currency_code' => [
                'required',
                'string',
                'size:3'
            ],
            'default_exchange_rate' => [
                'required',
                'numeric',
                'min:0.000001',
                'max:999999.999999'
            ],
        ]);

        // Custom uniqueness check for schema-prefixed table
        $existingCurrency = CompanyCurrency::where('company_id', $companyId)
            ->where('currency_code', strtoupper($request->currency_code))
            ->first();

        if ($existingCurrency) {
            return response()->json([
                'success' => false,
                'message' => 'This currency is already configured for your company',
                'errors' => ['currency_code' => ['The selected currency is already in use.']]
            ], 422);
        }

        $validated = $request->only(['currency_code', 'default_exchange_rate']);

        try {
            DB::beginTransaction();

            $currency = $this->currencyService->addCurrencyToCompany(
                $companyId,
                $validated['currency_code'],
                $validated['default_exchange_rate'],
                false, // Not a base currency
                true   // Active by default
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Currency added successfully',
                'data' => [
                    'id' => $currency->id,
                    'currency_code' => $currency->currency_code,
                    'currency_name' => $currency->currency_name,
                    'currency_symbol' => $currency->currency_symbol,
                    'is_base_currency' => $currency->is_base_currency,
                    'default_exchange_rate' => $currency->default_exchange_rate,
                    'is_active' => $currency->is_active,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add currency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update currency settings.
     */
    public function update(Request $request, CompanyCurrency $companyCurrency): JsonResponse
    {
        $validated = $request->validate([
            'default_exchange_rate' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.000001',
                'max:999999.999999'
            ],
            'is_active' => [
                'sometimes',
                'required',
                'boolean'
            ]
        ]);

        try {
            // Cannot deactivate base currency
            if (isset($validated['is_active']) && 
                !$validated['is_active'] && 
                $companyCurrency->is_base_currency) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate the base currency'
                ], 400);
            }

            $companyCurrency->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Currency updated successfully',
                'data' => $companyCurrency->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update currency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove currency from company.
     */
    public function destroy(CompanyCurrency $companyCurrency): JsonResponse
    {
        try {
            // Cannot delete base currency
            if ($companyCurrency->is_base_currency) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete the base currency'
                ], 400);
            }

            $companyCurrency->delete();

            return response()->json([
                'success' => true,
                'message' => 'Currency removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove currency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update exchange rate for a currency.
     */
    public function updateExchangeRate(Request $request, CompanyCurrency $companyCurrency): JsonResponse
    {
        $validated = $request->validate([
            'rate' => [
                'required',
                'numeric',
                'min:0.000001',
                'max:999999.999999'
            ],
            'notes' => [
                'nullable',
                'string',
                'max:255'
            ]
        ]);

        try {
            $user = auth()->user();
            $contextService = app(\App\Services\ContextService::class);
            $company = $contextService->getCurrentCompany($user);
            $companyId = $company?->id;
            $baseCurrency = $this->currencyService->getBaseCurrency($companyId);

            if (!$baseCurrency) {
                return response()->json([
                    'success' => false,
                    'message' => 'No base currency configured'
                ], 400);
            }

            // Set exchange rate from this currency to base currency
            $this->currencyService->setExchangeRate(
                $companyId,
                $companyCurrency->currency_code,
                $baseCurrency->currency_code,
                $validated['rate'],
                now(),
                'manual',
                $validated['notes'] ?? null,
                auth()->id()
            );

            return response()->json([
                'success' => true,
                'message' => 'Exchange rate updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update exchange rate',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set a currency as the base currency for the company.
     */
    public function setBaseCurrency(Request $request, CompanyCurrency $companyCurrency): JsonResponse
    {
        try {
            $user = auth()->user();
            $contextService = app(\App\Services\ContextService::class);
            $company = $contextService->getCurrentCompany($user);
            $companyId = $company?->id;

            // Verify the currency belongs to this company
            if ($companyCurrency->company_id !== $companyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            DB::beginTransaction();

            // Remove base flag from all other currencies
            CompanyCurrency::where('company_id', $companyId)
                ->where('id', '!=', $companyCurrency->id)
                ->update(['is_base_currency' => false]);

            // Set this currency as base
            $companyCurrency->update(['is_base_currency' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Base currency updated successfully',
                'data' => $companyCurrency->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to set base currency',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle multi-currency feature for the company.
     */
    public function toggleMultiCurrency(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $contextService = app(\App\Services\ContextService::class);
            $company = $contextService->getCurrentCompany($user);
            $companyId = $company?->id;
            
            \Log::info('ToggleMultiCurrency called', [
                'company_id' => $companyId,
                'request_data' => $request->all(),
            ]);
            
            $company = Company::find($companyId);

            if (!$company) {
                \Log::error('Company not found', ['company_id' => $companyId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found'
                ], 404);
            }

            $validated = $request->validate([
                'enabled' => ['required', 'boolean']
            ]);

            \Log::info('Toggle validation passed', ['enabled' => $validated['enabled']]);

            if ($validated['enabled']) {
                \Log::info('Enabling multi-currency for company', ['company_id' => $companyId]);
                $company->enableMultiCurrency();
                $message = 'Multi-currency feature enabled successfully';
            } else {
                \Log::info('Disabling multi-currency for company', ['company_id' => $companyId]);
                $company->disableMultiCurrency();
                $message = 'Multi-currency feature disabled successfully';
            }
            
  
            \Log::info('Toggle operation completed', [
                'company_id' => $companyId,
                'enabled' => $validated['enabled'],
                'new_setting' => $company->isMultiCurrencyEnabled(),
                'message' => $message
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'isMultiCurrencyEnabled' => $company->isMultiCurrencyEnabled()
            ]);

        } catch (\Exception $e) {
            \Log::error('Toggle operation failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update multi-currency setting',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}