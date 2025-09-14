<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Currency\ConvertCurrencyRequest;
use App\Http\Requests\Api\Currency\EnableCurrencyRequest;
use App\Http\Requests\Api\Currency\UpdateExchangeRateRequest;
use App\Models\Company;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CurrencyApiController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    /**
     * Get company currencies configuration.
     */
    public function companyCurrencies(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $currencies = $this->currencyService->getCompanyCurrencies($company);

        return response()->json([
            'success' => true,
            'data' => $currencies,
        ]);
    }

    /**
     * Get available currencies.
     */
    public function availableCurrencies(Request $request): JsonResponse
    {
        $currencies = Currency::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimal_places' => $currency->decimal_places,
                'is_active' => $currency->is_active,
            ]);

        return response()->json([
            'success' => true,
            'data' => $currencies,
        ]);
    }

    /**
     * Get exchange rate.
     */
    public function exchangeRate(Request $request): JsonResponse
    {
        $request->validate([
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'date' => ['nullable', 'date'],
        ]);

        try {
            $rate = $this->currencyService->getExchangeRate(
                $request->from_currency,
                $request->to_currency,
                $request->date
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'from_currency' => $request->from_currency,
                    'to_currency' => $request->to_currency,
                    'rate' => $rate,
                    'date' => $request->date ?? now()->toDateString(),
                    'inverse_rate' => 1 / $rate,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get exchange rate',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Convert currency amount.
     */
    public function convert(ConvertCurrencyRequest $request): JsonResponse
    {
        try {
            $amount = \Brick\Money\Money::of($request->amount, $request->from_currency);

            $convertedAmount = $this->currencyService->convertCurrency(
                $amount,
                $request->from_currency,
                $request->to_currency,
                $request->date,
                $request->custom_rate
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'original_amount' => $request->amount,
                    'original_currency' => $request->from_currency,
                    'converted_amount' => $convertedAmount->getAmount()->toFloat(),
                    'converted_currency' => $request->to_currency,
                    'exchange_rate_used' => $this->currencyService->getExchangeRate(
                        $request->from_currency,
                        $request->to_currency,
                        $request->date
                    ),
                    'date' => $request->date ?? now()->toDateString(),
                    'formatted_original' => $this->currencyService->formatMoney($amount),
                    'formatted_converted' => $this->currencyService->formatMoney($convertedAmount),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Currency conversion failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Currency conversion failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update exchange rate.
     */
    public function updateExchangeRate(UpdateExchangeRateRequest $request): JsonResponse
    {
        try {
            $exchangeRate = $this->currencyService->updateExchangeRate(
                $request->from_currency,
                $request->to_currency,
                $request->rate,
                $request->effective_date,
                $request->source
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $exchangeRate->id,
                    'from_currency' => $exchangeRate->from_currency,
                    'to_currency' => $exchangeRate->to_currency,
                    'rate' => $exchangeRate->rate,
                    'effective_date' => $exchangeRate->effective_date,
                    'source' => $exchangeRate->source,
                    'updated_at' => $exchangeRate->updated_at,
                ],
                'message' => 'Exchange rate updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update exchange rate', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update exchange rate',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable currency for company.
     */
    public function enableCurrency(EnableCurrencyRequest $request): JsonResponse
    {
        try {
            $company = $request->user()->company;

            $this->currencyService->enableCurrencyForCompany($company, $request->currency_code);

            return response()->json([
                'success' => true,
                'message' => 'Currency enabled successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to enable currency', [
                'error' => $e->getMessage(),
                'currency_code' => $request->currency_code,
                'company_id' => $request->user()->company_id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to enable currency',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disable currency for company.
     */
    public function disableCurrency(Request $request): JsonResponse
    {
        $request->validate([
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        try {
            $company = $request->user()->company;

            $this->currencyService->disableCurrencyForCompany($company, $request->currency_code);

            return response()->json([
                'success' => true,
                'message' => 'Currency disabled successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to disable currency', [
                'error' => $e->getMessage(),
                'currency_code' => $request->currency_code,
                'company_id' => $request->user()->company_id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to disable currency',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get exchange rate history.
     */
    public function exchangeRateHistory(Request $request): JsonResponse
    {
        $request->validate([
            'from_currency' => ['required', 'string', 'size:3'],
            'to_currency' => ['required', 'string', 'size:3'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        try {
            $history = $this->currencyService->getExchangeRateHistory(
                $request->from_currency,
                $request->to_currency,
                $request->start_date,
                $request->end_date
            );

            return response()->json([
                'success' => true,
                'data' => $history,
                'filters' => [
                    'from_currency' => $request->from_currency,
                    'to_currency' => $request->to_currency,
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get exchange rate history',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get latest exchange rates.
     */
    public function latestExchangeRates(Request $request): JsonResponse
    {
        $request->validate([
            'base_currency' => ['nullable', 'string', 'size:3'],
        ]);

        try {
            $baseCurrency = $request->base_currency ?? 'USD';
            $rates = $this->currencyService->getLatestExchangeRates($baseCurrency);

            return response()->json([
                'success' => true,
                'data' => $rates,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get latest exchange rates',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get currency balances for company.
     */
    public function currencyBalances(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $balances = $this->currencyService->getCurrencyBalances($company);

            return response()->json([
                'success' => true,
                'data' => $balances,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get currency balances', [
                'error' => $e->getMessage(),
                'company_id' => $request->user()->company_id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to get currency balances',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate currency impact.
     */
    public function currencyImpact(Request $request): JsonResponse
    {
        $request->validate([
            'transactions' => ['required', 'array'],
            'transactions.*.id' => ['required', 'string'],
            'transactions.*.amount' => ['required', 'numeric'],
            'transactions.*.currency' => ['required', 'string', 'size:3'],
            'transactions.*.date' => ['nullable', 'date'],
            'target_currency' => ['required', 'string', 'size:3'],
        ]);

        try {
            $company = $request->user()->company;
            $impact = $this->currencyService->calculateCurrencyImpact(
                $company,
                $request->transactions,
                $request->target_currency
            );

            return response()->json([
                'success' => true,
                'data' => $impact,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to calculate currency impact', [
                'error' => $e->getMessage(),
                'company_id' => $request->user()->company_id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to calculate currency impact',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync exchange rates from external API.
     */
    public function syncExchangeRates(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => ['nullable', 'string', 'in:fixer,openexchangerates,ecb'],
        ]);

        try {
            $provider = $request->provider ?? 'fixer';
            $result = $this->currencyService->syncExchangeRatesFromAPI($provider);

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Exchange rates synchronized successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to sync exchange rates', [
                'error' => $e->getMessage(),
                'provider' => $request->provider,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to sync exchange rates',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get currency symbol.
     */
    public function currencySymbol(Request $request): JsonResponse
    {
        $request->validate([
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        try {
            $symbol = $this->currencyService->getCurrencySymbol($request->currency_code);

            return response()->json([
                'success' => true,
                'data' => [
                    'currency_code' => $request->currency_code,
                    'symbol' => $symbol,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get currency symbol',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Format money amount.
     */
    public function formatMoney(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'numeric'],
            'currency_code' => ['required', 'string', 'size:3'],
            'locale' => ['nullable', 'string'],
        ]);

        try {
            $money = \Brick\Money\Money::of($request->amount, $request->currency_code);
            $formatted = $this->currencyService->formatMoney($money, $request->locale);

            return response()->json([
                'success' => true,
                'data' => [
                    'amount' => $request->amount,
                    'currency_code' => $request->currency_code,
                    'formatted' => $formatted,
                    'locale' => $request->locale ?? app()->getLocale(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to format money',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
