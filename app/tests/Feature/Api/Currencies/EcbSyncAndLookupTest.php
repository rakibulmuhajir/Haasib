<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Support\Str;

it('syncs ECB rates and persists USD/EUR pair', function () {
    // Arrange: ensure USD and EUR exist and active
    $usd = Currency::updateOrCreate(['code' => 'USD'], [
        'id' => (string) Str::uuid(),
        'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2, 'is_active' => true,
    ]);
    $eur = Currency::updateOrCreate(['code' => 'EUR'], [
        'id' => (string) Str::uuid(),
        'name' => 'Euro', 'symbol' => '€', 'minor_unit' => 2, 'is_active' => true,
    ]);

    // Act: call service to sync from ECB
    $svc = app(CurrencyService::class);
    $result = $svc->syncExchangeRatesFromAPI('ecb');

    // Assert: an exchange rate exists for USD->EUR as of today
    $today = now()->toDateString();
    $rate = ExchangeRate::where('base_currency_id', $usd->id)
        ->where('target_currency_id', $eur->id)
        ->where('effective_date', $today)
        ->first();

    expect($result)->toBeArray();
    expect($rate)->not()->toBeNull();
    expect((float) $rate->rate)->toBeGreaterThan(0.0);
});

it('returns exchange rate via API after upsert', function () {
    // Arrange: user + company + currencies
    $user = User::factory()->create();

    $usd = Currency::updateOrCreate(['code' => 'USD'], [
        'id' => (string) Str::uuid(),
        'name' => 'US Dollar', 'symbol' => '$', 'minor_unit' => 2, 'is_active' => true,
    ]);
    $eur = Currency::updateOrCreate(['code' => 'EUR'], [
        'id' => (string) Str::uuid(),
        'name' => 'Euro', 'symbol' => '€', 'minor_unit' => 2, 'is_active' => true,
    ]);

    $company = Company::create([
        'id' => (string) Str::uuid(),
        'name' => 'FX Test Co',
        'slug' => 'fx-test-co',
        'base_currency' => 'USD',
        'currency_id' => $usd->id,
        'language' => 'en',
        'locale' => 'en_US',
    ]);
    \DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Upsert a known rate for predictability
    $svc = app(CurrencyService::class);
    $svc->updateExchangeRate('USD', 'EUR', 0.9, now()->toDateString(), 'test');

    $this->actingAs($user);
    $resp = $this
        ->withHeaders(['X-Company-Id' => $company->id])
        ->getJson('/api/currencies/exchange-rate?from_currency=USD&to_currency=EUR');

    $resp->assertOk();
    $resp->assertJsonPath('data.from_currency', 'USD');
    $resp->assertJsonPath('data.to_currency', 'EUR');
    expect((float) $resp->json('data.rate'))->toBeGreaterThan(0.0);
});

