<?php

namespace App\Services;

use App\Models\Currency;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalCurrencyImportService
{
    public function __construct() {}

    /**
     * Import currencies from external service
     */
    public function importCurrencies(?string $source = null, ?string $baseCurrency = null): array
    {
        $source = 'ecb'; // Always use ECB as the only source

        try {
            return $this->importFromECB($baseCurrency);
        } catch (Exception $e) {
            Log::error("Currency import failed for source {$source}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Import from European Central Bank
     */
    protected function importFromECB(?string $baseCurrency = null): array
    {
        // Try to get from the European Central Bank directly
        $response = Http::timeout(30)
            ->withOptions([
                'verify' => true,
                'connect_timeout' => 10,
            ])
            ->get('https://cdn.jsdelivr.net/gh/fawazahmed0/currency-api@1/latest/currencies/usd.json');

        if (! $response->successful()) {
            // Fallback to exchangerate-api if the primary source fails
            $response = Http::timeout(30)
                ->withOptions([
                    'verify' => true,
                    'connect_timeout' => 10,
                ])
                ->get('https://open.er-api.com/v6/latest/USD');

            if (! $response->successful()) {
                throw new Exception('All currency API sources failed');
            }

            $data = $response->json();
            if (! isset($data['rates']) || ! is_array($data['rates'])) {
                throw new Exception('Invalid API response structure from fallback source');
            }

            return $this->processExchangeRates($data['rates'], $baseCurrency);
        }

        $data = $response->json();

        // Handle the response format from jsdelivr currency API
        if (! isset($data['usd']) || ! is_array($data['usd'])) {
            throw new Exception('Invalid API response structure from primary source');
        }

        // Convert the format: rates are nested under 'usd' key
        $rates = $data['usd'];
        unset($rates['date']); // Remove the date entry

        return $this->processExchangeRates($rates, $baseCurrency);
    }

    /**
     * Process exchange rates into currency format
     */
    private function processExchangeRates(array $rates, ?string $baseCurrency = null): array
    {
        $currencies = [];
        $baseCurrency = $baseCurrency ?? 'USD';

        // Get the rate for the base currency (all rates are relative to USD)
        $baseRate = $baseCurrency === 'USD' ? 1.0 : ($rates[$baseCurrency] ?? 1.0);

        // Base USD currency (always included for reference)
        $currencies[] = [
            'code' => 'USD',
            'numeric_code' => '840',
            'name' => 'US Dollar',
            'symbol' => '$',
            'symbol_position' => 'before',
            'thousands_separator' => ',',
            'decimal_separator' => '.',
            'minor_unit' => 2,
            'exchange_rate' => $baseCurrency === 'USD' ? 1.0 : (1.0 / $baseRate),
            'last_updated_at' => now(),
            'metadata' => [
                'source' => 'ecb',
                'type' => 'fiat',
            ],
        ];

        // Process rates and create currency entries
        foreach ($rates as $code => $rate) {
            // Skip if this is the base currency (already handled above)
            if ($code === 'USD' && $baseCurrency === 'USD') {
                continue;
            }

            $currencyInfo = $this->getCurrencyInfo($code);

            if ($currencyInfo) {
                // Calculate exchange rate relative to base currency
                // If USD is the base, use the rate directly
                // If another currency is the base, calculate: (USD_to_target) / (USD_to_base)
                $exchangeRate = $baseCurrency === 'USD' ? $rate : ($rate / $baseRate);

                $currencies[] = [
                    'code' => $code,
                    'numeric_code' => $currencyInfo['numeric_code'],
                    'name' => $currencyInfo['name'],
                    'symbol' => $currencyInfo['symbol'],
                    'symbol_position' => $currencyInfo['symbol_position'] ?? 'before',
                    'thousands_separator' => ',',
                    'decimal_separator' => '.',
                    'minor_unit' => $currencyInfo['minor_unit'] ?? 2,
                    'exchange_rate' => $exchangeRate,
                    'last_updated_at' => now(),
                    'metadata' => [
                        'source' => 'ecb',
                        'type' => 'fiat',
                    ],
                ];
            }
        }

        return $currencies;
    }

    /**
     * Import from Fixer.io
     */
    protected function importFromFixer(): array
    {
        $apiKey = config('services.fixer.api_key');

        if (! $apiKey) {
            throw new Exception('Fixer API key not configured');
        }

        $response = Http::timeout(30)
            ->withOptions([
                'verify' => true,
                'connect_timeout' => 10,
            ])
            ->get('https://data.fixer.io/api/latest', [
                'access_key' => $apiKey,
                'base' => 'USD',
            ]);

        if (! $response->successful()) {
            throw new Exception('Fixer API request failed: '.$response->status());
        }

        $data = $response->json();

        if (! $data['success']) {
            throw new Exception('Fixer API error: '.($data['error']['info'] ?? 'Unknown error'));
        }

        $currencies = [];

        // Base USD currency
        $currencies[] = [
            'code' => 'USD',
            'numeric_code' => '840',
            'name' => 'US Dollar',
            'symbol' => '$',
            'symbol_position' => 'before',
            'thousands_separator' => ',',
            'decimal_separator' => '.',
            'minor_unit' => 2,
            'exchange_rate' => 1.0,
            'last_updated_at' => now(),
            'metadata' => [
                'source' => 'fixer',
                'type' => 'fiat',
            ],
        ];

        // Process rates
        foreach ($data['rates'] as $code => $rate) {
            $currencyInfo = $this->getCurrencyInfo($code);

            if ($currencyInfo) {
                $currencies[] = [
                    'code' => $code,
                    'numeric_code' => $currencyInfo['numeric_code'],
                    'name' => $currencyInfo['name'],
                    'symbol' => $currencyInfo['symbol'],
                    'symbol_position' => $currencyInfo['symbol_position'] ?? 'before',
                    'thousands_separator' => ',',
                    'decimal_separator' => '.',
                    'minor_unit' => $currencyInfo['minor_unit'] ?? 2,
                    'exchange_rate' => $rate,
                    'last_updated_at' => now(),
                    'metadata' => [
                        'source' => 'fixer',
                        'type' => 'fiat',
                    ],
                ];
            }
        }

        return $currencies;
    }

    /**
     * Import from ExchangeRate-API
     */
    protected function importFromExchangeRate(): array
    {
        $response = Http::timeout(30)
            ->withOptions([
                'verify' => true,
                'connect_timeout' => 10,
            ])
            ->get('https://open.er-api.com/v6/latest/USD');

        if (! $response->successful()) {
            throw new Exception('ExchangeRate API request failed: '.$response->status());
        }

        $data = $response->json();

        if ($data['result'] !== 'success') {
            throw new Exception('ExchangeRate API error: '.($data['error-type'] ?? 'Unknown error'));
        }

        $currencies = [];

        // Base USD currency
        $currencies[] = [
            'code' => 'USD',
            'numeric_code' => '840',
            'name' => 'US Dollar',
            'symbol' => '$',
            'symbol_position' => 'before',
            'thousands_separator' => ',',
            'decimal_separator' => '.',
            'minor_unit' => 2,
            'exchange_rate' => 1.0,
            'last_updated_at' => now(),
            'metadata' => [
                'source' => 'exchangerate',
                'type' => 'fiat',
            ],
        ];

        // Process rates
        foreach ($data['conversion_rates'] as $code => $rate) {
            $currencyInfo = $this->getCurrencyInfo($code);

            if ($currencyInfo) {
                $currencies[] = [
                    'code' => $code,
                    'numeric_code' => $currencyInfo['numeric_code'],
                    'name' => $currencyInfo['name'],
                    'symbol' => $currencyInfo['symbol'],
                    'symbol_position' => $currencyInfo['symbol_position'] ?? 'before',
                    'thousands_separator' => ',',
                    'decimal_separator' => '.',
                    'minor_unit' => $currencyInfo['minor_unit'] ?? 2,
                    'exchange_rate' => $rate,
                    'last_updated_at' => now(),
                    'metadata' => [
                        'source' => 'exchangerate',
                        'type' => 'fiat',
                    ],
                ];
            }
        }

        return $currencies;
    }

    /**
     * Get currency information from predefined data
     */
    protected function getCurrencyInfo(string $code): ?array
    {
        $currencyData = [
            'EUR' => ['numeric_code' => '978', 'name' => 'Euro', 'symbol' => '€', 'minor_unit' => 2],
            'GBP' => ['numeric_code' => '826', 'name' => 'British Pound', 'symbol' => '£', 'minor_unit' => 2],
            'JPY' => ['numeric_code' => '392', 'name' => 'Japanese Yen', 'symbol' => '¥', 'minor_unit' => 0],
            'CAD' => ['numeric_code' => '124', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'minor_unit' => 2],
            'AUD' => ['numeric_code' => '036', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'minor_unit' => 2],
            'CHF' => ['numeric_code' => '756', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'minor_unit' => 2],
            'CNY' => ['numeric_code' => '156', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'minor_unit' => 2],
            'INR' => ['numeric_code' => '356', 'name' => 'Indian Rupee', 'symbol' => '₹', 'minor_unit' => 2],
            'PKR' => ['numeric_code' => '586', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'minor_unit' => 2],
            'AED' => ['numeric_code' => '784', 'name' => 'UAE Dirham', 'symbol' => 'د.إ', 'symbol_position' => 'after', 'minor_unit' => 2],
            'SAR' => ['numeric_code' => '682', 'name' => 'Saudi Riyal', 'symbol' => 'ر.س', 'symbol_position' => 'after', 'minor_unit' => 2],
            'KWD' => ['numeric_code' => '414', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك', 'symbol_position' => 'after', 'minor_unit' => 3],
            'QAR' => ['numeric_code' => '634', 'name' => 'Qatari Riyal', 'symbol' => 'ر.ق', 'symbol_position' => 'after', 'minor_unit' => 2],
            'OMR' => ['numeric_code' => '512', 'name' => 'Omani Rial', 'symbol' => 'ر.ع', 'symbol_position' => 'after', 'minor_unit' => 3],
            'BHD' => ['numeric_code' => '048', 'name' => 'Bahraini Dinar', 'symbol' => 'د.ب', 'symbol_position' => 'after', 'minor_unit' => 3],
            'SEK' => ['numeric_code' => '752', 'name' => 'Swedish Krona', 'symbol' => 'kr', 'minor_unit' => 2],
            'NOK' => ['numeric_code' => '578', 'name' => 'Norwegian Krone', 'symbol' => 'kr', 'minor_unit' => 2],
            'DKK' => ['numeric_code' => '208', 'name' => 'Danish Krone', 'symbol' => 'kr', 'minor_unit' => 2],
            'PLN' => ['numeric_code' => '985', 'name' => 'Polish Zloty', 'symbol' => 'zł', 'minor_unit' => 2],
            'CZK' => ['numeric_code' => '203', 'name' => 'Czech Koruna', 'symbol' => 'Kč', 'minor_unit' => 2],
            'HUF' => ['numeric_code' => '348', 'name' => 'Hungarian Forint', 'symbol' => 'Ft', 'minor_unit' => 2],
            'RON' => ['numeric_code' => '946', 'name' => 'Romanian Leu', 'symbol' => 'lei', 'minor_unit' => 2],
            'BGN' => ['numeric_code' => '975', 'name' => 'Bulgarian Lev', 'symbol' => 'лв', 'minor_unit' => 2],
            'HRK' => ['numeric_code' => '191', 'name' => 'Croatian Kuna', 'symbol' => 'kn', 'minor_unit' => 2],
            'RUB' => ['numeric_code' => '643', 'name' => 'Russian Ruble', 'symbol' => '₽', 'minor_unit' => 2],
            'TRY' => ['numeric_code' => '949', 'name' => 'Turkish Lira', 'symbol' => '₺', 'minor_unit' => 2],
            'BRL' => ['numeric_code' => '986', 'name' => 'Brazilian Real', 'symbol' => 'R$', 'minor_unit' => 2],
            'MXN' => ['numeric_code' => '484', 'name' => 'Mexican Peso', 'symbol' => '$', 'minor_unit' => 2],
            'ARS' => ['numeric_code' => '032', 'name' => 'Argentine Peso', 'symbol' => '$', 'minor_unit' => 2],
            'CLP' => ['numeric_code' => '152', 'name' => 'Chilean Peso', 'symbol' => '$', 'minor_unit' => 0],
            'COP' => ['numeric_code' => '170', 'name' => 'Colombian Peso', 'symbol' => '$', 'minor_unit' => 2],
            'PEN' => ['numeric_code' => '604', 'name' => 'Peruvian Sol', 'symbol' => 'S/', 'minor_unit' => 2],
            'UYU' => ['numeric_code' => '858', 'name' => 'Uruguayan Peso', 'symbol' => '$U', 'minor_unit' => 2],
            'ZAR' => ['numeric_code' => '710', 'name' => 'South African Rand', 'symbol' => 'R', 'minor_unit' => 2],
            'NGN' => ['numeric_code' => '566', 'name' => 'Nigerian Naira', 'symbol' => '₦', 'minor_unit' => 2],
            'KES' => ['numeric_code' => '404', 'name' => 'Kenyan Shilling', 'symbol' => 'KSh', 'minor_unit' => 2],
            'GHS' => ['numeric_code' => '936', 'name' => 'Ghanaian Cedi', 'symbol' => '₵', 'minor_unit' => 2],
            'EGP' => ['numeric_code' => '818', 'name' => 'Egyptian Pound', 'symbol' => 'E£', 'minor_unit' => 2],
            'MYR' => ['numeric_code' => '458', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'minor_unit' => 2],
            'SGD' => ['numeric_code' => '702', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'minor_unit' => 2],
            'THB' => ['numeric_code' => '764', 'name' => 'Thai Baht', 'symbol' => '฿', 'minor_unit' => 2],
            'IDR' => ['numeric_code' => '360', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'minor_unit' => 2],
            'PHP' => ['numeric_code' => '608', 'name' => 'Philippine Peso', 'symbol' => '₱', 'minor_unit' => 2],
            'VND' => ['numeric_code' => '704', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'minor_unit' => 0],
            'KRW' => ['numeric_code' => '410', 'name' => 'South Korean Won', 'symbol' => '₩', 'minor_unit' => 0],
            'TWD' => ['numeric_code' => '901', 'name' => 'Taiwan Dollar', 'symbol' => 'NT$', 'minor_unit' => 2],
            'HKD' => ['numeric_code' => '344', 'name' => 'Hong Kong Dollar', 'symbol' => 'HK$', 'minor_unit' => 2],
            'NZD' => ['numeric_code' => '554', 'name' => 'New Zealand Dollar', 'symbol' => 'NZ$', 'minor_unit' => 2],
            'ILS' => ['numeric_code' => '376', 'name' => 'Israeli Shekel', 'symbol' => '₪', 'minor_unit' => 2],
        ];

        return $currencyData[$code] ?? null;
    }

    /**
     * Save imported currencies to database
     */
    public function saveImportedCurrencies(array $currencies, array $options = []): array
    {
        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($currencies as $currencyData) {
            try {
                $currency = Currency::withTrashed()->where('code', $currencyData['code'])->first();

                if (! $currency) {
                    // Create new currency
                    Currency::create($currencyData);
                    $results['created']++;
                } elseif (isset($options['update_existing']) && $options['update_existing']) {
                    // Update existing currency
                    $currency->update($currencyData);
                    $results['updated']++;
                } else {
                    // Skip existing currency
                    $results['skipped']++;
                }
            } catch (Exception $e) {
                $results['errors'][] = [
                    'code' => $currencyData['code'],
                    'error' => $e->getMessage(),
                ];
                Log::error("Failed to import currency {$currencyData['code']}: ".$e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Import specific currencies by their codes
     */
    public function importSpecificCurrencies(array $currencyCodes, ?string $source = null, ?string $baseCurrency = null): array
    {
        $source = $source ?? 'ecb';
        $allCurrencies = $this->importCurrencies($source, $baseCurrency);

        // Filter to only requested currencies
        $filteredCurrencies = array_filter($allCurrencies, function ($currency) use ($currencyCodes) {
            return in_array($currency['code'], $currencyCodes);
        });

        return array_values($filteredCurrencies);
    }

    /**
     * Get list of available import sources
     */
    public function getAvailableSources(): array
    {
        return [
            [
                'id' => 'ecb',
                'name' => 'European Central Bank',
                'description' => 'Free service with major world currencies via exchangerate.host',
                'requires_api_key' => false,
            ],
        ];
    }
}
