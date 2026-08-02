<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\DB;

function createRoleAccessCompany(): array
{
    $owner = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Role Access '.str()->random(8),
        'slug' => 'role-access-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'industry_code' => 'umrah',
        'settings' => ['modules' => ['umrah' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$owner->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");

    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    addRoleAccessMember($company, $owner, 'owner');

    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");

    return [$company, $owner];
}

function addRoleAccessMember(Company $company, User $user, string $role): void
{
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => $role,
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, $role),
    );
}

test('owner can remove a manager', function () {
    [$company, $owner] = createRoleAccessCompany();
    $manager = User::factory()->withoutTwoFactor()->create();
    addRoleAccessMember($company, $manager, 'manager');

    $this->actingAs($owner)
        ->delete(route('users.remove', ['company' => $company->slug, 'user' => $manager->id]))
        ->assertRedirect()
        ->assertSessionHas('success', 'User removed from company successfully.');

    expect(DB::table('auth.company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $manager->id)
        ->exists())->toBeFalse();
});

test('owner and manager can invite allowed roles and owner can change roles', function () {
    [$company, $owner] = createRoleAccessCompany();
    $manager = User::factory()->withoutTwoFactor()->create();
    $accountant = User::factory()->withoutTwoFactor()->create();
    addRoleAccessMember($company, $manager, 'manager');
    addRoleAccessMember($company, $accountant, 'accountant');

    $this->actingAs($owner)
        ->post(route('users.invite', ['company' => $company->slug]), [
            'email' => 'manager-invite-'.str()->random(8).'@example.test',
            'role' => 'manager',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Invitation sent successfully.');

    $this->actingAs($manager)
        ->post(route('users.invite', ['company' => $company->slug]), [
            'email' => 'operations-invite-'.str()->random(8).'@example.test',
            'role' => 'operations',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'Invitation sent successfully.');

    $this->actingAs($owner)
        ->put(route('users.update-role', ['company' => $company->slug, 'user' => $accountant->id]), [
            'role' => 'manager',
        ])
        ->assertRedirect();

    expect(DB::table('auth.company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $accountant->id)
        ->value('role'))->toBe('manager');
});

test('manager cannot change or remove an owner', function () {
    [$company, $owner] = createRoleAccessCompany();
    $manager = User::factory()->withoutTwoFactor()->create();
    addRoleAccessMember($company, $manager, 'manager');

    $this->actingAs($manager)
        ->put(route('users.update-role', ['company' => $company->slug, 'user' => $owner->id]), [
            'role' => 'operations',
        ])
        ->assertForbidden();

    $this->actingAs($manager)
        ->delete(route('users.remove', ['company' => $company->slug, 'user' => $owner->id]))
        ->assertForbidden();

    expect(DB::table('auth.company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $owner->id)
        ->value('role'))->toBe('owner');
});

test('manager can change and remove non-owner users', function () {
    [$company] = createRoleAccessCompany();
    $manager = User::factory()->withoutTwoFactor()->create();
    $accountant = User::factory()->withoutTwoFactor()->create();
    addRoleAccessMember($company, $manager, 'manager');
    addRoleAccessMember($company, $accountant, 'accountant');

    $this->actingAs($manager)
        ->put(route('users.update-role', ['company' => $company->slug, 'user' => $accountant->id]), [
            'role' => 'operations',
        ])
        ->assertRedirect()
        ->assertSessionHas('success', 'User role updated successfully.');

    expect(DB::table('auth.company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $accountant->id)
        ->value('role'))->toBe('operations');

    $this->actingAs($manager)
        ->delete(route('users.remove', ['company' => $company->slug, 'user' => $accountant->id]))
        ->assertRedirect();

    expect(DB::table('auth.company_user')
        ->where('company_id', $company->id)
        ->where('user_id', $accountant->id)
        ->exists())->toBeFalse();
});

test('company role permissions enforce the requested financial boundary', function () {
    [$company] = createRoleAccessCompany();
    $manager = User::factory()->withoutTwoFactor()->create();
    $accountant = User::factory()->withoutTwoFactor()->create();
    $operations = User::factory()->withoutTwoFactor()->create();
    addRoleAccessMember($company, $manager, 'manager');
    addRoleAccessMember($company, $accountant, 'accountant');
    addRoleAccessMember($company, $operations, 'operations');

    app(CompanyContextService::class)->withContext($company, function () use ($manager, $accountant, $operations) {
        expect($manager->hasCompanyPermission('company.manage-users'))->toBeTrue()
            ->and($manager->hasCompanyPermission('company.delete-user'))->toBeTrue()
            ->and($accountant->hasCompanyPermission('account.view'))->toBeTrue()
            ->and($accountant->hasCompanyPermission('journal.view'))->toBeTrue()
            ->and($accountant->hasCompanyPermission('umrah.voucher-accounting.view'))->toBeTrue()
            ->and($operations->hasCompanyPermission('umrah.group.create'))->toBeTrue()
            ->and($operations->hasCompanyPermission('umrah.voucher.create'))->toBeTrue()
            ->and($operations->hasCompanyPermission('account.view'))->toBeFalse()
            ->and($operations->hasCompanyPermission('journal.view'))->toBeFalse()
            ->and($operations->hasCompanyPermission('umrah.group-accounting.view'))->toBeFalse()
            ->and($operations->hasCompanyPermission('umrah.voucher-accounting.view'))->toBeFalse()
            ->and($operations->hasCompanyPermission('umrah.payment.view'))->toBeFalse()
            ->and($operations->hasCompanyPermission('umrah.expense.view'))->toBeFalse()
            ->and($operations->hasCompanyPermission('umrah.report.view'))->toBeFalse();
    });
});

test('operations clerk can open operational entry but direct financial URLs are forbidden', function () {
    [$company] = createRoleAccessCompany();
    $operations = User::factory()->withoutTwoFactor()->create();
    addRoleAccessMember($company, $operations, 'operations');
    $missingRecord = (string) str()->uuid();

    $this->actingAs($operations)
        ->get(route('umrah.groups.create', ['company' => $company->slug]))
        ->assertOk();

    $this->actingAs($operations)
        ->get(route('umrah.vouchers.create', ['company' => $company->slug]))
        ->assertOk();

    $this->actingAs($operations)
        ->get(route('umrah.groups.accounting.show', [
            'company' => $company->slug,
            'group' => $missingRecord,
        ]))
        ->assertForbidden();

    $this->actingAs($operations)
        ->get(route('umrah.vouchers.accounting.show', [
            'company' => $company->slug,
            'voucher' => $missingRecord,
        ]))
        ->assertForbidden();

    $this->actingAs($operations)
        ->get(route('umrah.payments.index', ['company' => $company->slug]))
        ->assertForbidden();

    $this->actingAs($operations)
        ->get(route('umrah.expenses.index', ['company' => $company->slug]))
        ->assertForbidden();
});

test('payroll is enabled by default but may be explicitly disabled', function () {
    $company = new Company(['settings' => []]);
    expect($company->isModuleEnabled('payroll'))->toBeTrue();

    $company->settings = ['modules' => ['payroll' => false]];
    expect($company->isModuleEnabled('payroll'))->toBeFalse();
});
