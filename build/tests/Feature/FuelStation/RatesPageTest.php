<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function fuelPricesPageFixture(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Fuel Prices Station',
        'slug' => 'fuel-prices-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);
    $company->enableModule('fuel_station');

    return compact('company', 'user');
}

test('the fuel prices page renders with its rate, fuel and tank props', function () {
    $f = fuelPricesPageFixture();

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/rates")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Rates/Index')
            ->has('rates')
            ->has('items')
            ->has('stockLevels')
            ->has('tanks')
            ->has('nozzles'));
});
