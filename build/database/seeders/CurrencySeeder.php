<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $table = 'public.currencies';
        try {
            DB::table($table)->limit(1)->get();
        } catch (\Throwable) {
            $table = 'currencies';
        }

        foreach ($this->currencies() as $currency) {

            DB::table($table)->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    private function currencies(): array
    {
        $metadata = [
            'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED'],
            'AFN' => ['name' => 'Afghan Afghani', 'symbol' => 'AFN'],
            'ARS' => ['name' => 'Argentine Peso', 'symbol' => 'ARS'],
            'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'AUD'],
            'BDT' => ['name' => 'Bangladeshi Taka', 'symbol' => 'BDT'],
            'BHD' => ['name' => 'Bahraini Dinar', 'symbol' => 'BHD', 'decimal_places' => 3],
            'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'BRL'],
            'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'CAD'],
            'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'CHF'],
            'CLP' => ['name' => 'Chilean Peso', 'symbol' => 'CLP', 'decimal_places' => 0],
            'CNY' => ['name' => 'Chinese Yuan', 'symbol' => 'CNY'],
            'COP' => ['name' => 'Colombian Peso', 'symbol' => 'COP'],
            'CZK' => ['name' => 'Czech Koruna', 'symbol' => 'CZK'],
            'DKK' => ['name' => 'Danish Krone', 'symbol' => 'DKK'],
            'EGP' => ['name' => 'Egyptian Pound', 'symbol' => 'EGP'],
            'EUR' => ['name' => 'Euro', 'symbol' => 'EUR'],
            'GBP' => ['name' => 'British Pound', 'symbol' => 'GBP'],
            'GHS' => ['name' => 'Ghanaian Cedi', 'symbol' => 'GHS'],
            'HKD' => ['name' => 'Hong Kong Dollar', 'symbol' => 'HKD'],
            'HUF' => ['name' => 'Hungarian Forint', 'symbol' => 'HUF'],
            'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'IDR'],
            'ILS' => ['name' => 'Israeli New Shekel', 'symbol' => 'ILS'],
            'INR' => ['name' => 'Indian Rupee', 'symbol' => 'INR'],
            'IQD' => ['name' => 'Iraqi Dinar', 'symbol' => 'IQD', 'decimal_places' => 3],
            'IRR' => ['name' => 'Iranian Rial', 'symbol' => 'IRR'],
            'JOD' => ['name' => 'Jordanian Dinar', 'symbol' => 'JOD', 'decimal_places' => 3],
            'JPY' => ['name' => 'Japanese Yen', 'symbol' => 'JPY', 'decimal_places' => 0],
            'KES' => ['name' => 'Kenyan Shilling', 'symbol' => 'KES'],
            'KRW' => ['name' => 'South Korean Won', 'symbol' => 'KRW', 'decimal_places' => 0],
            'KWD' => ['name' => 'Kuwaiti Dinar', 'symbol' => 'KWD', 'decimal_places' => 3],
            'LBP' => ['name' => 'Lebanese Pound', 'symbol' => 'LBP'],
            'LKR' => ['name' => 'Sri Lankan Rupee', 'symbol' => 'LKR'],
            'MAD' => ['name' => 'Moroccan Dirham', 'symbol' => 'MAD'],
            'MXN' => ['name' => 'Mexican Peso', 'symbol' => 'MXN'],
            'MYR' => ['name' => 'Malaysian Ringgit', 'symbol' => 'MYR'],
            'NGN' => ['name' => 'Nigerian Naira', 'symbol' => 'NGN'],
            'NOK' => ['name' => 'Norwegian Krone', 'symbol' => 'NOK'],
            'NPR' => ['name' => 'Nepalese Rupee', 'symbol' => 'NPR'],
            'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZD'],
            'OMR' => ['name' => 'Omani Rial', 'symbol' => 'OMR', 'decimal_places' => 3],
            'PEN' => ['name' => 'Peruvian Sol', 'symbol' => 'PEN'],
            'PHP' => ['name' => 'Philippine Peso', 'symbol' => 'PHP'],
            'PKR' => ['name' => 'Pakistani Rupee', 'symbol' => 'PKR'],
            'PLN' => ['name' => 'Polish Zloty', 'symbol' => 'PLN'],
            'QAR' => ['name' => 'Qatari Riyal', 'symbol' => 'QAR'],
            'RON' => ['name' => 'Romanian Leu', 'symbol' => 'RON'],
            'RUB' => ['name' => 'Russian Ruble', 'symbol' => 'RUB'],
            'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR'],
            'SEK' => ['name' => 'Swedish Krona', 'symbol' => 'SEK'],
            'SGD' => ['name' => 'Singapore Dollar', 'symbol' => 'SGD'],
            'THB' => ['name' => 'Thai Baht', 'symbol' => 'THB'],
            'TND' => ['name' => 'Tunisian Dinar', 'symbol' => 'TND', 'decimal_places' => 3],
            'TRY' => ['name' => 'Turkish Lira', 'symbol' => 'TRY'],
            'TWD' => ['name' => 'New Taiwan Dollar', 'symbol' => 'TWD'],
            'UAH' => ['name' => 'Ukrainian Hryvnia', 'symbol' => 'UAH'],
            'USD' => ['name' => 'US Dollar', 'symbol' => 'USD'],
            'VND' => ['name' => 'Vietnamese Dong', 'symbol' => 'VND', 'decimal_places' => 0],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'ZAR'],
        ];

        $codes = collect(config('countries', []))
            ->pluck('currency')
            ->filter()
            ->merge(array_keys($metadata))
            ->unique()
            ->sort()
            ->values();

        return $codes
            ->map(fn (string $code): array => array_merge(
                ['code' => $code, 'name' => "{$code} Currency", 'symbol' => $code, 'decimal_places' => 2],
                $metadata[$code] ?? []
            ))
            ->all();
    }
}
