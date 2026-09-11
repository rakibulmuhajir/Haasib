<?php

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a new user can create a company with optional currency inputs exactly as sent by the form', function (array $optional, bool $hasSecondary) {
    $user = User::factory()->withoutTwoFactor()->create();
    $name = 'Currency QA '.str()->random(12);

    $response = $this->actingAs($user)->post('/companies', array_merge([
        'name' => $name,
        'owner_user_id' => '',
        'industry_code' => 'travel',
        'country' => 'PK',
        'base_currency' => 'PKR',
        'timezone' => 'Asia/Karachi',
    ], $optional));

    $response->assertSessionHasNoErrors()->assertSessionMissing('error')->assertRedirect();
    $company = Company::where('name', $name)->sole();
    $response->assertRedirect(route('umrah.dashboard', ['company' => $company->slug]));
    expect(DB::table('auth.company_user')->where('company_id', $company->id)->where('user_id', $user->id)->value('role'))->toBe('owner');
    $secondary = CompanyCurrency::where('company_id', $company->id)->where('currency_code', 'SAR')->first();
    if ($hasSecondary) {
        expect($secondary)->not->toBeNull()
            ->and((float) $secondary->exchange_rate)->toBe(75.0);
    } else {
        expect($secondary)->toBeNull();
    }
    $this->get(route('umrah.dashboard', ['company' => $company->slug]))->assertOk();
})->with([
    'empty strings from default form' => [['secondary_currency' => '', 'secondary_exchange_rate' => ''], false],
    'explicit null values' => [['secondary_currency' => null, 'secondary_exchange_rate' => null], false],
    'omitted optional inputs' => [[], false],
    'selected currency and positive rate' => [['secondary_currency' => 'SAR', 'secondary_exchange_rate' => '75'], true],
]);

test('invalid secondary currency rates still reject company creation without saving a company', function (array $optional, string $field) {
    $user = User::factory()->withoutTwoFactor()->create();
    $name = 'Rejected Currency QA '.str()->random(12);
    $this->actingAs($user)->from('/companies/create')->post('/companies', array_merge([
        'name' => $name,
        'industry_code' => 'travel',
        'country' => 'PK',
        'base_currency' => 'PKR',
    ], $optional))->assertRedirect('/companies/create')->assertSessionHasErrors($field);

    expect(Company::where('name', $name)->exists())->toBeFalse();
})->with([
    'selected currency missing rate' => [['secondary_currency' => 'SAR'], 'secondary_exchange_rate'],
    'selected currency null rate' => [['secondary_currency' => 'SAR', 'secondary_exchange_rate' => null], 'secondary_exchange_rate'],
    'zero rate' => [['secondary_currency' => 'SAR', 'secondary_exchange_rate' => '0'], 'secondary_exchange_rate'],
    'negative rate' => [['secondary_currency' => 'SAR', 'secondary_exchange_rate' => '-1'], 'secondary_exchange_rate'],
    'text rate' => [['secondary_currency' => 'SAR', 'secondary_exchange_rate' => 'bad'], 'secondary_exchange_rate'],
    'too many decimals' => [['secondary_currency' => 'SAR', 'secondary_exchange_rate' => '1.123456789'], 'secondary_exchange_rate'],
    'rate without currency' => [['secondary_currency' => '', 'secondary_exchange_rate' => '75'], 'secondary_exchange_rate'],
    'duplicate base currency' => [['secondary_currency' => 'PKR', 'secondary_exchange_rate' => '1'], 'secondary_currency'],
]);
