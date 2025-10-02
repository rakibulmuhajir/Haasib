<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Currency\ConvertCurrencyRequest;
use App\Http\Requests\Api\Currency\EnableCurrencyRequest;
use App\Http\Requests\Api\Currency\StoreCurrencyRequest;
use App\Http\Requests\Api\Currency\UpdateExchangeRateRequest;
use App\Http\Responses\ApiResponder;
use App\Models\Company;
use App\Models\Currency;
use App\Services\CurrencyService;
use App\Services\ExternalCurrencyImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CurrencyApiController extends Controller
{
    use ApiResponder;

    public function __construct(
        private CurrencyService $currencyService,
        private ExternalCurrencyImportService $importService
    ) {}

    /**
     * Get all system currencies.
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has system permission
        if (! $request->user()->hasPermissionTo('system.currencies.manage')) {
            abort(403, 'Unauthorized');
        }

        $currencies = Currency::orderBy('name')
            ->get()
            ->map(fn ($currency) => [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'active' => $currency->is_active,
            ]);

        return $this->ok($currencies);
    }

    /**
     * Store a new system currency.
     */
    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $currency = Currency::create([
            'code' => $request->code,
            'numeric_code' => $request->numeric_code,
            'name' => $request->name,
            'symbol' => $request->symbol,
            'symbol_position' => $request->symbol_position,
            'minor_unit' => $request->minor_unit,
            'thousands_separator' => $request->thousands_separator,
            'decimal_separator' => $request->decimal_separator,
            'exchange_rate' => $request->exchange_rate,
            'is_active' => true,
        ]);

        return $this->created([
            'id' => $currency->id,
            'code' => $currency->code,
            'name' => $currency->name,
            'symbol' => $currency->symbol,
            'numeric_code' => $currency->numeric_code,
            'active' => $currency->is_active,
        ]);
    }

    /**
     * Toggle currency active status.
     */
    public function toggleActive(Request $request, Currency $currency): JsonResponse
    {
        $currency->is_active = ! $currency->is_active;
        $currency->save();

        return $this->ok([
            'id' => $currency->id,
            'active' => $currency->is_active,
            'message' => sprintf('Currency %s successfully', $currency->is_active ? 'enabled' : 'disabled'),
        ]);
    }

    /**
     * Get company currencies configuration.
     */
    public function companyCurrencies(Request $request): JsonResponse
    {
        $company = $request->attributes->get('company');

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
            $company = $request->attributes->get('company');

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
            $company = $request->attributes->get('company');

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
        // Check if user has system permission
        if (! $request->user()->hasPermissionTo('system.fx.view')) {
            abort(403, 'Unauthorized');
        }

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
            $company = $request->attributes->get('company');
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
            $company = $request->attributes->get('company');
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

    /**
     * Get available import sources.
     */
    public function getImportSources(Request $request): JsonResponse
    {
        // Check if user has system permission
        if (! $request->user()->hasPermissionTo('system.currencies.manage')) {
            abort(403, 'Unauthorized');
        }

        try {
            $sources = $this->importService->getAvailableSources();

            return $this->ok($sources);
        } catch (\Exception $e) {
            Log::error('Failed to get import sources', ['error' => $e->getMessage()]);

            return $this->fail('INTERNAL_ERROR', 'Failed to get import sources', 500);
        }
    }

    /**
     * Search currencies from external source.
     */
    public function searchExternalCurrencies(Request $request): JsonResponse
    {
        // Check if user has system permission
        if (! $request->user()->hasPermissionTo('system.currencies.manage')) {
            abort(403, 'Unauthorized');
        }

        $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'source' => ['sometimes', 'string', 'in:ecb,fixer,exchangerate'],
        ]);

        try {
            $source = $request->source ?? 'ecb';

            // Get the company's base currency
            $company = $request->user()->currentCompany;
            $baseCurrency = $company->base_currency ?? 'USD';

            $currencies = $this->importService->importCurrencies($source, $baseCurrency);

            // Filter currencies based on search query
            $queryString = strtolower($request->input('query'));
            $filteredCurrencies = collect($currencies)->filter(function ($currency) use ($queryString) {
                return str_contains(strtolower($currency['name']), $queryString) ||
                       str_contains(strtolower($currency['code']), $queryString) ||
                       str_contains(strtolower($currency['symbol']), $queryString);
            })->values()->all();

            return $this->ok([
                'source' => $source,
                'query' => $request->input('query'),
                'currencies' => array_slice($filteredCurrencies, 0, 20), // Limit results
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to search external currencies', [
                'source' => $request->source ?? 'ecb',
                'query' => $request->input('query'),
                'error' => $e->getMessage(),
            ]);

            return $this->fail('SEARCH_ERROR', 'Failed to search currencies', 400, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Preview currencies from external source.
     */
    public function previewImport(Request $request): JsonResponse
    {
        $request->validate([
            'source' => ['required', 'string', 'in:ecb,fixer,exchangerate'],
        ]);

        try {
            $currencies = $this->importService->importCurrencies($request->source);

            // Format for preview - show which currencies are new vs existing
            $existingCodes = Currency::pluck('code')->toArray();
            $preview = collect($currencies)->map(function ($currency) use ($existingCodes) {
                return [
                    'code' => $currency['code'],
                    'name' => $currency['name'],
                    'symbol' => $currency['symbol'],
                    'numeric_code' => $currency['numeric_code'],
                    'exchange_rate' => $currency['exchange_rate'],
                    'status' => in_array($currency['code'], $existingCodes) ? 'existing' : 'new',
                ];
            });

            return $this->ok([
                'source' => $request->source,
                'total_currencies' => count($currencies),
                'new_currencies' => $preview->where('status', 'new')->count(),
                'existing_currencies' => $preview->where('status', 'existing')->count(),
                'currencies' => $preview,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to preview import', [
                'source' => $request->source,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('IMPORT_ERROR', 'Failed to preview currencies', 400, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Import currencies from external source.
     */
    public function importCurrencies(Request $request): JsonResponse
    {
        $request->validate([
            'source' => ['required', 'string', 'in:ecb,fixer,exchangerate'],
            'update_existing' => ['sometimes', 'boolean'],
        ]);

        try {
            $currencies = $this->importService->importCurrencies($request->source);
            $options = [
                'update_existing' => $request->boolean('update_existing', false),
            ];

            $result = $this->importService->saveImportedCurrencies($currencies, $options);

            Log::info('Currency import completed', [
                'source' => $request->source,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ]);

            return $this->ok([
                'source' => $request->source,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ], 'Currencies imported successfully');
        } catch (\Exception $e) {
            Log::error('Failed to import currencies', [
                'source' => $request->source,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('IMPORT_ERROR', 'Failed to import currencies', 400, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Import specific currencies from external source.
     */
    public function importSpecificCurrencies(Request $request): JsonResponse
    {
        $request->validate([
            'currency_codes' => ['required', 'array'],
            'currency_codes.*' => ['required', 'string', 'size:3'],
            'source' => ['sometimes', 'string', 'in:ecb,fixer,exchangerate'],
            'update_existing' => ['sometimes', 'boolean'],
        ]);

        try {
            $source = $request->source ?? 'ecb';

            // Get the company's base currency
            $company = $request->user()->currentCompany;
            $baseCurrency = $company->base_currency ?? 'USD';

            $currencies = $this->importService->importSpecificCurrencies(
                $request->currency_codes,
                $source,
                $baseCurrency
            );

            $options = [
                'update_existing' => $request->boolean('update_existing', false),
            ];

            $result = $this->importService->saveImportedCurrencies($currencies, $options);

            Log::info('Specific currency import completed', [
                'source' => $source,
                'currencies' => $request->currency_codes,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ]);

            return $this->ok([
                'source' => $source,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ], 'Currencies imported successfully');
        } catch (\Exception $e) {
            Log::error('Failed to import specific currencies', [
                'source' => $request->source ?? 'ecb',
                'currency_codes' => $request->currency_codes,
                'error' => $e->getMessage(),
            ]);

            return $this->fail('IMPORT_ERROR', 'Failed to import currencies', 400, ['message' => $e->getMessage()]);
        }
    }
}
