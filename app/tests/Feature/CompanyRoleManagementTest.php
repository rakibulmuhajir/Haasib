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

        // Associate user with company
        $company->users()->attach($owner->id, ['role' => 'owner']);

        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        actingAs($owner)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('companies.roles.index', $company))
            ->assertSuccessful();
    });

    it('viewer cannot view role management', function () {
        $viewer = User::factory()->create();
        $company = Company::factory()->create();

        // Associate user with company
        $company->users()->attach($viewer->id, ['role' => 'viewer']);

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

        // Associate users with company
        $company->users()->attach($owner->id, ['role' => 'owner']);
        $company->users()->attach($employee->id, ['role' => 'viewer']);

        // Setup users and roles
        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        setPermissionsTeamId($company->id);
        $employee->assignRole('viewer');
        setPermissionsTeamId(null);

        // Update role
        actingAs($owner)
            ->withSession(['current_company_id' => $company->id])
            ->put(route('companies.roles.update', [$company, $employee]), [
                'role' => 'employee',
            ])
            ->assertSuccessful()
            ->assertJson(['role' => 'employee']);
    });

    it('cannot remove last owner from company', function () {
        $owner = User::factory()->create();
        $company = Company::factory()->create();

        // Associate user with company
        $company->users()->attach($owner->id, ['role' => 'owner']);

        setPermissionsTeamId($company->id);
        $owner->assignRole('owner');
        setPermissionsTeamId(null);

        // Try to remove the only owner
        actingAs($owner)
            ->withSession(['current_company_id' => $company->id])
            ->delete(route('companies.roles.remove', [$company, $owner]))
            ->assertStatus(403)
            ->assertJson(['message' => 'You cannot remove yourself from the company']);
    });

    it('admin cannot assign owner role to themselves', function () {
        $admin = User::factory()->create();
        $company = Company::factory()->create();

        // Associate user with company
        $company->users()->attach($admin->id, ['role' => 'admin']);

        setPermissionsTeamId($company->id);
        $admin->assignRole('admin');
        setPermissionsTeamId(null);

        // Try to assign owner role to self
        actingAs($admin)
            ->withSession(['current_company_id' => $company->id])
            ->put(route('companies.roles.update', [$company, $admin]), [
                'role' => 'owner',
            ])
            ->assertStatus(403);
    });
});
