<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(User::class);

it('creates a user with a system role', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'system_role' => 'company_owner',
    ]);

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->system_role)->toBe('company_owner');
    expect($user->is_active)->toBeTrue();
});

it('resolves ownership and admin status for companies', function () {
    $user = User::factory()->create();
    $ownerCompany = Company::factory()->create();
    $memberCompany = Company::factory()->create();

    $user->companies()->attach($ownerCompany->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);

    $user->companies()->attach($memberCompany->id, [
        'role' => 'member',
        'is_active' => true,
    ]);

    expect($user->isOwnerOfCompany($ownerCompany))->toBeTrue();
    expect($user->isAdminOfCompany($ownerCompany))->toBeTrue();
    expect($user->isOwnerOfCompany($memberCompany))->toBeFalse();
    expect($user->isAdminOfCompany($memberCompany))->toBeFalse();
    expect($user->ownsCompany($ownerCompany->id))->toBeTrue();
});

it('returns active companies and role map', function () {
    $user = User::factory()->create();
    $activeCompany = Company::factory()->create();
    $inactiveCompany = Company::factory()->create();

    $user->companies()->attach($activeCompany->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    $user->companies()->attach($inactiveCompany->id, [
        'role' => 'member',
        'is_active' => false,
    ]);

    $activeCompanies = $user->getActiveCompanies();
    expect($activeCompanies)->toHaveCount(1);
    expect($activeCompanies->first()->id)->toBe($activeCompany->id);

    $map = $user->getCompaniesWithRoles();
    expect($map[$activeCompany->id]['role'])->toBe('admin');
    expect($map[$inactiveCompany->id]['is_active'])->toBeFalse();
});

it('derives current company from session context', function () {
    $user = User::factory()->create();
    $firstCompany = Company::factory()->create();
    $secondCompany = Company::factory()->create();

    $user->companies()->attach($firstCompany->id, [
        'role' => 'owner',
        'is_active' => true,
    ]);

    $user->companies()->attach($secondCompany->id, [
        'role' => 'admin',
        'is_active' => true,
    ]);

    $context = app(\App\Services\ContextService::class);
    $context->setCurrentCompany($user, $secondCompany);

    expect($user->currentCompany()?->id)->toBe($secondCompany->id);

    $context->setCurrentCompany($user, $firstCompany);

    expect($user->currentCompany()?->id)->toBe($firstCompany->id);
});

it('activates and deactivates the user', function () {
    $user = User::factory()->create();

    $user->deactivate();
    expect($user->refresh()->is_active)->toBeFalse();

    $user->activate();
    expect($user->refresh()->is_active)->toBeTrue();
});

it('scopes users by active status and system role', function () {
    $activeUsers = User::factory()->count(2)->create(['is_active' => true, 'system_role' => 'accountant']);
    $inactiveUser = User::factory()->create(['is_active' => false, 'system_role' => 'accountant']);
    $owner = User::factory()->create(['is_active' => true, 'system_role' => 'company_owner']);

    expect(User::active()->whereIn('id', $activeUsers->pluck('id')->push($owner->id))->count())->toBe(3);
    expect(User::byRole('accountant')->whereIn('id', $activeUsers->pluck('id')->push($inactiveUser->id))->count())->toBe(3);
});
