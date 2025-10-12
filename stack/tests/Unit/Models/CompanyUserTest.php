<?php

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(CompanyUser::class);

it('can create a company user relationship', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();

    $companyUser = CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    expect($companyUser->company_id)->toBe($company->id);
    expect($companyUser->user_id)->toBe($user->id);
    expect($companyUser->role)->toBe('owner');
    expect($companyUser->is_active)->toBeTrue();
});

it('belongs to a company', function () {
    $company = Company::factory()->create(['name' => 'Test Company']);
    $user = User::factory()->create();

    $companyUser = CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'member',
        'is_active' => true,
    ]);

    expect($companyUser->company->name)->toBe('Test Company');
});

it('belongs to a user', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['name' => 'Test User']);

    $companyUser = CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'member',
        'is_active' => true,
    ]);

    expect($companyUser->user->name)->toBe('Test User');
});

it('can be activated and deactivated', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();

    $companyUser = CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'member',
        'is_active' => false,
    ]);

    expect($companyUser->is_active)->toBeFalse();

    $companyUser->activate();

    $reloaded = CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    expect($reloaded->is_active)->toBeTrue();

    $companyUser->deactivate();

    $reloaded = CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    expect($reloaded->is_active)->toBeFalse();
});

it('can change user role', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();

    $companyUser = CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'member',
        'is_active' => true,
    ]);

    expect($companyUser->role)->toBe('member');

    $companyUser->changeRole('owner');

    $reloaded = CompanyUser::where('company_id', $company->id)
        ->where('user_id', $user->id)
        ->first();

    expect($reloaded->role)->toBe('owner');
});

it('validates unique company-user combination', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create();

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    expect(fn () => CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'member',
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('can scope to active relationships', function () {
    $company = Company::factory()->create();
    $users = User::factory()->count(5)->create();

    // Activate 3 users
    foreach ($users->take(3) as $user) {
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_active' => true,
        ]);
    }

    // Deactivate 2 users
    foreach ($users->skip(3) as $user) {
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'is_active' => false,
        ]);
    }

    $activeRelationships = CompanyUser::active()
        ->where('company_id', $company->id)
        ->get();

    expect($activeRelationships)->toHaveCount(3);
    $activeRelationships->each(fn ($cu) => expect($cu->is_active)->toBeTrue());
});

it('can scope by role', function () {
    $company = Company::factory()->create();
    $users = User::factory()->count(5)->create();

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $users[0]->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $users[1]->id,
        'role' => 'owner',
        'is_active' => true,
    ]);

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $users[2]->id,
        'role' => 'accountant',
        'is_active' => true,
    ]);

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $users[3]->id,
        'role' => 'member',
        'is_active' => true,
    ]);

    CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => $users[4]->id,
        'role' => 'member',
        'is_active' => true,
    ]);

    $owners = CompanyUser::byRole('owner')->where('company_id', $company->id)->get();
    $accountants = CompanyUser::byRole('accountant')->where('company_id', $company->id)->get();
    $members = CompanyUser::byRole('member')->where('company_id', $company->id)->get();

    expect($owners)->toHaveCount(2);
    expect($accountants)->toHaveCount(1);
    expect($members)->toHaveCount(2);
});

it('validates role values', function () {
    $company = Company::factory()->create();
    $users = User::factory()->count(3)->create();

    foreach (['owner', 'manager', 'employee'] as $index => $role) {
        $companyUser = CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $users[$index]->id,
            'role' => $role,
            'is_active' => true,
        ]);
        expect($companyUser->role)->toBe($role);
    }

    // Invalid role should fail validation
    expect(fn () => CompanyUser::create([
        'company_id' => $company->id,
        'user_id' => User::factory()->create()->id,
        'role' => 'invalid_role',
        'is_active' => true,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});
