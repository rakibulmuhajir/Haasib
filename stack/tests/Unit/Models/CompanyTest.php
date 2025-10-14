<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(Company::class);

it('can create a company', function () {
    $company = Company::factory()->create([
        'name' => 'Test Company',
        'base_currency' => 'USD',
    ]);

    expect($company->name)->toBe('Test Company');
    expect($company->base_currency)->toBe('USD');
    expect($company->is_active)->toBeTrue();
});

it('has many users through company users', function () {
    $company = Company::factory()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        $company->users()->attach($user->id, [
            'role' => 'member',
            'is_active' => true,
        ]);
    }

    expect($company->users)->toHaveCount(3);
    expect($company->users->first()->pivot->role)->toBe('member');
});

it('has many modules', function () {
    $company = Company::factory()->create();
    $modules = Module::factory()->count(2)->create();

    foreach ($modules as $module) {
        $company->modules()->attach($module->id, [
            'is_active' => true,
            'enabled_at' => now(),
            'enabled_by_user_id' => User::factory()->create()->id,
        ]);
    }

    expect($company->modules)->toHaveCount(2);
    expect($company->modules->first()->pivot->is_active)->toBeTrue();
});

it('can be deleted', function () {
    $company = Company::factory()->create();
    $companyId = $company->id;

    $company->delete();

    expect(Company::find($companyId))->toBeNull();
});

it('validates required fields', function () {
    expect(fn () => Company::factory()->create(['name' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
    
    expect(fn () => Company::factory()->create(['base_currency' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('can scope to active companies', function () {
    $active = Company::factory()->count(3)->create(['is_active' => true]);
    Company::factory()->count(2)->create(['is_active' => false]);

    $activeCompanies = Company::active()
        ->whereIn('id', $active->pluck('id'))
        ->get();

    expect($activeCompanies)->toHaveCount(3);
    $activeCompanies->each(fn ($company) => expect($company->is_active)->toBeTrue());
});

it('casts timestamps to carbon instances', function () {
    $company = Company::factory()->create();

    expect($company->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($company->updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can get active modules count', function () {
    $company = Company::factory()->create();
    $activeModules = Module::factory()->count(3)->create();
    $inactiveModules = Module::factory()->count(2)->create();

    foreach ($activeModules as $module) {
        $company->modules()->attach($module->id, ['is_active' => true]);
    }

    foreach ($inactiveModules as $module) {
        $company->modules()->attach($module->id, ['is_active' => false]);
    }

    expect($company->getActiveModulesCount())->toBe(3);
});
