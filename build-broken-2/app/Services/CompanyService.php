<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CompanyService
{
    /**
     * Create a new company and assign the creator as owner.
     */
    public function createForUser(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data) {
            // Create the company
            $company = Company::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'created_by_user_id' => $user->id,
                'is_active' => true,
            ]);

            // Attach user to company with owner role
            $user->companies()->attach($company->id, [
                'role' => 'company_owner',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            // Set team context for role operations
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            // Create company-scoped roles
            $this->createCompanyRoles($company);

            // Assign owner role to creator
            $user->assignRole('owner');

            return $company;
        });
    }

    /**
     * Create the standard roles for a company.
     */
    public function createCompanyRoles(Company $company): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $roles = ['owner', 'admin', 'accountant', 'viewer'];
        $matrix = config('role-permissions', []);

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            // Assign permissions from matrix
            if (isset($matrix[$roleName])) {
                $permissions = Permission::whereIn('name', $matrix[$roleName])
                    ->where('guard_name', 'web')
                    ->get();

                $role->syncPermissions($permissions);
            }
        }
    }

    /**
     * Add a user to a company with a role.
     */
    public function addMember(Company $company, User $user, string $roleName): void
    {
        DB::transaction(function () use ($company, $user, $roleName) {
            // Add to pivot if not exists
            if (!$this->userBelongsToCompany($user, $company)) {
                $user->companies()->attach($company->id, [
                    'role' => $roleName,
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
            }

            // Set team context
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            // Remove existing roles for this company and assign new one
            $user->syncRoles([$roleName]);
        });
    }

    /**
     * Change a user's role in a company.
     */
    public function changeRole(Company $company, User $user, string $newRole): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $user->syncRoles([$newRole]);

        // Update pivot table role
        $user->companies()->updateExistingPivot($company->id, [
            'role' => $newRole,
        ]);
    }

    /**
     * Remove a user from a company.
     */
    public function removeMember(Company $company, User $user): void
    {
        DB::transaction(function () use ($company, $user) {
            // Set team context
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            // Remove all roles for this company
            $user->syncRoles([]);

            // Update pivot status
            $user->companies()->updateExistingPivot($company->id, [
                'is_active' => false,
                'left_at' => now(),
            ]);
        });
    }

    /**
     * Get all members of a company with their roles.
     */
    public function getMembers(Company $company): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        return $company->users()
            ->wherePivot('is_active', true)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first(),
                    'joined_at' => $user->pivot->joined_at,
                ];
            })
            ->toArray();
    }

    /**
     * Check if user belongs to company.
     */
    private function userBelongsToCompany(User $user, Company $company): bool
    {
        return $user->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
