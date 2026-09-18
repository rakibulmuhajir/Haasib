<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Services\FuelNavigationAccess;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

function fuelNavCompanyWithRole(string $role): array
{
    $user = User::factory()->create();
    $company = Company::create([
        'name' => 'Nav Station', 'slug' => 'nav-station-'.str()->random(8),
        'owner_id' => $user->id, 'base_currency' => 'PKR', 'industry_code' => 'fuel_station',
        'is_active' => true,
    ]);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => $role,
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, $role));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    return compact('user', 'company');
}

test('a fuel-station owner sees the payments, expenses and banking-transactions nav keys', function () {
    ['user' => $user, 'company' => $company] = fuelNavCompanyWithRole('owner');

    $allowed = app(CompanyContextService::class)->withContext($company, fn () => app(FuelNavigationAccess::class)->forUser($company, $user)['allowed']);

    expect($allowed)->toContain('payments')
        ->toContain('expenses')
        ->toContain('banking');
});

test('a user without payment, expense or banking permissions does not see those nav keys', function () {
    ['user' => $user, 'company' => $company] = fuelNavCompanyWithRole('operations');

    $allowed = app(CompanyContextService::class)->withContext($company, fn () => app(FuelNavigationAccess::class)->forUser($company, $user)['allowed']);

    expect($allowed)->not->toContain('payments')
        ->not->toContain('expenses')
        ->not->toContain('banking');
});

test('a fuel-station owner sees the bank feed, bank reconciliation and credit notes nav keys', function () {
    ['user' => $user, 'company' => $company] = fuelNavCompanyWithRole('owner');

    $allowed = app(CompanyContextService::class)->withContext($company, fn () => app(FuelNavigationAccess::class)->forUser($company, $user)['allowed']);

    expect($allowed)->toContain('bankFeed')
        ->toContain('bankReconciliation')
        ->toContain('creditNotes');
});

test('a user without the mapped permissions does not see bank feed, bank reconciliation or credit notes', function () {
    ['user' => $user, 'company' => $company] = fuelNavCompanyWithRole('operations');

    $allowed = app(CompanyContextService::class)->withContext($company, fn () => app(FuelNavigationAccess::class)->forUser($company, $user)['allowed']);

    expect($allowed)->not->toContain('bankFeed')
        ->not->toContain('bankReconciliation')
        ->not->toContain('creditNotes');
});
