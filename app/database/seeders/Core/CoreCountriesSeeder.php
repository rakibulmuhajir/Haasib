<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreCountriesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Map of currency codes to currency UUID id from currencies
        $currencyIds = DB::table('currencies')->pluck('id', 'code');

        $rows = [
            ['code' => 'US', 'alpha3' => 'USA', 'name' => 'United States',         'currency_code' => 'USD', 'phone_prefix' => '+1'],
            ['code' => 'AE', 'alpha3' => 'ARE', 'name' => 'United Arab Emirates',  'currency_code' => 'AED', 'phone_prefix' => '+971'],
            ['code' => 'PK', 'alpha3' => 'PAK', 'name' => 'Pakistan',              'currency_code' => 'PKR', 'phone_prefix' => '+92'],
            ['code' => 'GB', 'alpha3' => 'GBR', 'name' => 'United Kingdom',        'currency_code' => 'GBP', 'phone_prefix' => '+44'],
            ['code' => 'JP', 'alpha3' => 'JPN', 'name' => 'Japan',                 'currency_code' => 'JPY', 'phone_prefix' => '+81'],
        ];

        foreach ($rows as $r) {
            $currencyId = $currencyIds[$r['currency_code']] ?? null;
            if (! $currencyId) {
                // Skip if currency not present yet
                continue;
            }

            DB::table('countries')->updateOrInsert(
                ['code' => $r['code']],
                [
                    'alpha3' => $r['alpha3'],
                    'name' => $r['name'],
                    // app-level countries table does not have FK to currencies; keep neutral
                    // Additional reference linking can be added via a pivot if needed
                    'phone_prefix' => $r['phone_prefix'],
                    'updated_at' => $now,
                    'created_at' => DB::raw("COALESCE(created_at, '{$now}')"),
                ]
            );
        }
    }
}
