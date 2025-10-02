<?php

use App\Models\Company;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Run the RBAC seeder
    $this->seed(\Database\Seeders\RbacSeeder::class);
});

describe('Company Role Management', function () {
    it('owner can view and manage roles', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        actingAs($owner)
            ->get(route('companies.roles.index', $company))
            ->assertSuccessful();
    });

    it('viewer cannot view role management', function () {
        $viewer = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $viewer->assignRole('viewer');
        setPermissionsTeamId(null);

        actingAs($viewer)
            ->get(route('companies.roles.index', $company))
            ->assertStatus(403);
    });

    it('can update user role', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->create();
        $company = Company::factory()->create();

        // Setup users and roles
        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        setPermissionsTeamId($company->id);
        $employee->assignRole('viewer');
        setPermissionsTeamId(null);

        // Update role
        actingAs($owner)
            ->put(route('companies.roles.update', [$company, $employee]), [
                'role' => 'employee',
            ])
            ->assertSuccessful()
            ->assertJson(['role' => 'employee']);
    });

    it('cannot remove last owner from company', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        // Try to remove the only owner
        actingAs($owner)
            ->delete(route('companies.roles.remove', [$company, $owner]))
            ->assertStatus(403)
            ->assertJson(['message' => 'Cannot remove the last owner from the company']);
    });

    it('admin cannot assign owner role to themselves', function () {
        $admin = User::factory()->create();
        $company = Company::factory()->create();

        setPermissionsTeamId($company->id);
        $admin->assignRole('admin');
        setPermissionsTeamId(null);

        // Try to assign owner role to self
        actingAs($admin)
            ->put(route('companies.roles.update', [$company, $admin]), [
                'role' => 'owner',
            ])
            ->assertStatus(403);
    });
});
