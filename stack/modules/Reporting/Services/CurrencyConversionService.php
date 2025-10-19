<?php

namespace Modules\Reporting\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CurrencyConversionService
{
    /**
     * Convert amount from source currency to target currency
     */
    public function convertAmount(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        ?Carbon $date = null
    ): array {
        if ($fromCurrency === $toCurrency) {
            return [
                'original_amount' => $amount,
                'converted_amount' => $amount,
                'exchange_rate' => 1.0,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'conversion_date' => $date?->toDateString() ?? now()->toDateString(),
            ];
        }

        $exchangeRate = $this->getExchangeRate($fromCurrency, $toCurrency, $date);
        $convertedAmount = $amount * $exchangeRate;

        return [
            'original_amount' => $amount,
            'converted_amount' => $convertedAmount,
            'exchange_rate' => $exchangeRate,
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'conversion_date' => $date?->toDateString() ?? now()->toDateString(),
        ];
    }

    /**
     * Convert multiple amounts in batch
     */
    public function convertBatch(array $amounts, string $fromCurrency, string $toCurrency, ?Carbon $date = null): array
    {
        $exchangeRate = $this->getExchangeRate($fromCurrency, $toCurrency, $date);
        $results = [];

        foreach ($amounts as $key => $amount) {
            if ($fromCurrency === $toCurrency) {
                $results[$key] = [
                    'original_amount' => $amount,
                    'converted_amount' => $amount,
                    'exchange_rate' => 1.0,
                ];
            } else {
                $results[$key] = [
                    'original_amount' => $amount,
                    'converted_amount' => $amount * $exchangeRate,
                    'exchange_rate' => $exchangeRate,
                ];
            }
        }

        return [
            'conversions' => $results,
            'exchange_rate' => $exchangeRate,
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'conversion_date' => $date?->toDateString() ?? now()->toDateString(),
        ];
    }

    /**
     * Convert financial statement data
     */
    public function convertStatementData(array $data, string $targetCurrency, ?Carbon $date = null): array
    {
        $sourceCurrency = $data['currency'] ?? 'USD';

        if ($sourceCurrency === $targetCurrency) {
            return $data;
        }

        $exchangeRate = $this->getExchangeRate($sourceCurrency, $targetCurrency, $date);

        return $this->applyConversionToStatement($data, $exchangeRate, $sourceCurrency, $targetCurrency, $date);
    }

    /**
     * Get exchange rate between two currencies
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency, ?Carbon $date = null): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = "exchange_rate:{$fromCurrency}:{$toCurrency}:".($date?->toDateString() ?? now()->toDateString());

        return Cache::remember($cacheKey, 3600, function () use ($fromCurrency, $toCurrency, $date) {
            // Try to get rate from database first
            $rate = $this->getDatabaseRate($fromCurrency, $toCurrency, $date);

            if ($rate !== null) {
                return $rate;
            }

            // Fallback to USD as intermediate currency
            if ($fromCurrency !== 'USD' && $toCurrency !== 'USD') {
                $fromToUsd = $this->getExchangeRate($fromCurrency, 'USD', $date);
                $usdToTo = $this->getExchangeRate('USD', $toCurrency, $date);

                return $fromToUsd * $usdToTo;
            }

            // Return 1.0 if no rate found (should be handled by error checking)
            return 1.0;
        });
    }

    /**
     * Get exchange rate from database
     */
    protected function getDatabaseRate(string $fromCurrency, string $toCurrency, ?Carbon $date = null): ?float
    {
        $queryDate = $date?->toDateString() ?? now()->toDateString();

        // Try direct rate first
        $directRate = DB::table('exchange_rates')
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('effective_date', '<=', $queryDate)
            ->orderBy('effective_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->value('rate');

        if ($directRate !== null) {
            return (float) $directRate;
        }

        return null;
    }

    /**
     * Get currency conversion snapshot for reporting
     */
    public function getConversionSnapshot(string $companyId, string $targetCurrency, ?Carbon $date = null): array
    {
        $company = DB::table('auth.companies')
            ->where('id', $companyId)
            ->first(['base_currency', 'exchange_rate_id']);

        if (! $company) {
            throw new \InvalidArgumentException('Company not found');
        }

        $baseCurrency = $company->base_currency;
        $snapshotDate = $date?->toDateString() ?? now()->toDateString();

        // Get exchange rate from company base currency to target currency
        $exchangeRate = $this->getExchangeRate($baseCurrency, $targetCurrency, $date);

        return [
            'company_id' => $companyId,
            'base_currency' => $baseCurrency,
            'target_currency' => $targetCurrency,
            'exchange_rate' => $exchangeRate,
            'snapshot_date' => $snapshotDate,
            'rates_used' => [
                "{$baseCurrency}_to_{$targetCurrency}" => $exchangeRate,
            ],
        ];
    }

    /**
     * Apply conversion to statement data recursively
     */
    protected function applyConversionToStatement(
        array $data,
        float $exchangeRate,
        string $fromCurrency,
        string $toCurrency,
        ?Carbon $date = null
    ): array {
        $converted = $data;
        $converted['currency'] = $toCurrency;
        $converted['exchange_rate_snapshot'] = $this->getConversionSnapshot(
            $data['company_id'] ?? null,
            $toCurrency,
            $date
        );

        // Convert totals if present
        if (isset($converted['totals'])) {
            $converted['totals'] = $this->convertMonetaryValues($converted['totals'], $exchangeRate);
        }

        // Convert sections if present
        if (isset($converted['sections'])) {
            foreach ($converted['sections'] as $sectionName => &$section) {
                if (is_array($section)) {
                    $section = $this->convertMonetaryValues($section, $exchangeRate);
                }
            }
        }

        // Convert accounts array if present
        if (isset($converted['accounts'])) {
            foreach ($converted['accounts'] as &$account) {
                if (is_array($account)) {
                    $account = $this->convertMonetaryValues($account, $exchangeRate);
                }
            }
        }

        return $converted;
    }

    /**
     * Convert monetary values in an array
     */
    protected function convertMonetaryValues(array $data, float $exchangeRate): array
    {
        $converted = $data;

        foreach ($converted as $key => &$value) {
            // Convert common monetary field names
            if (is_numeric($value) && $this->isMonetaryField($key)) {
                $value = round($value * $exchangeRate, 2);
            }
        }

        return $converted;
    }

    /**
     * Check if a field name represents a monetary value
     */
    protected function isMonetaryField(string $fieldName): bool
    {
        $monetaryPatterns = [
            'amount', 'balance', 'total', 'debit', 'credit', 'value',
            'revenue', 'expense', 'income', 'cost', 'profit', 'loss',
        ];

        foreach ($monetaryPatterns as $pattern) {
            if (str_contains(strtolower($fieldName), $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get available currencies for a company
     */
    public function getAvailableCurrencies(string $companyId): array
    {
        $company = DB::table('auth.companies')
            ->where('id', $companyId)
            ->first(['base_currency']);

        if (! $company) {
            throw new \InvalidArgumentException('Company not found');
        }

        // Get currencies used in transactions
        $transactionCurrencies = DB::table('ledger.journal_entries')
            ->where('company_id', $companyId)
            ->whereNotNull('currency')
            ->distinct()
            ->pluck('currency')
            ->toArray();

        // Always include company base currency
        $currencies = array_unique(array_merge([$company->base_currency], $transactionCurrencies));

        // Add common currencies
        $commonCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'];
        $allCurrencies = array_unique(array_merge($currencies, $commonCurrencies));

        sort($allCurrencies);

        return array_values($allCurrencies);
    }

    /**
     * Validate currency conversion is possible
     */
    public function validateConversion(string $fromCurrency, string $toCurrency, ?Carbon $date = null): bool
    {
        try {
            $rate = $this->getExchangeRate($fromCurrency, $toCurrency, $date);

            return $rate > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get historical exchange rates for a date range
     */
    public function getHistoricalRates(
        string $fromCurrency,
        string $toCurrency,
        Carbon $startDate,
        Carbon $endDate
    ): array {
        $rates = DB::table('exchange_rates')
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->whereBetween('effective_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('effective_date')
            ->get()
            ->keyBy('effective_date');

        return $rates->toArray();
    }
}
