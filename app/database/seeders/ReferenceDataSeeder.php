<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Languages
        $languages = [
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English', 'iso_639_1' => 'en', 'iso_639_2' => 'eng', 'script' => 'Latn', 'rtl' => false],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'iso_639_1' => 'ar', 'iso_639_2' => 'ara', 'script' => 'Arab', 'rtl' => true],
        ];
        foreach ($languages as $l) {
            DB::table('languages')->updateOrInsert(['code' => $l['code']], $l + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Currencies
        $currencies = [
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'USD',
                'numeric_code' => '840',
                'name' => 'US Dollar',
                'symbol' => '$',
                'symbol_position' => 'before',
                'minor_unit' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 1.0,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'EUR',
                'numeric_code' => '978',
                'name' => 'Euro',
                'symbol' => '€',
                'symbol_position' => 'before',
                'minor_unit' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 0.85,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'GBP',
                'numeric_code' => '826',
                'name' => 'British Pound',
                'symbol' => '£',
                'symbol_position' => 'before',
                'minor_unit' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 0.73,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'JPY',
                'numeric_code' => '392',
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'symbol_position' => 'before',
                'minor_unit' => 0,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'cash_minor_unit' => 0,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 110.0,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'AED',
                'numeric_code' => '784',
                'name' => 'UAE Dirham',
                'symbol' => 'د.إ',
                'symbol_position' => 'before',
                'minor_unit' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 3.67,
            ],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'code' => 'PKR',
                'numeric_code' => '586',
                'name' => 'Pakistani Rupee',
                'symbol' => '₨',
                'symbol_position' => 'before',
                'minor_unit' => 2,
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 160.0,
            ],
        ];
        foreach ($currencies as $c) {
            DB::table('currencies')->updateOrInsert(['code' => $c['code']], $c + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Countries
        $countries = [
            ['code' => 'US', 'alpha3' => 'USA', 'name' => 'United States', 'native_name' => null, 'region' => 'Americas', 'subregion' => 'Northern America', 'emoji' => '🇺🇸', 'capital' => 'Washington, D.C.', 'calling_code' => '+1', 'eea_member' => false],
            ['code' => 'AE', 'alpha3' => 'ARE', 'name' => 'United Arab Emirates', 'native_name' => 'الإمارات', 'region' => 'Asia', 'subregion' => 'Western Asia', 'emoji' => '🇦🇪', 'capital' => 'Abu Dhabi', 'calling_code' => '+971', 'eea_member' => false],
        ];
        foreach ($countries as $c) {
            DB::table('countries')->updateOrInsert(['code' => $c['code']], $c + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Locales
        $locales = [
            ['tag' => 'en-US', 'name' => 'English (United States)', 'native_name' => 'English (United States)', 'language_code' => 'en', 'country_code' => 'US', 'script' => 'Latn', 'variant' => null],
            ['tag' => 'en-AE', 'name' => 'English (UAE)', 'native_name' => 'English (UAE)', 'language_code' => 'en', 'country_code' => 'AE', 'script' => 'Latn', 'variant' => null],
            ['tag' => 'ar-AE', 'name' => 'Arabic (UAE)', 'native_name' => 'العربية (الإمارات)', 'language_code' => 'ar', 'country_code' => 'AE', 'script' => 'Arab', 'variant' => null],
        ];
        foreach ($locales as $loc) {
            DB::table('locales')->updateOrInsert(['tag' => $loc['tag']], $loc + ['created_at' => now(), 'updated_at' => now()]);
        }

        // Pivots — languages per country
        $countryLanguages = [
            ['country_code' => 'US', 'language_code' => 'en', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'AE', 'language_code' => 'ar', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'AE', 'language_code' => 'en', 'official' => false, 'primary' => false, 'order' => 1],
        ];
        foreach ($countryLanguages as $cl) {
            DB::table('country_language')->updateOrInsert(
                ['country_code' => $cl['country_code'], 'language_code' => $cl['language_code']],
                $cl + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Pivots — currencies per country
        $countryCurrencies = [
            ['country_code' => 'US', 'currency_code' => 'USD', 'official' => true],
            ['country_code' => 'AE', 'currency_code' => 'AED', 'official' => true],
        ];
        foreach ($countryCurrencies as $cc) {
            DB::table('country_currency')->updateOrInsert(
                ['country_code' => $cc['country_code'], 'currency_code' => $cc['currency_code']],
                $cc + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
