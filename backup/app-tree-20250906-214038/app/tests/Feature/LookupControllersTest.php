<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LookupControllersTest extends TestCase
{
    use RefreshDatabase;

    public function test_country_lookup_returns_matches(): void
    {
        $user = User::factory()->create();

        DB::table('countries')->insert([
            'id' => 1,
            'code' => 'US',
            'alpha3' => 'USA',
            'name' => 'United States',
            'emoji' => '🇺🇸',
            'region' => 'Americas',
            'subregion' => 'North',
            'calling_code' => '1',
        ]);

        $this->actingAs($user)
            ->getJson('/web/countries/suggest?q=United')
            ->assertOk()
            ->assertJsonFragment(['code' => 'US']);
    }

    public function test_language_lookup_filters_rtl(): void
    {
        $user = User::factory()->create();

        DB::table('languages')->insert([
            ['id' => 1, 'code' => 'en', 'name' => 'English', 'native_name' => 'English', 'rtl' => false],
            ['id' => 2, 'code' => 'ar', 'name' => 'Arabic', 'native_name' => 'العربية', 'rtl' => true],
        ]);

        $this->actingAs($user)
            ->getJson('/web/languages/suggest?rtl=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['code' => 'ar']);
    }

    public function test_currency_lookup_suggests(): void
    {
        $user = User::factory()->create();

        DB::table('currencies')->insert([
            'id' => 1,
            'code' => 'USD',
            'numeric_code' => '840',
            'name' => 'US Dollar',
            'symbol' => '$',
            'minor_unit' => 2,
            'cash_minor_unit' => null,
            'rounding' => 0,
            'fund' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/web/currencies/suggest?q=USD')
            ->assertOk()
            ->assertJsonFragment(['code' => 'USD']);
    }

    public function test_locale_lookup_filters(): void
    {
        $user = User::factory()->create();

        DB::table('languages')->insert(['id' => 1, 'code' => 'en', 'name' => 'English']);
        DB::table('countries')->insert(['id' => 1, 'code' => 'US', 'alpha3' => 'USA', 'name' => 'United States']);
        DB::table('countries')->insert(['id' => 2, 'code' => 'GB', 'alpha3' => 'GBR', 'name' => 'United Kingdom']);

        DB::table('locales')->insert([
            [
                'id' => 1,
                'tag' => 'en-US',
                'name' => 'English (United States)',
                'native_name' => 'English (United States)',
                'language_code' => 'en',
                'country_code' => 'US',
                'script' => null,
                'variant' => null,
            ],
            [
                'id' => 2,
                'tag' => 'en-GB',
                'name' => 'English (United Kingdom)',
                'native_name' => 'English (United Kingdom)',
                'language_code' => 'en',
                'country_code' => 'GB',
                'script' => null,
                'variant' => null,
            ],
        ]);

        $this->actingAs($user)
            ->getJson('/web/locales/suggest?language=en&country=US')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['tag' => 'en-US']);
    }
}
