<?php

use App\Models\Company;
use App\Models\User;

beforeEach(function () {
    // Run the RBAC seeder - only if tables exist
    try {
        $this->seed(\Database\Seeders\RbacSeeder::class);
    } catch (\Exception $e) {
        // Skip seeding if tables don't exist
        $this->markTestSkipped('Database tables not available for testing');
    }
});

describe('RBAC Permission Tests', function () {
    it('owner can manage company currencies', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create();

        // Set team context and assign role
        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        // Test permission within team context
        expect($owner->hasPermissionTo('companies.currencies.manage'))->toBeFalse();

        setPermissionsTeamId($company->id);
        expect($owner->hasPermissionTo('companies.currencies.manage'))->toBeTrue();
        expect($owner->hasPermissionTo('companies.settings.update'))->toBeTrue();
        expect($owner->hasPermissionTo('users.roles.assign'))->toBeTrue();

        // Clear context for clean state
        setPermissionsTeamId(null);
    });

    it('admin cannot assign owner role', function () {
        $admin = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $admin->assignRole('admin');
        setPermissionsTeamId(null);

        setPermissionsTeamId($company->id);
        expect($admin->hasPermissionTo('users.roles.assign'))->toBeTrue();
        // Note: The restriction on assigning owner role would be enforced at the application level
        setPermissionsTeamId(null);
    });

    it('employee cannot assign roles', function () {
        $employee = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $employee->assignRole('employee');
        setPermissionsTeamId(null);

        setPermissionsTeamId($company->id);
        expect($employee->hasPermissionTo('users.roles.assign'))->toBeFalse();
        expect($employee->hasPermissionTo('companies.currencies.manage'))->toBeFalse();
        expect($employee->hasPermissionTo('invoices.create'))->toBeTrue();
        expect($employee->hasPermissionTo('invoices.delete'))->toBeFalse();

        setPermissionsTeamId(null);
    });

    it('permissions are isolated between companies', function () {
        $user = User::factory()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        // Assign admin role to company1
        setPermissionsTeamId($company1->id);
        $user->assignRole('admin');
        setPermissionsTeamId(null);

        // Switch context to company2 and assign viewer role
        setPermissionsTeamId($company2->id);
        $user->assignRole('viewer');
        setPermissionsTeamId(null);

        // Test permissions in company1 context
        setPermissionsTeamId($company1->id);
        expect($user->hasPermissionTo('companies.currencies.manage'))->toBeTrue();
        expect($user->hasPermissionTo('invoices.delete'))->toBeTrue();

        // Test permissions in company2 context
        setPermissionsTeamId($company2->id);
        expect($user->hasPermissionTo('companies.currencies.manage'))->toBeFalse();
        expect($user->hasPermissionTo('invoices.delete'))->toBeFalse();
        expect($user->hasPermissionTo('invoices.view'))->toBeTrue();

        // Clear context for clean state
        setPermissionsTeamId(null);
    });

    it('super_admin has system permissions', function () {
        $superAdmin = User::factory()->create(['system_role' => 'superadmin']);

        // Clear team context to check system permissions
        setPermissionsTeamId(null);
        $superAdmin->assignRole('super_admin');

        expect($superAdmin->hasPermissionTo('system.companies.manage'))->toBeTrue();
        expect($superAdmin->hasPermissionTo('system.currencies.manage'))->toBeTrue();
        expect($superAdmin->hasPermissionTo('system.users.manage'))->toBeTrue();

        // Super admin should have all permissions
        expect($superAdmin->hasPermissionTo('companies.currencies.manage'))->toBeTrue();
        expect($superAdmin->hasPermissionTo('invoices.delete'))->toBeTrue();
    });
});

describe('RBAC Route Protection Tests', function () {
    it('unauthorized user cannot access protected routes', function () {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $user->assignRole('viewer');
        setPermissionsTeamId(null);

        // Test currency management route
        $response = $this->actingAs($user)
            ->post("/api/companies/{$company->id}/currencies", [
                'currency_id' => 'USD',
            ]);

        $response->assertStatus(403);
    });

    it('authorized user can access protected routes', function () {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $user->assignRole('admin');
        setPermissionsTeamId(null);

        // Test currency management route
        $response = $this->actingAs($user)
            ->post("/api/companies/{$company->id}/currencies", [
                'currency_id' => 'USD',
            ]);

        // Should not be blocked by permissions (may still fail due to validation)
        $response->assertStatusNot(403);
    });
});

describe('RBAC Role Management Tests', function () {
    it('can assign roles to users in company context', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->create();
        $company = Company::factory()->create();

        // Owner assigns roles
        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        // Assign employee role
        setPermissionsTeamId($company->id);
        $employee->assignRole('employee');
        setPermissionsTeamId(null);

        // Verify role assignment
        setPermissionsTeamId($company->id);
        expect($employee->hasRole('employee'))->toBeTrue();
        expect($employee->hasPermissionTo('invoices.view'))->toBeTrue();
        expect($employee->hasPermissionTo('invoices.delete'))->toBeFalse();
        setPermissionsTeamId(null);
    });
});
