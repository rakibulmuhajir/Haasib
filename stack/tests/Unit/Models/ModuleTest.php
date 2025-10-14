<?php

use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

covers(Module::class);

it('can create a module', function () {
    $module = Module::factory()->create([
        'is_active' => true,
    ]);

    expect($module->name)->not->toBeEmpty();
    expect($module->version)->not->toBeEmpty();
    expect($module->is_active)->toBeTrue();
});

it('has many companies through company modules', function () {
    $module = Module::factory()->create();
    $companies = Company::factory()->count(3)->create();
    $user = User::factory()->create();

    foreach ($companies as $company) {
        CompanyModule::create([
            'company_id' => $company->id,
            'module_id' => $module->id,
            'is_active' => true,
            'enabled_at' => now(),
            'enabled_by_user_id' => $user->id,
        ]);
    }

    expect($module->companies)->toHaveCount(3);
    expect($module->companies->first()->pivot->is_active)->toBeTrue();
});

it('can be enabled and disabled', function () {
    $module = Module::factory()->create(['is_active' => false]);

    expect($module->is_active)->toBeFalse();

    $module->is_active = true;
    $module->save();

    expect($module->refresh()->is_active)->toBeTrue();
});

it('validates required fields', function () {
    expect(fn () => Module::factory()->create(['name' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
    
    expect(fn () => Module::factory()->create(['version' => null]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('validates unique key', function () {
    $duplicateKey = 'duplicate-key-'.Str::uuid();

    Module::factory()->create(['key' => $duplicateKey]);

    expect(fn () => Module::factory()->create(['key' => $duplicateKey]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('can scope to enabled modules', function () {
    $activeModules = Module::factory()->count(3)->create(['is_active' => true]);
    Module::factory()->count(2)->create(['is_active' => false]);

    $enabledModules = Module::active()
        ->whereIn('id', $activeModules->pluck('id'))
        ->get();

    expect($enabledModules)->toHaveCount(3);
    $enabledModules->each(fn ($module) => expect($module->is_active)->toBeTrue());
});

it('can get enabled companies count', function () {
    $module = Module::factory()->create();
    $companies = Company::factory()->count(5)->create();
    $user = User::factory()->create();

    // Enable module for 3 companies
    foreach ($companies->take(3) as $company) {
        CompanyModule::create([
            'company_id' => $company->id,
            'module_id' => $module->id,
            'is_active' => true,
            'enabled_at' => now(),
            'enabled_by_user_id' => $user->id,
        ]);
    }

    // Disable for 2 companies
    foreach ($companies->skip(3) as $company) {
        CompanyModule::create([
            'company_id' => $company->id,
            'module_id' => $module->id,
            'is_active' => false,
        ]);
    }

    expect($module->getEnabledCompaniesCount())->toBe(3);
});

it('has proper casting for dates', function () {
    $module = Module::factory()->create();

    expect($module->created_at)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($module->updated_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can check if module is enabled for company', function () {
    $module = Module::factory()->create();
    $enabledCompany = Company::factory()->create();
    $disabledCompany = Company::factory()->create();
    $user = User::factory()->create();

    CompanyModule::create([
        'company_id' => $enabledCompany->id,
        'module_id' => $module->id,
        'is_active' => true,
        'enabled_at' => now(),
        'enabled_by_user_id' => $user->id,
    ]);

    CompanyModule::create([
        'company_id' => $disabledCompany->id,
        'module_id' => $module->id,
        'is_active' => false,
    ]);

    expect($module->isEnabledForCompany($enabledCompany->id))->toBeTrue();
    expect($module->isEnabledForCompany($disabledCompany->id))->toBeFalse();
    expect($module->isEnabledForCompany('00000000-0000-0000-0000-000000000000'))->toBeFalse();
});
