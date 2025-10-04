<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;

trait InteractsWithRbac
{
    /**
     * Create a user with proper company association and role
     */
    protected function createUserWithRole(string $role, ?Company $company = null): User
    {
        $company = $company ?: Company::factory()->create();
        $user = User::factory()->create();

        // Associate user with company
        $company->users()->attach($user->id, ['role' => $role]);

        // Set team context and assign role
        setPermissionsTeamId($company->id);
        $user->assignRole($role);
        setPermissionsTeamId(null);

        return $user;
    }

    /**
     * Create multiple users with different roles for the same company
     */
    protected function createUsersForCompany(Company $company, array $roles): array
    {
        $users = [];

        foreach ($roles as $role) {
            $users[$role] = $this->createUserWithRole($role, $company);
        }

        return $users;
    }

    /**
     * Setup test with company and authenticated user
     */
    protected function actingAsUserForTest(string $role, ?Company $company = null): array
    {
        $company = $company ?: Company::factory()->create();
        $user = $this->createUserWithRole($role, $company);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id]);

        return [$user, $company];
    }

    /**
     * Check permission within company context
     */
    protected function userHasPermission(User $user, Company $company, string $permission): bool
    {
        setPermissionsTeamId($company->id);
        $hasPermission = $user->hasPermissionTo($permission);
        setPermissionsTeamId(null);

        return $hasPermission;
    }

    /**
     * Create a super admin user
     */
    protected function createSuperAdmin(): User
    {
        $user = User::factory()->create();

        // Super admin role has team_id = null (system-wide)
        $user->assignRole('super_admin');

        return $user;
    }

    /**
     * Create a company with complete role hierarchy
     */
    protected function createCompanyWithAllRoles(): array
    {
        $company = Company::factory()->create();
        $roles = ['owner', 'admin', 'manager', 'accountant', 'employee', 'viewer'];
        $users = $this->createUsersForCompany($company, $roles);

        return ['company' => $company, 'users' => $users];
    }

    /**
     * Seed RBAC permissions if not already seeded
     */
    protected function ensureRbacIsSeeded(): void
    {
        // Only seed once per test suite
        if (! \Spatie\Permission\Models\Permission::exists()) {
            $this->seed(\Database\Seeders\RbacSeeder::class);
        }
    }
}
