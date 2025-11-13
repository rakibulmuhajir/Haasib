<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CurrencyRate;
use App\Traits\AuditLogging;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyExchangeRateService
{
    use AuditLogging;

    /**
     * Real-time exchange rate providers and their endpoints.
     */
    private array $providers = [
        'exchangerate' => [
            'url' => 'https://api.exchangerate-api.com/v4/latest/{base}',
            'api_key' => null, // Will use free tier
            'free_limit' => 2000, // requests per month
        ],
        'fixer' => [
            'url' => 'http://data.fixer.io/api/latest?access_key={api_key}&base={base}',
            'api_key' => null,
            'free_limit' => 1000,
        ],
        'openexchangerates' => [
            'url' => 'https://openexchangerates.org/api/latest.json?app_id={api_key}&base={base}',
            'api_key' => null,
            'free_limit' => 1000,
        ],
    ];

    private array $commonCurrencies = [
        'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CHF', 'CNY', 'SEK', 'NOK', 'DKK',
        'NZD', 'SGD', 'HKD', 'MXN', 'ZAR', 'INR', 'BRL', 'RUB', 'TRY', 'KRW', 'IDR',
    ];

    private int $cacheTtl = 3600; // 1 hour

    private ServiceContext $context;

    public function __construct(ServiceContext $context)
    {
        $this->context = $context;
    }

    /**
     * Get real-time exchange rate for a currency pair.
     */
    public function getRealTimeExchangeRate(string $fromCurrency, string $toCurrency, ?string $companyId = null): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        // Use company from context or provided ID
        $targetCompanyId = $companyId ?? $this->context->getCompanyId();

        if (! $targetCompanyId) {
            throw new \InvalidArgumentException('Company context is required for exchange rate lookup');
        }

        // Validate company access
        $this->validateCompanyAccess($targetCompanyId);

        // Company-isolated cache key
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}_company_{$targetCompanyId}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($fromCurrency, $toCurrency, $targetCompanyId) {
            // Try providers in order of preference
            foreach ($this->providers as $providerName => $config) {
                try {
                    $rate = $this->fetchFromProvider($providerName, $fromCurrency, $toCurrency);
                    if ($rate !== null) {
                        Log::info("Exchange rate fetched from {$providerName}: {$fromCurrency} to {$toCurrency} = {$rate}", [
                            'company_id' => $targetCompanyId,
                            'user_id' => $this->context->getUserId(),
                            'provider' => $providerName,
                        ]);

                        // Log rate access for audit
                        $this->audit('exchange_rate.accessed', [
                            'company_id' => $targetCompanyId,
                            'from_currency' => $fromCurrency,
                            'to_currency' => $toCurrency,
                            'rate' => $rate,
                            'provider' => $providerName,
                            'accessed_by_user_id' => $this->context->getUserId(),
                            'ip_address' => $this->context->getIpAddress(),
                        ]);

                        return $rate;
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to fetch exchange rate from {$providerName}: {$e->getMessage()}", [
                        'company_id' => $targetCompanyId,
                        'provider' => $providerName,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }
            }

            // Fallback to stored rates if real-time fetch fails
            return $this->getStoredExchangeRate($fromCurrency, $toCurrency, $targetCompanyId);
        });
    }

    /**
     * Fetch exchange rate from a specific provider.
     */
    private function fetchFromProvider(string $providerName, string $fromCurrency, string $toCurrency): ?float
    {
        $provider = $this->providers[$providerName];

        $url = str_replace('{base}', $fromCurrency, $provider['url']);
        if ($provider['api_key']) {
            $url = str_replace('{api_key}', $provider['api_key'], $url);
        }

        $response = Http::timeout(10)->get($url);

        if (! $response->successful()) {
            Log::warning("HTTP error from {$providerName}: {$response->status()}");

            return null;
        }

        $data = $response->json();

        return $this->extractRateFromResponse($providerName, $data, $toCurrency);
    }

    /**
     * Extract rate from provider response.
     */
    private function extractRateFromResponse(string $providerName, array $data, string $toCurrency): ?float
    {
        switch ($providerName) {
            case 'exchangerate':
                return $data['rates'][$toCurrency] ?? null;

            case 'fixer':
                return $data['rates'][$toCurrency] ?? null;

            case 'openexchangerates':
                return $data['rates'][$toCurrency] ?? null;

            default:
                return null;
        }
    }

    /**
     * Get stored exchange rate (fallback).
     */
    private function getStoredExchangeRate(string $fromCurrency, string $toCurrency, string $companyId): ?float
    {
        // Set RLS context
        $this->setRlsContext($companyId);

        $rate = CurrencyRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('valid_from', '<=', now())
            ->where(function ($query) {
                $query->where('valid_until', '>=', now())
                    ->orWhereNull('valid_until');
            })
            ->orderBy('valid_from', 'desc')
            ->first();

        if ($rate) {
            Log::info("Using stored exchange rate: {$fromCurrency} to {$toCurrency} = {$rate->rate}", [
                'company_id' => $companyId,
                'user_id' => $this->context->getUserId(),
            ]);

            // Log stored rate access
            $this->audit('exchange_rate.stored_rate_accessed', [
                'company_id' => $companyId,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'rate' => $rate->rate,
                'rate_id' => $rate->id,
                'valid_from' => $rate->valid_from,
                'valid_until' => $rate->valid_until,
                'accessed_by_user_id' => $this->context->getUserId(),
                'ip_address' => $this->context->getIpAddress(),
            ]);

            return $rate->rate;
        }

        return null;
    }

    /**
     * Update exchange rates for all common currencies.
     */
    public function updateExchangeRates(string $baseCurrency = 'USD', ?string $companyId = null): array
    {
        // Use company from context or provided ID
        $targetCompanyId = $companyId ?? $this->context->getCompanyId();

        if (! $targetCompanyId) {
            throw new \InvalidArgumentException('Company context is required for exchange rate updates');
        }

        // Validate company access
        $this->validateCompanyAccess($targetCompanyId);

        // Set RLS context
        $this->setRlsContext($targetCompanyId);

        $results = [
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $totalUpdates = 0;
        $totalFailures = 0;

        foreach ($this->commonCurrencies as $currency) {
            if ($currency === $baseCurrency) {
                continue;
            }

            try {
                $rate = $this->getRealTimeExchangeRate($baseCurrency, $currency, $targetCompanyId);

                if ($rate !== null) {
                    // Store or update the rate
                    $currencyRate = CurrencyRate::updateOrCreate(
                        [
                            'from_currency' => $baseCurrency,
                            'to_currency' => $currency,
                            'valid_from' => now()->startOfDay(),
                        ],
                        [
                            'rate' => $rate,
                            'valid_until' => now()->endOfDay(),
                            'provider' => 'real_time',
                            'notes' => 'Updated via real-time fetch',
                            'updated_by_user_id' => $this->context->getUserId(),
                            'company_id' => $targetCompanyId,
                        ]
                    );

                    // Clear company-isolated cache
                    Cache::forget("exchange_rate_{$baseCurrency}_{$currency}_company_{$targetCompanyId}");
                    Cache::forget("exchange_rate_{$currency}_{$baseCurrency}_company_{$targetCompanyId}");

                    $results['updated']++;
                    $totalUpdates++;

                    // Log individual rate update
                    $this->audit('exchange_rate.updated', [
                        'company_id' => $targetCompanyId,
                        'from_currency' => $baseCurrency,
                        'to_currency' => $currency,
                        'rate' => $rate,
                        'rate_id' => $currencyRate->id,
                        'provider' => 'real_time',
                        'updated_by_user_id' => $this->context->getUserId(),
                        'ip_address' => $this->context->getIpAddress(),
                    ]);
                } else {
                    $results['failed']++;
                    $totalFailures++;
                    $results['errors'][] = "Could not fetch rate for {$baseCurrency} to {$currency}";
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $totalFailures++;
                $results['errors'][] = "Error updating {$baseCurrency} to {$currency}: {$e->getMessage()}";
            }
        }

        // Log batch update completion
        $this->audit('exchange_rate.batch_update_completed', [
            'company_id' => $targetCompanyId,
            'base_currency' => $baseCurrency,
            'total_updated' => $totalUpdates,
            'total_failed' => $totalFailures,
            'updated_by_user_id' => $this->context->getUserId(),
            'ip_address' => $this->context->getIpAddress(),
        ]);

        Log::info('Exchange rate batch update completed', [
            'company_id' => $targetCompanyId,
            'base_currency' => $baseCurrency,
            'updated' => $totalUpdates,
            'failed' => $totalFailures,
            'user_id' => $this->context->getUserId(),
            'ip' => $this->context->getIpAddress(),
        ]);

        return $results;
    }

    /**
     * Convert amount from one currency to another.
     */
    public function convertAmount(float $amount, string $fromCurrency, string $toCurrency, ?Carbon $date = null): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $rate = $this->getExchangeRateForDate($fromCurrency, $toCurrency, $date);

        if ($rate === null) {
            throw new \RuntimeException("No exchange rate available for {$fromCurrency} to {$toCurrency}");
        }

        return round($amount * $rate, 8);
    }

    /**
     * Get exchange rate for a specific date.
     */
    private function getExchangeRateForDate(string $fromCurrency, string $toCurrency, ?Carbon $date): ?float
    {
        if ($date === null || $date->isToday()) {
            return $this->getRealTimeExchangeRate($fromCurrency, $toCurrency);
        }

        // Look for historical rates
        $rate = CurrencyRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('valid_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->where('valid_until', '>=', $date)
                    ->orWhereNull('valid_until');
            })
            ->orderBy('valid_from', 'desc')
            ->first();

        return $rate?->rate;
    }

    /**
     * Calculate unrealized gains/losses for foreign currency balances.
     */
    public function calculateUnrealizedGainsLosses(Company $company): array
    {
        $gainsLosses = [];
        $baseCurrency = $company->currency_code ?? 'USD';

        // Get foreign currency balances from receivables
        $receivables = \App\Models\Invoice::where('company_id', $company->id)
            ->where('currency_code', '!=', $baseCurrency)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($receivables as $invoice) {
            $currency = $invoice->currency_code;
            $balanceDue = $invoice->balance_due;

            // Get original rate (when invoice was created)
            $originalRate = $this->getExchangeRateForDate($currency, $baseCurrency, $invoice->created_at);
            $currentRate = $this->getRealTimeExchangeRate($currency, $baseCurrency);

            if ($originalRate && $currentRate) {
                $originalBaseAmount = $balanceDue * $originalRate;
                $currentBaseAmount = $balanceDue * $currentRate;
                $unrealizedGainLoss = $currentBaseAmount - $originalBaseAmount;

                $gainsLosses[$currency] = ($gainsLosses[$currency] ?? 0) + $unrealizedGainLoss;
            }
        }

        // Get foreign currency balances from payables
        $payables = \App\Models\Bill::where('company_id', $company->id)
            ->where('currency', '!=', $baseCurrency)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->get();

        foreach ($payables as $bill) {
            $currency = $bill->currency;
            $balanceDue = $bill->balance_due;

            $originalRate = $this->getExchangeRateForDate($currency, $baseCurrency, $bill->created_at);
            $currentRate = $this->getRealTimeExchangeRate($currency, $baseCurrency);

            if ($originalRate && $currentRate) {
                $originalBaseAmount = $balanceDue * $originalRate;
                $currentBaseAmount = $balanceDue * $currentRate;
                $unrealizedGainLoss = $originalBaseAmount - $currentBaseAmount; // Note: reversed for liabilities

                $gainsLosses[$currency] = ($gainsLosses[$currency] ?? 0) + $unrealizedGainLoss;
            }
        }

        return $gainsLosses;
    }

    /**
     * Get exchange rate volatility data for reporting.
     */
    public function getExchangeRateVolatility(string $fromCurrency, string $toCurrency, int $days = 30): array
    {
        $endDate = now();
        $startDate = $endDate->copy()->subDays($days);

        $rates = CurrencyRate::where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->whereBetween('valid_from', [$startDate, $endDate])
            ->orderBy('valid_from')
            ->get();

        if ($rates->isEmpty()) {
            return [
                'volatility' => 0,
                'min_rate' => null,
                'max_rate' => null,
                'avg_rate' => null,
                'trend' => 'stable',
            ];
        }

        $rateValues = $rates->pluck('rate')->toArray();
        $minRate = min($rateValues);
        $maxRate = max($rateValues);
        $avgRate = array_sum($rateValues) / count($rateValues);

        // Calculate volatility as percentage change
        $volatility = $avgRate > 0 ? (($maxRate - $minRate) / $avgRate) * 100 : 0;

        // Determine trend
        $firstHalf = array_slice($rateValues, 0, count($rateValues) / 2);
        $secondHalf = array_slice($rateValues, count($rateValues) / 2);

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $trend = $secondAvg > $firstAvg ? 'increasing' : ($secondAvg < $firstAvg ? 'decreasing' : 'stable');

        return [
            'volatility' => round($volatility, 2),
            'min_rate' => $minRate,
            'max_rate' => $maxRate,
            'avg_rate' => round($avgRate, 6),
            'trend' => $trend,
            'data_points' => $rates->count(),
        ];
    }

    /**
     * Schedule automatic exchange rate updates.
     */
    public function scheduleAutomaticUpdates(): void
    {
        // This would be used with Laravel's task scheduler
        // Example: $schedule->job(new UpdateExchangeRatesJob)->dailyAt('01:00');

        $results = $this->updateExchangeRates();

        Log::info('Automatic exchange rate update completed', [
            'updated' => $results['updated'],
            'failed' => $results['failed'],
            'errors' => $results['errors'],
        ]);
    }

    /**
     * Validate user can access the company
     */
    private function validateCompanyAccess(string $companyId): void
    {
        $user = $this->context->getUser();

        if (! $user) {
            throw new \InvalidArgumentException('User context is required');
        }

        // Check if user belongs to this company
        if (! $user->companies()->where('company_id', $companyId)->exists()) {
            throw new \InvalidArgumentException('User does not have access to this company');
        }

        // Additional validation for active company membership
        $companyMembership = $user->companies()
            ->where('company_id', $companyId)
            ->wherePivot('is_active', true)
            ->first();

        if (! $companyMembership) {
            throw new \InvalidArgumentException('User access to this company is not active');
        }
    }

    /**
     * Set RLS context for database operations
     */
    private function setRlsContext(string $companyId): void
    {
        \DB::statement('SET app.current_company_id = ?', [$companyId]);
        \DB::statement('SET app.current_user_id = ?', [$this->context->getUserId()]);
    }
}
