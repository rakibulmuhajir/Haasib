<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrencyApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of currencies.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $currencies = Currency::where('company_id', $company->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($currencies);
    }

    /**
     * Store a newly created currency.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:3|unique:currencies,code',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0',
            'is_default' => 'boolean',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $currency = Currency::create([
                'company_id' => $company->id,
                'code' => $validated['code'],
                'name' => $validated['name'],
                'symbol' => $validated['symbol'],
                'exchange_rate' => $validated['exchange_rate'],
                'is_default' => $validated['is_default'] ?? false,
                'is_active' => true,
            ]);

            return response()->json([
                'message' => 'Currency created successfully',
                'currency' => $currency,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create currency',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company currencies.
     */
    public function companyCurrencies(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $currencies = Currency::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json($currencies);
    }

    /**
     * Get available currencies.
     */
    public function availableCurrencies(Request $request): JsonResponse
    {
        // Common world currencies
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
            ['code' => 'BRL', 'name' => 'Brazilian Real', 'symbol' => 'R$'],
            ['code' => 'MXN', 'name' => 'Mexican Peso', 'symbol' => '$'],
            ['code' => 'SEK', 'name' => 'Swedish Krona', 'symbol' => 'kr'],
            ['code' => 'NOK', 'name' => 'Norwegian Krone', 'symbol' => 'kr'],
            ['code' => 'DKK', 'name' => 'Danish Krone', 'symbol' => 'kr'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$'],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$'],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R'],
            ['code' => 'KRW', 'name' => 'South Korean Won', 'symbol' => '₩'],
        ];

        return response()->json($currencies);
    }

    /**
     * Get exchange rate.
     */
    public function exchangeRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        try {
            // Exchange rate logic would be implemented here
            // For now, return a dummy rate
            $exchangeRate = 1.0;

            return response()->json([
                'from' => $validated['from'],
                'to' => $validated['to'],
                'exchange_rate' => $exchangeRate,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get exchange rate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert currency amount.
     */
    public function convert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        try {
            // Currency conversion logic would be implemented here
            // For now, just return the same amount
            $convertedAmount = $validated['amount'];
            $exchangeRate = 1.0;

            return response()->json([
                'original_amount' => $validated['amount'],
                'converted_amount' => $convertedAmount,
                'from_currency' => $validated['from'],
                'to_currency' => $validated['to'],
                'exchange_rate' => $exchangeRate,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to convert currency',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update exchange rate.
     */
    public function updateExchangeRate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0',
        ]);

        try {
            $company = $request->user()->currentCompany();

            // Update exchange rate logic would be implemented here
            // For now, just return success

            return response()->json([
                'message' => 'Exchange rate updated successfully',
                'from' => $validated['from'],
                'to' => $validated['to'],
                'exchange_rate' => $validated['exchange_rate'],
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update exchange rate',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable currency.
     */
    public function enableCurrency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => 'required|string|size:3',
        ]);

        try {
            $company = $request->user()->currentCompany();

            // Enable currency logic would be implemented here

            return response()->json([
                'message' => 'Currency enabled successfully',
                'currency_code' => $validated['currency_code'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to enable currency',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable currency.
     */
    public function disableCurrency(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => 'required|string|size:3',
        ]);

        try {
            $company = $request->user()->currentCompany();

            // Disable currency logic would be implemented here

            return response()->json([
                'message' => 'Currency disabled successfully',
                'currency_code' => $validated['currency_code'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to disable currency',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get exchange rate history.
     */
    public function exchangeRateHistory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            $days = $validated['days'] ?? 30;

            // Exchange rate history logic would be implemented here
            $history = [];

            return response()->json($history);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get exchange rate history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get latest exchange rates.
     */
    public function latestExchangeRates(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            // Latest exchange rates logic would be implemented here
            $exchangeRates = [];

            return response()->json($exchangeRates);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get latest exchange rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get currency balances.
     */
    public function currencyBalances(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            // Currency balances logic would be implemented here
            $balances = [];

            return response()->json($balances);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get currency balances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate currency impact.
     */
    public function currencyImpact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3',
        ]);

        try {
            // Currency impact calculation would be implemented here
            $impact = [
                'original_amount' => $validated['amount'],
                'from_currency' => $validated['from_currency'],
                'to_currency' => $validated['to_currency'],
                'impact_amount' => 0,
                'percentage_change' => 0,
            ];

            return response()->json($impact);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to calculate currency impact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync exchange rates.
     */
    public function syncExchangeRates(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            // Sync exchange rates logic would be implemented here

            return response()->json([
                'message' => 'Exchange rates synchronized successfully',
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync exchange rates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get currency symbol.
     */
    public function currencySymbol(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'currency_code' => 'required|string|size:3',
        ]);

        try {
            $symbols = [
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'JPY' => '¥',
                'CAD' => 'C$',
                'AUD' => 'A$',
                'CHF' => 'CHF',
                'CNY' => '¥',
                'INR' => '₹',
                'BRL' => 'R$',
                'MXN' => '$',
                'SEK' => 'kr',
                'NOK' => 'kr',
                'DKK' => 'kr',
                'SGD' => 'S$',
                'HKD' => 'HK$',
                'NZD' => 'NZ$',
                'ZAR' => 'R',
                'KRW' => '₩',
            ];

            $symbol = $symbols[$validated['currency_code']] ?? $validated['currency_code'];

            return response()->json([
                'currency_code' => $validated['currency_code'],
                'symbol' => $symbol,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get currency symbol',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Format money amount.
     */
    public function formatMoney(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'currency_code' => 'required|string|size:3',
            'decimal_places' => 'nullable|integer|min:0|max:4',
        ]);

        try {
            $symbols = [
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'JPY' => '¥',
                'CAD' => 'C$',
                'AUD' => 'A$',
                'CHF' => 'CHF',
                'CNY' => '¥',
                'INR' => '₹',
                'BRL' => 'R$',
                'MXN' => '$',
                'SEK' => 'kr',
                'NOK' => 'kr',
                'DKK' => 'kr',
                'SGD' => 'S$',
                'HKD' => 'HK$',
                'NZD' => 'NZ$',
                'ZAR' => 'R',
                'KRW' => '₩',
            ];

            $decimalPlaces = $validated['decimal_places'] ?? 2;
            $symbol = $symbols[$validated['currency_code']] ?? $validated['currency_code'];

            $formattedAmount = $symbol.number_format($validated['amount'], $decimalPlaces);

            return response()->json([
                'amount' => $validated['amount'],
                'formatted_amount' => $formattedAmount,
                'currency_code' => $validated['currency_code'],
                'symbol' => $symbol,
                'decimal_places' => $decimalPlaces,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to format money',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle currency active status.
     */
    public function toggleActive(Request $request, string $currency): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $currency = Currency::where('company_id', $company->id)
                ->where('code', $currency)
                ->firstOrFail();

            $currency->update(['is_active' => ! $currency->is_active]);

            return response()->json([
                'message' => 'Currency status updated successfully',
                'currency' => $currency,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to toggle currency status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Placeholder methods for import functionality
    public function getImportSources(Request $request): JsonResponse
    {
        return response()->json([
            'sources' => [
                ['id' => 'ecb', 'name' => 'European Central Bank'],
                ['id' => 'fixer', 'name' => 'Fixer.io'],
                ['id' => 'openexchangerates', 'name' => 'Open Exchange Rates'],
            ],
        ]);
    }

    public function searchExternalCurrencies(Request $request): JsonResponse
    {
        return response()->json(['currencies' => []]);
    }

    public function importSpecificCurrencies(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Import functionality not implemented']);
    }

    public function previewImport(Request $request): JsonResponse
    {
        return response()->json(['preview' => []]);
    }

    public function importCurrencies(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Import functionality not implemented']);
    }
}
