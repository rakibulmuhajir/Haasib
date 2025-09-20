<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
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
    use ApiResponder;
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

        return $this->ok($currencies);
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

        return $this->ok($currencies);
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

            return $this->ok([
                'from_currency' => $request->from_currency,
                'to_currency' => $request->to_currency,
                'rate' => $rate,
                'date' => $request->date ?? now()->toDateString(),
                'inverse_rate' => 1 / $rate,
            ]);

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to get exchange rate', 400, ['message' => $e->getMessage()]);
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

            return $this->ok([
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
            ]);

        } catch (\Exception $e) {
            Log::error('Currency conversion failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return $this->fail('INTERNAL_ERROR', 'Currency conversion failed', 500, ['message' => $e->getMessage()]);
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

            return $this->ok([
                'id' => $exchangeRate->id,
                'from_currency' => $exchangeRate->from_currency,
                'to_currency' => $exchangeRate->to_currency,
                'rate' => $exchangeRate->rate,
                'effective_date' => $exchangeRate->effective_date,
                'source' => $exchangeRate->source,
                'updated_at' => $exchangeRate->updated_at,
            ], 'Exchange rate updated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to update exchange rate', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to update exchange rate', 500, ['message' => $e->getMessage()]);
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

            return $this->ok(null, 'Currency enabled successfully');

        } catch (\Exception $e) {
            Log::error('Failed to enable currency', [
                'error' => $e->getMessage(),
                'currency_code' => $request->currency_code,
                'company_id' => $request->user()->company_id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to enable currency', 500, ['message' => $e->getMessage()]);
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

            return $this->ok(null, 'Currency disabled successfully');

        } catch (\Exception $e) {
            Log::error('Failed to disable currency', [
                'error' => $e->getMessage(),
                'currency_code' => $request->currency_code,
                'company_id' => $request->user()->company_id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to disable currency', 500, ['message' => $e->getMessage()]);
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

            return $this->ok($history, null, [
                'from_currency' => $request->from_currency,
                'to_currency' => $request->to_currency,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to get exchange rate history', 500, ['message' => $e->getMessage()]);
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

            return $this->ok($rates);

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to get latest exchange rates', 500, ['message' => $e->getMessage()]);
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

            return $this->ok($balances);

        } catch (\Exception $e) {
            Log::error('Failed to get currency balances', [
                'error' => $e->getMessage(),
                'company_id' => $request->user()->company_id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to get currency balances', 500, ['message' => $e->getMessage()]);
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

            return $this->ok($impact);

        } catch (\Exception $e) {
            Log::error('Failed to calculate currency impact', [
                'error' => $e->getMessage(),
                'company_id' => $request->user()->company_id,
                'request_data' => $request->all(),
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to calculate currency impact', 500, ['message' => $e->getMessage()]);
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

            return $this->ok($result, 'Exchange rates synchronized successfully');

        } catch (\Exception $e) {
            Log::error('Failed to sync exchange rates', [
                'error' => $e->getMessage(),
                'provider' => $request->provider,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to sync exchange rates', 500, ['message' => $e->getMessage()]);
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

            return $this->ok([
                'currency_code' => $request->currency_code,
                'symbol' => $symbol,
            ]);

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to get currency symbol', 400, ['message' => $e->getMessage()]);
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

            return $this->ok([
                'amount' => $request->amount,
                'currency_code' => $request->currency_code,
                'formatted' => $formatted,
                'locale' => $request->locale ?? app()->getLocale(),
            ]);

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to format money', 400, ['message' => $e->getMessage()]);
        }
    }
}
