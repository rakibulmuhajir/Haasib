<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨', 'decimal_places' => 2],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'decimal_places' => 0],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => '$', 'decimal_places' => 2],
        ];

        foreach ($currencies as $currency) {
            $table = 'public.currencies';
            try {
                DB::table($table)->limit(1)->get();
            } catch (\Throwable) {
                $table = 'currencies';
            }

            DB::table($table)->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }
}
