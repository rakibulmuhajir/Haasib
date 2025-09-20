<?php

namespace Database\Seeders\Core;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CoreCurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$',  'decimal_places' => 2, 'is_active' => true],
            ['code' => 'EUR', 'name' => 'Euro',       'symbol' => '€',  'decimal_places' => 2, 'is_active' => true],
            ['code' => 'GBP', 'name' => 'Pound Sterling', 'symbol' => '£', 'decimal_places' => 2, 'is_active' => true],
            ['code' => 'JPY', 'name' => 'Japanese Yen',   'symbol' => '¥', 'decimal_places' => 0, 'is_active' => true],
            ['code' => 'AED', 'name' => 'UAE Dirham',     'symbol' => 'د.إ', 'decimal_places' => 2, 'is_active' => true],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee','symbol' => '₨', 'decimal_places' => 2, 'is_active' => true],
        ];

        foreach ($rows as $r) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $r['code']],
                [
                    'name' => $r['name'],
                    'symbol' => $r['symbol'],
                    'minor_unit' => $r['decimal_places'],
                    'is_active' => $r['is_active'],
                    'updated_at' => $now,
                    'created_at' => DB::raw("COALESCE(created_at, '{$now}')"),
                ]
            );
        }
    }
}
