<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use Brick\Money\Currency as BrickCurrency;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CurrencyService
{
    private function logAudit(string $action, array $params, ?User $user = null, ?string $companyId = null, ?string $idempotencyKey = null, ?array $result = null): void
    {
        try {
            DB::transaction(function () use ($action, $params, $user, $companyId, $idempotencyKey, $result) {
                DB::table('acct.audit_logs')->insert([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $user?->id,
                    'company_id' => $companyId,
                    'action' => $action,
                    'params' => json_encode($params),
                    'result' => $result ? json_encode($result) : null,
                    'idempotency_key' => $idempotencyKey,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function convertCurrency(
        Money $amount,
        string $fromCurrency,
        string $toCurrency,
        ?string $date = null,
        ?float $customRate = null
    ): Money {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $exchangeRate = $customRate ?? $this->getExchangeRate($fromCurrency, $toCurrency, $date);

        $convertedAmount = $amount->multipliedBy($exchangeRate);

        return Money::of(
            $convertedAmount->getAmount()->toFloat(),
            $toCurrency
        );
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency, ?string $date = null): float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $date = $date ?? now()->toDateString();

        $fromId = Currency::where('code', strtoupper($fromCurrency))->value('id');
        $toId = Currency::where('code', strtoupper($toCurrency))->value('id');

        if (! $fromId || ! $toId) {
            throw new \InvalidArgumentException('Unknown currency code supplied');
        }

        $exchangeRate = ExchangeRate::where('base_currency_id', $fromId)
            ->where('target_currency_id', $toId)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        if (! $exchangeRate) {
            $reverseRate = ExchangeRate::where('base_currency_id', $toId)
                ->where('target_currency_id', $fromId)
                ->where('effective_date', '<=', $date)
                ->orderBy('effective_date', 'desc')
                ->first();

            if ($reverseRate) {
                return 1 / $reverseRate->rate;
            }

            return $this->getFallbackExchangeRate($fromCurrency, $toCurrency);
        }

        return $exchangeRate->rate;
    }

    public function updateExchangeRate(
        string $fromCurrency,
        string $toCurrency,
        float $rate,
        ?string $effectiveDate = null,
        ?string $source = null
    ): ExchangeRate {
        $fromId = Currency::where('code', strtoupper($fromCurrency))->value('id');
        $toId = Currency::where('code', strtoupper($toCurrency))->value('id');

        if (! $fromId || ! $toId) {
            throw new \InvalidArgumentException('Unknown currency code supplied');
        }

        $result = DB::transaction(function () use ($fromId, $toId, $rate, $effectiveDate, $source) {
            $exchangeRate = ExchangeRate::updateOrCreate(
                [
                    'base_currency_id' => $fromId,
                    'target_currency_id' => $toId,
                    'effective_date' => $effectiveDate ?? now()->toDateString(),
                ],
                [
                    'rate' => $rate,
                    'source' => $source ?? 'manual',
                    'is_active' => true,
                ]
            );

            return $exchangeRate;
        });

        // Attach transient attributes for API consumers expecting codes
        $result->setAttribute('from_currency', strtoupper($fromCurrency));
        $result->setAttribute('to_currency', strtoupper($toCurrency));

        $this->logAudit('currency.exchange_rate.update', [
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'rate' => $rate,
            'effective_date' => $result->effective_date,
            'source' => $source,
        ], auth()->user(), result: ['exchange_rate_id' => $result->id]);

        return $result;
    }

    public function getCompanyCurrencies(Company $company): array
    {
        $baseCurrency = $company->getDefaultCurrency();
        $enabledCurrencies = $company->enabledCurrencies;

        return [
            'base_currency' => [
                'code' => $baseCurrency->code,
                'name' => $baseCurrency->name,
                'symbol' => $baseCurrency->symbol,
                'is_base' => true,
            ],
            'enabled_currencies' => $enabledCurrencies->map(fn ($currency) => [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'is_enabled' => true,
            ])->toArray(),
            'available_currencies' => Currency::whereNotIn('code', [$baseCurrency->code])
                ->where('is_active', true)
                ->get()
                ->map(fn ($currency) => [
                    'code' => $currency->code,
                    'name' => $currency->name,
                    'symbol' => $currency->symbol,
                    'is_enabled' => $enabledCurrencies->contains('code', $currency->code),
                ])->toArray(),
        ];
    }

    public function enableCurrencyForCompany(Company $company, string $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)
            ->where('is_active', true)
            ->firstOrFail();

        if ($currency->code === $company->base_currency) {
            throw new \InvalidArgumentException('Base currency is already enabled');
        }

        if ($company->enabledCurrencies()->where('currency_id', $currency->id)->exists()) {
            throw new \InvalidArgumentException('Currency is already enabled for this company');
        }

        $company->enabledCurrencies()->attach($currency->id, [
            'enabled_at' => now(),
            'enabled_by_user_id' => auth()->id(),
        ]);

        $this->logAudit('currency.enable', [
            'company_id' => $company->id,
            'currency_code' => $currencyCode,
        ], auth()->user(), $company->id);
    }

    public function disableCurrencyForCompany(Company $company, string $currencyCode): void
    {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        if ($currency->code === $company->base_currency) {
            throw new \InvalidArgumentException('Cannot disable base currency');
        }

        $company->enabledCurrencies()->detach($currency->id);

        $this->logAudit('currency.disable', [
            'company_id' => $company->id,
            'currency_code' => $currencyCode,
        ], auth()->user(), $company->id);
    }

    public function formatMoney(Money $money, ?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        try {
            $brickCurrency = BrickCurrency::of($money->getCurrency()->getCurrencyCode());

            return $formatter->formatCurrency($money->getAmount()->toFloat(), $brickCurrency->getCurrencyCode());
        } catch (\Exception $e) {
            return $money->getCurrency()->getCurrencyCode().' '.number_format($money->getAmount()->toFloat(), 2);
        }
    }

    public function getCurrencySymbol(string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();

        return $currency?->symbol ?? $currencyCode;
    }

    public function validateCurrencyCode(string $currencyCode): bool
    {
        return Currency::where('code', $currencyCode)
            ->where('is_active', true)
            ->exists();
    }

    public function getCurrencyPrecision(string $currencyCode): int
    {
        $currency = Currency::where('code', $currencyCode)->first();

        return $currency?->decimal_places ?? 2;
    }

    public function roundToCurrencyPrecision(Money $money): Money
    {
        $precision = $this->getCurrencyPrecision($money->getCurrency()->getCurrencyCode());
        $roundedAmount = round($money->getAmount()->toFloat(), $precision);

        return Money::of($roundedAmount, $money->getCurrency()->getCurrencyCode());
    }

    public function getExchangeRateHistory(
        string $fromCurrency,
        string $toCurrency,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        $fromId = Currency::where('code', strtoupper($fromCurrency))->value('id');
        $toId = Currency::where('code', strtoupper($toCurrency))->value('id');

        if (! $fromId || ! $toId) {
            return [];
        }

        $query = ExchangeRate::where('base_currency_id', $fromId)
            ->where('target_currency_id', $toId);

        if ($startDate) {
            $query->where('effective_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('effective_date', '<=', $endDate);
        }

        return $query->orderBy('effective_date', 'desc')
            ->get()
            ->map(fn ($rate) => [
                'date' => $rate->effective_date,
                'rate' => $rate->rate,
                'source' => $rate->source,
                'created_at' => $rate->created_at,
                'updated_at' => $rate->updated_at,
            ])
            ->toArray();
    }

    public function getLatestExchangeRates(string $baseCurrency = 'USD'): array
    {
        $allCurrencies = Currency::where('is_active', true)->pluck('code');
        $rates = [];

        foreach ($allCurrencies as $currency) {
            if ($currency !== $baseCurrency) {
                try {
                    $rate = $this->getExchangeRate($baseCurrency, $currency);
                    $rates[$currency] = [
                        'rate' => $rate,
                        'inverse_rate' => 1 / $rate,
                    ];
                } catch (\Exception $e) {
                    Log::warning("Failed to get exchange rate for {$baseCurrency} to {$currency}", [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'base_currency' => $baseCurrency,
            'rates' => $rates,
            'as_of_date' => now()->toDateString(),
        ];
    }

    public function calculateCurrencyImpact(
        Company $company,
        array $transactions,
        string $targetCurrency
    ): array {
        $baseCurrency = $company->base_currency;
        $totalImpact = Money::of(0, $targetCurrency);
        $detailedImpact = [];

        foreach ($transactions as $transaction) {
            $amount = Money::of($transaction['amount'], $transaction['currency']);

            $convertedAmount = $this->convertCurrency(
                $amount,
                $transaction['currency'],
                $targetCurrency,
                $transaction['date'] ?? null
            );

            $baseAmount = $this->convertCurrency(
                $amount,
                $transaction['currency'],
                $baseCurrency,
                $transaction['date'] ?? null
            );

            $totalImpact = $totalImpact->plus($convertedAmount);

            $detailedImpact[] = [
                'transaction_id' => $transaction['id'],
                'original_amount' => $this->formatMoney($amount),
                'converted_amount' => $this->formatMoney($convertedAmount),
                'base_amount' => $this->formatMoney($baseAmount),
                'exchange_rate_used' => $this->getExchangeRate($transaction['currency'], $targetCurrency, $transaction['date'] ?? null),
            ];
        }

        return [
            'total_impact' => $this->formatMoney($totalImpact),
            'base_currency' => $baseCurrency,
            'target_currency' => $targetCurrency,
            'transaction_count' => count($transactions),
            'detailed_impact' => $detailedImpact,
            'calculated_at' => now()->toISOString(),
        ];
    }

    public function validateCurrencyConversion(
        string $fromCurrency,
        string $toCurrency,
        float $amount
    ): void {
        if (! $this->validateCurrencyCode($fromCurrency)) {
            throw new \InvalidArgumentException("Invalid from currency: {$fromCurrency}");
        }

        if (! $this->validateCurrencyCode($toCurrency)) {
            throw new \InvalidArgumentException("Invalid to currency: {$toCurrency}");
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if ($fromCurrency === $toCurrency) {
            throw new \InvalidArgumentException('From and to currencies must be different');
        }
    }

    public function getCurrencyBalances(Company $company): array
    {
        $baseCurrency = $company->base_currency;
        $enabledCurrencies = $company->enabledCurrencies->pluck('code');
        $allCurrencies = collect([$baseCurrency])->merge($enabledCurrencies);

        $balances = [];

        foreach ($allCurrencies as $currency) {
            $currencyBalance = $this->calculateCurrencyBalance($company, $currency);
            $balances[$currency] = [
                'currency_code' => $currency,
                'balance' => $currencyBalance->getAmount()->toFloat(),
                'formatted_balance' => $this->formatMoney($currencyBalance),
                'in_base_currency' => $this->formatMoney(
                    $this->convertCurrency($currencyBalance, $currency, $baseCurrency)
                ),
            ];
        }

        return [
            'company_id' => $company->id,
            'base_currency' => $baseCurrency,
            'balances' => $balances,
            'calculated_at' => now()->toISOString(),
        ];
    }

    private function calculateCurrencyBalance(Company $company, string $currencyCode): Money
    {
        $totalPaid = $company->invoices()
            ->join('payment_allocations', 'invoices.id', '=', 'payment_allocations.invoice_id')
            ->join('payments', 'payment_allocations.payment_id', '=', 'payments.id')
            ->where('payments.currency_id', Currency::where('code', $currencyCode)->value('id'))
            ->where('payment_allocations.status', 'active')
            ->sum('payment_allocations.amount');

        $totalReceived = $company->payments()
            ->where('currency_id', Currency::where('code', $currencyCode)->value('id'))
            ->where('status', 'completed')
            ->sum('amount');

        return Money::of($totalReceived - $totalPaid, $currencyCode);
    }

    private function getFallbackExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $fallbackRates = [
            'USD_EUR' => 0.85,
            'USD_GBP' => 0.73,
            'USD_AED' => 3.67,
            'USD_PKR' => 280.0,
            'EUR_USD' => 1.18,
            'GBP_USD' => 1.37,
            'AED_USD' => 0.27,
            'PKR_USD' => 0.0036,
        ];

        $key = $fromCurrency.'_'.$toCurrency;

        if (isset($fallbackRates[$key])) {
            Log::info('Using fallback exchange rate', [
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'rate' => $fallbackRates[$key],
            ]);

            return $fallbackRates[$key];
        }

        throw new \InvalidArgumentException("No exchange rate available for {$fromCurrency} to {$toCurrency}");
    }

    public function syncExchangeRatesFromAPI(string $provider = 'fixer'): array
    {
        $currencies = Currency::where('is_active', true)->pluck('code');
        $baseCurrency = 'USD';
        $results = [];
        $rateDate = null;

        try {
            foreach ($currencies as $currency) {
                if ($currency !== $baseCurrency) {
                    $rateData = $this->fetchExchangeRateFromAPI($baseCurrency, $currency, $provider);

                    if ($rateData !== null) {
                        // If rateData is an array, it contains rate and date
                        if (is_array($rateData)) {
                            $rate = $rateData['rate'];
                            $rateDate = $rateData['date'];
                        } else {
                            $rate = $rateData;
                            $rateDate = $rateDate ?? now()->toDateString();
                        }

                        $this->updateExchangeRate($baseCurrency, $currency, $rate, $rateDate, $provider);
                        $results[$currency] = ['success' => true, 'rate' => $rate];
                    } else {
                        $results[$currency] = ['success' => false, 'error' => 'Failed to fetch rate'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync exchange rates from API', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            $results['error'] = $e->getMessage();
        }

        return [
            'provider' => $provider,
            'base_currency' => $baseCurrency,
            'results' => $results,
            'synced_at' => now()->toISOString(),
        ];
    }

    private function fetchExchangeRateFromAPI(string $fromCurrency, string $toCurrency, string $provider)
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return ['rate' => 1.0, 'date' => now()->toDateString()];
        }

        if ($provider !== 'ecb') {
            return null; // only ECB implemented for now
        }

        try {
            $xml = @file_get_contents('https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml');
            if ($xml === false) {
                return null;
            }
            $doc = new \SimpleXMLElement($xml);

            // Extract the date from the Cube element
            $date = (string) $doc->Cube->Cube['time'];
            if (!$date) {
                $date = now()->toDateString();
            }

            $rates = ['EUR' => 1.0];
            foreach ($doc->Cube->Cube->Cube as $cube) {
                $ccy = (string) $cube['currency'];
                $rate = (float) $cube['rate'];
                if ($ccy && $rate > 0) {
                    $rates[$ccy] = $rate;
                }
            }

            if (! isset($rates[$from]) && $from !== 'EUR') {
                return null;
            }
            if (! isset($rates[$to]) && $to !== 'EUR') {
                return null;
            }

            if ($from === 'EUR') {
                return ['rate' => $rates[$to] ?? null, 'date' => $date];
            }
            if ($to === 'EUR') {
                $rate = isset($rates[$from]) ? 1.0 / $rates[$from] : null;
                return ['rate' => $rate, 'date' => $date];
            }

            $rate = ($rates[$to] ?? null) && ($rates[$from] ?? null)
                ? $rates[$to] / $rates[$from]
                : null;

            return ['rate' => $rate, 'date' => $date];
        } catch (\Throwable $e) {
            Log::warning('ECB fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
