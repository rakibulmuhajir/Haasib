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
            ['code' => 'en', 'name' => 'English', 'native_name' => 'English'],
            ['code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية'],
            ['code' => 'zh', 'name' => 'Chinese', 'native_name' => '中文'],
            ['code' => 'hi', 'name' => 'Hindi', 'native_name' => 'हिन्दी'],
            ['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español'],
            ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français'],
            ['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch'],
            ['code' => 'ja', 'name' => 'Japanese', 'native_name' => '日本語'],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português'],
            ['code' => 'ru', 'name' => 'Russian', 'native_name' => 'Русский'],
            ['code' => 'ur', 'name' => 'Urdu', 'native_name' => 'اردو'],
        ];
        foreach ($languages as $l) {
            DB::table('public.languages')->updateOrInsert(['code' => $l['code']], $l + [
                'id' => \Illuminate\Support\Str::uuid(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
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
                'code' => 'PKR',
                'numeric_code' => '586',
                'name' => 'Pakistani Rupee',
                'symbol' => '₨',
                'symbol_position' => 'before',
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 160.0,
            ],
            [
                'code' => 'INR',
                'numeric_code' => '356',
                'name' => 'Indian Rupee',
                'symbol' => '₹',
                'symbol_position' => 'before',
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 75.0,
            ],
            [
                'code' => 'CNY',
                'numeric_code' => '156',
                'name' => 'Chinese Yuan',
                'symbol' => '¥',
                'symbol_position' => 'before',
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 6.5,
            ],
            [
                'code' => 'CAD',
                'numeric_code' => '124',
                'name' => 'Canadian Dollar',
                'symbol' => 'C$',
                'symbol_position' => 'before',
                'thousands_separator' => ',',
                'decimal_separator' => '.',
                'minor_unit' => 2,
                'cash_minor_unit' => 2,
                'rounding' => 0,
                'fund' => false,
                'is_active' => true,
                'exchange_rate' => 1.25,
            ],
        ];
        foreach ($currencies as $c) {
            // Only generate ID if not already present
            if (!isset($c['id'])) {
                $c['id'] = \Illuminate\Support\Str::uuid();
            }
            DB::table('public.currencies')->updateOrInsert(['code' => $c['code']], $c + [
                'last_updated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Countries
        $countries = [
            ['code' => 'US', 'alpha3' => 'USA', 'name' => 'United States', 'native_name' => 'United States', 'region' => 'Americas', 'subregion' => 'Northern America', 'emoji' => '🇺🇸', 'capital' => 'Washington, D.C.', 'calling_code' => '+1', 'eea_member' => false],
            ['code' => 'AE', 'alpha3' => 'ARE', 'name' => 'United Arab Emirates', 'native_name' => 'الإمارات العربية المتحدة', 'region' => 'Asia', 'subregion' => 'Western Asia', 'emoji' => '🇦🇪', 'capital' => 'Abu Dhabi', 'calling_code' => '+971', 'eea_member' => false],
            ['code' => 'GB', 'alpha3' => 'GBR', 'name' => 'United Kingdom', 'native_name' => 'United Kingdom', 'region' => 'Europe', 'subregion' => 'Northern Europe', 'emoji' => '🇬🇧', 'capital' => 'London', 'calling_code' => '+44', 'eea_member' => true],
            ['code' => 'PK', 'alpha3' => 'PAK', 'name' => 'Pakistan', 'native_name' => 'پاکستان', 'region' => 'Asia', 'subregion' => 'Southern Asia', 'emoji' => '🇵🇰', 'capital' => 'Islamabad', 'calling_code' => '+92', 'eea_member' => false],
            ['code' => 'IN', 'alpha3' => 'IND', 'name' => 'India', 'native_name' => 'भारत', 'region' => 'Asia', 'subregion' => 'Southern Asia', 'emoji' => '🇮🇳', 'capital' => 'New Delhi', 'calling_code' => '+91', 'eea_member' => false],
            ['code' => 'CN', 'alpha3' => 'CHN', 'name' => 'China', 'native_name' => '中国', 'region' => 'Asia', 'subregion' => 'Eastern Asia', 'emoji' => '🇨🇳', 'capital' => 'Beijing', 'calling_code' => '+86', 'eea_member' => false],
            ['code' => 'JP', 'alpha3' => 'JPN', 'name' => 'Japan', 'native_name' => '日本', 'region' => 'Asia', 'subregion' => 'Eastern Asia', 'emoji' => '🇯🇵', 'capital' => 'Tokyo', 'calling_code' => '+81', 'eea_member' => false],
            ['code' => 'DE', 'alpha3' => 'DEU', 'name' => 'Germany', 'native_name' => 'Deutschland', 'region' => 'Europe', 'subregion' => 'Western Europe', 'emoji' => '🇩🇪', 'capital' => 'Berlin', 'calling_code' => '+49', 'eea_member' => true],
            ['code' => 'FR', 'alpha3' => 'FRA', 'name' => 'France', 'native_name' => 'France', 'region' => 'Europe', 'subregion' => 'Western Europe', 'emoji' => '🇫🇷', 'capital' => 'Paris', 'calling_code' => '+33', 'eea_member' => true],
            ['code' => 'CA', 'alpha3' => 'CAN', 'name' => 'Canada', 'native_name' => 'Canada', 'region' => 'Americas', 'subregion' => 'Northern America', 'emoji' => '🇨🇦', 'capital' => 'Ottawa', 'calling_code' => '+1', 'eea_member' => false],
        ];
        foreach ($countries as $c) {
            // Only generate ID if not already present
            if (!isset($c['id'])) {
                $c['id'] = \Illuminate\Support\Str::uuid();
            }
            DB::table('public.countries')->updateOrInsert(['code' => $c['code']], $c + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Locales
        $locales = [
            ['code' => 'en-US', 'name' => 'English (United States)', 'native_name' => 'English (United States)', 'language_code' => 'en', 'country_code' => 'US'],
            ['code' => 'en-AE', 'name' => 'English (UAE)', 'native_name' => 'English (UAE)', 'language_code' => 'en', 'country_code' => 'AE'],
            ['code' => 'ar-AE', 'name' => 'Arabic (UAE)', 'native_name' => 'العربية (الإمارات)', 'language_code' => 'ar', 'country_code' => 'AE'],
            ['code' => 'en-GB', 'name' => 'English (United Kingdom)', 'native_name' => 'English (United Kingdom)', 'language_code' => 'en', 'country_code' => 'GB'],
            ['code' => 'en-IN', 'name' => 'English (India)', 'native_name' => 'English (India)', 'language_code' => 'en', 'country_code' => 'IN'],
            ['code' => 'hi-IN', 'name' => 'Hindi (India)', 'native_name' => 'हिन्दी (भारत)', 'language_code' => 'hi', 'country_code' => 'IN'],
            ['code' => 'zh-CN', 'name' => 'Chinese (China)', 'native_name' => '中文 (中国)', 'language_code' => 'zh', 'country_code' => 'CN'],
            ['code' => 'ja-JP', 'name' => 'Japanese (Japan)', 'native_name' => '日本語 (日本)', 'language_code' => 'ja', 'country_code' => 'JP'],
            ['code' => 'de-DE', 'name' => 'German (Germany)', 'native_name' => 'Deutsch (Deutschland)', 'language_code' => 'de', 'country_code' => 'DE'],
            ['code' => 'fr-FR', 'name' => 'French (France)', 'native_name' => 'Français (France)', 'language_code' => 'fr', 'country_code' => 'FR'],
            ['code' => 'es-ES', 'name' => 'Spanish (Spain)', 'native_name' => 'Español (España)', 'language_code' => 'es', 'country_code' => 'ES'],
            ['code' => 'ur-PK', 'name' => 'Urdu (Pakistan)', 'native_name' => 'اردو (پاکستان)', 'language_code' => 'ur', 'country_code' => 'PK'],
        ];
        foreach ($locales as $loc) {
            // Only generate ID if not already present
            if (!isset($loc['id'])) {
                $loc['id'] = \Illuminate\Support\Str::uuid();
            }
            DB::table('public.locales')->updateOrInsert(['code' => $loc['code']], $loc + [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Pivots — languages per country
        $countryLanguages = [
            ['country_code' => 'US', 'language_code' => 'en', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'AE', 'language_code' => 'ar', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'AE', 'language_code' => 'en', 'official' => false, 'primary' => false, 'order' => 1],
            ['country_code' => 'GB', 'language_code' => 'en', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'PK', 'language_code' => 'en', 'official' => true, 'primary' => false, 'order' => 0],
            ['country_code' => 'PK', 'language_code' => 'ur', 'official' => true, 'primary' => true, 'order' => 1],
            ['country_code' => 'IN', 'language_code' => 'hi', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'IN', 'language_code' => 'en', 'official' => true, 'primary' => false, 'order' => 1],
            ['country_code' => 'CN', 'language_code' => 'zh', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'JP', 'language_code' => 'ja', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'DE', 'language_code' => 'de', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'FR', 'language_code' => 'fr', 'official' => true, 'primary' => true, 'order' => 0],
            ['country_code' => 'CA', 'language_code' => 'en', 'official' => true, 'primary' => false, 'order' => 0],
            ['country_code' => 'CA', 'language_code' => 'fr', 'official' => true, 'primary' => false, 'order' => 1],
        ];
        foreach ($countryLanguages as $cl) {
            DB::table('public.country_language')->updateOrInsert(
                ['country_code' => $cl['country_code'], 'language_code' => $cl['language_code']],
                $cl + ['created_at' => now(), 'updated_at' => now()]
            );
        }

        // Pivots — currencies per country
        $countryCurrencies = [
            ['country_code' => 'US', 'currency_code' => 'USD', 'official' => true],
            ['country_code' => 'AE', 'currency_code' => 'AED', 'official' => true],
            ['country_code' => 'GB', 'currency_code' => 'GBP', 'official' => true],
            ['country_code' => 'PK', 'currency_code' => 'PKR', 'official' => true],
            ['country_code' => 'IN', 'currency_code' => 'INR', 'official' => true],
            ['country_code' => 'CN', 'currency_code' => 'CNY', 'official' => true],
            ['country_code' => 'JP', 'currency_code' => 'JPY', 'official' => true],
            ['country_code' => 'DE', 'currency_code' => 'EUR', 'official' => true],
            ['country_code' => 'FR', 'currency_code' => 'EUR', 'official' => true],
            ['country_code' => 'CA', 'currency_code' => 'CAD', 'official' => true],
        ];
        foreach ($countryCurrencies as $cc) {
            DB::table('public.country_currency')->updateOrInsert(
                ['country_code' => $cc['country_code'], 'currency_code' => $cc['currency_code']],
                $cc + ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
