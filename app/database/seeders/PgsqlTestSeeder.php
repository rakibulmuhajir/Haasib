<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PgsqlTestSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure a base USD currency exists for tests
        if (! Currency::where('code', 'USD')->exists()) {
            Currency::create([
                'id' => (string) Str::uuid(),
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'minor_unit' => 2,
            ]);
        }
    }
}
