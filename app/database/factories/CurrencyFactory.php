<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'Fr'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨'],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼'],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك'],
            ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'ر.ق'],
            ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => 'ر.ع.'],
            ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => 'د.ب'],
        ];

        $currency = fake()->randomElement($currencies);

        return [
            'id' => fake()->uuid(),
            'code' => $currency['code'],
            'name' => $currency['name'],
            'symbol' => $currency['symbol'],
            'symbol_position' => fake()->randomElement(['before', 'after']),
            'minor_unit' => in_array($currency['code'], ['JPY', 'KRW']) ? 0 : 2,
            'thousands_separator' => fake()->randomElement([',', '.', ' ', "'"]),
            'decimal_separator' => fake()->randomElement(['.', ',']),
            'is_active' => true,
            'exchange_rate' => $currency['code'] === 'USD' ? 1.0 : fake()->randomFloat(6, 0.1, 10),
            'last_updated_at' => now(),
            'metadata' => [
                'created_by' => 'factory',
                'currency_type' => in_array($currency['code'], ['USD', 'EUR', 'GBP', 'JPY']) ? 'major' : 'other',
            ],
        ];
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'exchange_rate' => 1.0,
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'base', 'major' => true]),
        ]);
    }

    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'EUR',
            'name' => 'Euro',
            'symbol' => '€',
            'exchange_rate' => fake()->randomFloat(6, 0.8, 1.2),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'major', 'eurozone' => true]),
        ]);
    }

    public function gbp(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'GBP',
            'name' => 'British Pound',
            'symbol' => '£',
            'exchange_rate' => fake()->randomFloat(6, 0.6, 0.9),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'major']),
        ]);
    }

    public function jpy(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'JPY',
            'name' => 'Japanese Yen',
            'symbol' => '¥',
            'minor_unit' => 0,
            'exchange_rate' => fake()->randomFloat(6, 100, 160),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'major']),
        ]);
    }

    public function aed(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'AED',
            'name' => 'UAE Dirham',
            'symbol' => 'د.إ',
            'exchange_rate' => fake()->randomFloat(6, 3.5, 3.8),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'gcc', 'region' => 'middle_east']),
        ]);
    }

    public function pkr(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'PKR',
            'name' => 'Pakistani Rupee',
            'symbol' => '₨',
            'exchange_rate' => fake()->randomFloat(6, 250, 350),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'emerging', 'region' => 'south_asia']),
        ]);
    }

    public function inr(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'INR',
            'name' => 'Indian Rupee',
            'symbol' => '₹',
            'exchange_rate' => fake()->randomFloat(6, 70, 90),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'emerging', 'region' => 'south_asia']),
        ]);
    }

    public function cad(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'CAD',
            'name' => 'Canadian Dollar',
            'symbol' => 'C$',
            'exchange_rate' => fake()->randomFloat(6, 1.2, 1.4),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'major', 'region' => 'north_america']),
        ]);
    }

    public function aud(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'AUD',
            'name' => 'Australian Dollar',
            'symbol' => 'A$',
            'exchange_rate' => fake()->randomFloat(6, 1.4, 1.7),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'major', 'region' => 'oceania']),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'inactivation_reason' => fake()->randomElement(['deprecated', 'country_ceased', 'currency_reform']),
            ]),
        ]);
    }

    public function withExchangeRate(float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'exchange_rate' => $rate,
            'last_updated_at' => now(),
        ]);
    }

    public function withSymbolBefore(): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol_position' => 'before',
        ]);
    }

    public function withSymbolAfter(): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol_position' => 'after',
        ]);
    }

    public function withNoDecimals(): static
    {
        return $this->state(fn (array $attributes) => [
            'minor_unit' => 0,
        ]);
    }

    public function withThreeDecimals(): static
    {
        return $this->state(fn (array $attributes) => [
            'minor_unit' => 3,
        ]);
    }

    public function fiat(): static
    {
        $fiatCodes = ['USD', 'EUR', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'INR', 'PKR', 'AED'];
        $currency = fake()->randomElement($fiatCodes);

        return $this->state(fn (array $attributes) => [
            'code' => $currency,
            'name' => $this->getCurrencyName($currency),
            'symbol' => $this->getCurrencySymbol($currency),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'fiat']),
        ]);
    }

    public function crypto(): static
    {
        $cryptoCurrencies = [
            ['code' => 'BTC', 'name' => 'Bitcoin', 'symbol' => '₿'],
            ['code' => 'ETH', 'name' => 'Ethereum', 'symbol' => 'Ξ'],
            ['code' => 'LTC', 'name' => 'Litecoin', 'symbol' => 'Ł'],
        ];

        $crypto = fake()->randomElement($cryptoCurrencies);

        return $this->state(fn (array $attributes) => [
            'code' => $crypto['code'],
            'name' => $crypto['name'],
            'symbol' => $crypto['symbol'],
            'minor_unit' => 8,
            'exchange_rate' => fake()->randomFloat(8, 0.00001, 100000),
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'cryptocurrency']),
        ]);
    }

    public function gcc(): static
    {
        $gccCurrencies = [
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼'],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'د.ك'],
            ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'ر.ق'],
            ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => 'ر.ع.'],
            ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => 'د.ب'],
        ];

        $currency = fake()->randomElement($gccCurrencies);

        return $this->state(fn (array $attributes) => [
            'code' => $currency['code'],
            'name' => $currency['name'],
            'symbol' => $currency['symbol'],
            'metadata' => array_merge($attributes['metadata'] ?? [], ['currency_type' => 'gcc', 'region' => 'middle_east']),
        ]);
    }

    private function getCurrencyName(string $code): string
    {
        $names = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'JPY' => 'Japanese Yen',
            'CAD' => 'Canadian Dollar',
            'AUD' => 'Australian Dollar',
            'CHF' => 'Swiss Franc',
            'CNY' => 'Chinese Yuan',
            'INR' => 'Indian Rupee',
            'PKR' => 'Pakistani Rupee',
        ];

        return $names[$code] ?? 'Unknown Currency';
    }

    private function getCurrencySymbol(string $code): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'Fr',
            'CNY' => '¥',
            'INR' => '₹',
            'PKR' => '₨',
        ];

        return $symbols[$code] ?? '$';
    }
}
