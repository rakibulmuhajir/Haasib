<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\CurrencyCatalog;
use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CurrencyService
{
    /**
     * Get all active currencies for a company.
     */
    public function getCompanyCurrencies(string $companyId): Collection
    {
        $company = Company::find($companyId);
        
        // If multi-currency is disabled, only return base currency
        if (!$company || !$company->isMultiCurrencyEnabled()) {
            $baseCurrency = $this->getBaseCurrency($companyId);
            return $baseCurrency ? collect([$baseCurrency]) : collect([]);
        }

        return CompanyCurrency::where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByDesc('is_base_currency')
            ->orderBy('currency_name')
            ->get();
    }

    /**
     * Get company's base currency.
     */
    public function getBaseCurrency(string $companyId): ?CompanyCurrency
    {
        return CompanyCurrency::where('company_id', $companyId)
            ->where('is_base_currency', true)
            ->first();
    }

    /**
     * Check if multi-currency is enabled for a company.
     */
    public function isMultiCurrencyEnabled(string $companyId): bool
    {
        $company = Company::find($companyId);
        return $company ? $company->isMultiCurrencyEnabled() : false;
    }

    /**
     * Add a currency to a company.
     */
    public function addCurrencyToCompany(
        string $companyId,
        string $currencyCode,
        float $defaultExchangeRate = 1.0,
        bool $isBaseCurrency = false,
        bool $isActive = true
    ): CompanyCurrency {
        // Get currency info from catalog
        $catalogInfo = CurrencyCatalog::getCurrencyInfo($currencyCode);
        
        if (!$catalogInfo) {
            throw new \InvalidArgumentException("Currency {$currencyCode} not found in catalog");
        }

        // Check if currency already exists
        $existing = CompanyCurrency::where('company_id', $companyId)
            ->where('currency_code', strtoupper($currencyCode))
            ->first();
            
        if ($existing) {
            throw new \RuntimeException("Currency {$currencyCode} already exists for this company");
        }

        // If this is a base currency, ensure no other base currency exists
        if ($isBaseCurrency) {
            $existingBase = $this->getBaseCurrency($companyId);
            if ($existingBase) {
                throw new \RuntimeException("Company already has a base currency: {$existingBase->currency_code}");
            }
        }

        return CompanyCurrency::create([
            'company_id' => $companyId,
            'currency_code' => strtoupper($currencyCode),
            'currency_name' => $catalogInfo['name'],
            'currency_symbol' => $catalogInfo['symbol'],
            'is_base_currency' => $isBaseCurrency,
            'default_exchange_rate' => $defaultExchangeRate,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Set up initial base currency for a company.
     */
    public function setupBaseCurrency(string $companyId, string $currencyCode): CompanyCurrency
    {
        return $this->addCurrencyToCompany(
            $companyId,
            $currencyCode,
            1.0,
            true,
            true
        );
    }

    /**
     * Get exchange rate between currencies.
     */
    public function getExchangeRate(
        string $companyId,
        string $fromCurrency,
        string $toCurrency,
        ?Carbon $asOfDate = null
    ): ?float {
        // Same currency
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $asOfDate = $asOfDate ?? now();

        // Try cached rate first
        $cacheKey = "exchange_rate:{$companyId}:{$fromCurrency}:{$toCurrency}:" . $asOfDate->format('Y-m-d');
        
        return Cache::remember($cacheKey, 3600, function () use ($companyId, $fromCurrency, $toCurrency, $asOfDate) {
            return ExchangeRate::getDbRate($companyId, $fromCurrency, $toCurrency, $asOfDate);
        });
    }

    /**
     * Set exchange rate between currencies.
     */
    public function setExchangeRate(
        string $companyId,
        string $fromCurrency,
        string $toCurrency,
        float $rate,
        ?Carbon $effectiveDate = null,
        string $source = 'manual',
        ?string $notes = null,
        ?string $userId = null
    ): ExchangeRate {
        $exchangeRate = ExchangeRate::setRate(
            $companyId,
            $fromCurrency,
            $toCurrency,
            $rate,
            $effectiveDate,
            $source,
            $notes,
            $userId
        );

        // Clear related cache
        $dateKey = ($effectiveDate ?? now())->format('Y-m-d');
        Cache::forget("exchange_rate:{$companyId}:{$fromCurrency}:{$toCurrency}:{$dateKey}");
        Cache::forget("exchange_rate:{$companyId}:{$toCurrency}:{$fromCurrency}:{$dateKey}");

        return $exchangeRate;
    }

    /**
     * Convert amount between currencies.
     */
    public function convertAmount(
        string $companyId,
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        ?Carbon $asOfDate = null
    ): ?float {
        if ($amount === 0.0) {
            return 0.0;
        }

        $rate = $this->getExchangeRate($companyId, $fromCurrency, $toCurrency, $asOfDate);
        
        return $rate ? $amount * $rate : null;
    }

    /**
     * Convert amount to base currency.
     */
    public function convertToBaseCurrency(
        string $companyId,
        float $amount,
        string $fromCurrency,
        ?Carbon $asOfDate = null
    ): ?float {
        $baseCurrency = $this->getBaseCurrency($companyId);
        
        if (!$baseCurrency) {
            throw new \RuntimeException('No base currency configured for company');
        }

        return $this->convertAmount(
            $companyId,
            $amount,
            $fromCurrency,
            $baseCurrency->currency_code,
            $asOfDate
        );
    }

    /**
     * Convert amount from base currency.
     */
    public function convertFromBaseCurrency(
        string $companyId,
        float $amount,
        string $toCurrency,
        ?Carbon $asOfDate = null
    ): ?float {
        $baseCurrency = $this->getBaseCurrency($companyId);
        
        if (!$baseCurrency) {
            throw new \RuntimeException('No base currency configured for company');
        }

        return $this->convertAmount(
            $companyId,
            $amount,
            $baseCurrency->currency_code,
            $toCurrency,
            $asOfDate
        );
    }

    /**
     * Get latest exchange rates for a company.
     */
    public function getLatestExchangeRates(string $companyId): Collection
    {
        return DB::table('auth.exchange_rates as er1')
            ->select('er1.*')
            ->where('er1.company_id', $companyId)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('auth.exchange_rates as er2')
                    ->whereRaw('er2.company_id = er1.company_id')
                    ->whereRaw('er2.from_currency_code = er1.from_currency_code')
                    ->whereRaw('er2.to_currency_code = er1.to_currency_code')
                    ->whereRaw('(er2.effective_date > er1.effective_date OR (er2.effective_date = er1.effective_date AND er2.created_at > er1.created_at))');
            })
            ->orderBy('from_currency_code')
            ->orderBy('to_currency_code')
            ->get()
            ->map(function ($rate) {
                return (object) [
                    'id' => $rate->id,
                    'from_currency_code' => $rate->from_currency_code,
                    'to_currency_code' => $rate->to_currency_code,
                    'rate' => (float) $rate->rate,
                    'effective_date' => $rate->effective_date,
                    'source' => $rate->source,
                    'notes' => $rate->notes,
                    'created_at' => $rate->created_at,
                ];
            });
    }

    /**
     * Get exchange rate history for a currency pair.
     */
    public function getExchangeRateHistory(
        string $companyId,
        string $fromCurrency,
        string $toCurrency,
        int $limit = 10
    ): Collection {
        return ExchangeRate::where('company_id', $companyId)
            ->where('from_currency_code', $fromCurrency)
            ->where('to_currency_code', $toCurrency)
            ->orderByDesc('effective_date')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if exchange rates are stale (need updating).
     */
    public function getStaleExchangeRates(string $companyId, int $staleDays = 7): Collection
    {
        $staleDate = now()->subDays($staleDays);

        return $this->getLatestExchangeRates($companyId)
            ->filter(function ($rate) use ($staleDate) {
                return Carbon::parse($rate->effective_date)->lt($staleDate);
            });
    }

    /**
     * Format amount in a specific currency.
     */
    public function formatAmount(float $amount, string $currencyCode): string
    {
        $catalogInfo = CurrencyCatalog::getCurrencyInfo($currencyCode);
        
        if (!$catalogInfo) {
            return number_format($amount, 2);
        }

        $decimals = $catalogInfo['decimal_places'];
        $symbol = $catalogInfo['symbol'];
        
        return $symbol . number_format($amount, $decimals);
    }

    /**
     * Get currency display information.
     */
    public function getCurrencyDisplay(string $currencyCode): array
    {
        $catalogInfo = CurrencyCatalog::getCurrencyInfo($currencyCode);
        
        return $catalogInfo ?: [
            'code' => $currencyCode,
            'name' => $currencyCode,
            'symbol' => $currencyCode,
            'decimal_places' => 2,
        ];
    }

    /**
     * Bulk update exchange rates.
     */
    public function bulkUpdateExchangeRates(
        string $companyId,
        array $rates,
        ?Carbon $effectiveDate = null,
        string $source = 'manual',
        ?string $userId = null
    ): Collection {
        $effectiveDate = $effectiveDate ?? now();
        $updated = collect();

        DB::transaction(function () use ($companyId, $rates, $effectiveDate, $source, $userId, &$updated) {
            foreach ($rates as $rate) {
                if (!isset($rate['from'], $rate['to'], $rate['rate'])) {
                    continue;
                }

                $exchangeRate = $this->setExchangeRate(
                    $companyId,
                    $rate['from'],
                    $rate['to'],
                    $rate['rate'],
                    $effectiveDate,
                    $source,
                    $rate['notes'] ?? null,
                    $userId
                );

                $updated->push($exchangeRate);
            }
        });

        return $updated;
    }

    /**
     * Validate currency configuration for a company.
     */
    public function validateCurrencyConfiguration(string $companyId): array
    {
        $issues = [];
        
        // Check if base currency exists
        $baseCurrency = $this->getBaseCurrency($companyId);
        if (!$baseCurrency) {
            $issues[] = 'No base currency configured';
        }

        // Check for stale exchange rates
        $staleRates = $this->getStaleExchangeRates($companyId);
        if ($staleRates->isNotEmpty()) {
            $issues[] = "Stale exchange rates found for " . $staleRates->count() . " currency pairs";
        }

        // Check for inactive currencies with recent transactions
        // This would require checking against actual transaction tables

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'base_currency' => $baseCurrency?->currency_code,
            'active_currencies_count' => $this->getCompanyCurrencies($companyId)->count(),
            'stale_rates_count' => $staleRates->count(),
        ];
    }
}