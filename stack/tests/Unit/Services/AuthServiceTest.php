<?php

use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

covers(AuthService::class);

it('logs in users with valid credentials', function () {
    $password = 'secret123';
    $user = User::factory()->create([
        'email' => 'owner@example.com',
        'password' => Hash::make($password),
        'is_active' => true,
    ]);

    $service = app(AuthService::class);
    $result = $service->login('owner@example.com', $password);

    expect($result['success'])->toBeTrue();
    expect($result['user']->is($user))->toBeTrue();
});

it('rejects invalid credentials or inactive users', function () {
    $user = User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('good-pass'),
        'is_active' => false,
    ]);

    $service = app(AuthService::class);

    $wrongPassword = $service->login('inactive@example.com', 'bad-pass');
    expect($wrongPassword['success'])->toBeFalse();

    $inactive = $service->login('inactive@example.com', 'good-pass');
    expect($inactive['success'])->toBeFalse();
    expect($inactive['message'])->toBe('Account is deactivated');
});

it('checks company access based on membership and super admin role', function () {
    $company = Company::factory()->create();
    $member = User::factory()->create();
    $super = User::factory()->create(['system_role' => 'superadmin']);

    $member->companies()->attach($company->id, [
        'role' => 'member',
        'is_active' => true,
    ]);

    $service = app(AuthService::class);

    expect($service->canAccessCompany($member, $company))->toBeTrue();
    expect($service->canAccessCompany($super, $company))->toBeTrue();

    $otherCompany = Company::factory()->create();
    expect($service->canAccessCompany($member, $otherCompany))->toBeFalse();
});

it('switches to accessible companies and updates session context', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();

    $user->companies()->attach($company->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);

    $request = request();
    if (method_exists($request, 'setLaravelSession')) {
        $request->setLaravelSession(session());
    }

    $service = app(AuthService::class);
    $switched = $service->switchCompany($user, $company);

    expect($switched)->toBeTrue();
    expect(session('current_company_id'))->toBe($company->id);
});

it('aggregates role and module permissions for a company', function () {
    $user = User::factory()->create();
    $company = Company::factory()->create();
    $module = Module::factory()->create([
        'permissions' => ['view_reports', 'manage_entries'],
    ]);

    $user->companies()->attach($company->id, [
        'role' => 'accountant',
        'is_active' => true,
    ]);

    $company->modules()->attach($module->id, [
        'is_active' => true,
        'enabled_at' => now(),
    ]);

    $service = app(AuthService::class);
    $permissions = $service->getUserPermissions($user, $company);

    expect($permissions)->toContain('view_reports');
    expect($permissions)->toContain('manage_entries');
    expect($permissions)->toContain('create_transactions');
});
