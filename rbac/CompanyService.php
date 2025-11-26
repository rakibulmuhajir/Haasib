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
                'settings' => $data['settings'] ?? null,
            ]);

            // Attach user to company
            $user->companies()->attach($company->id, [
                'status' => 'active',
                'is_default' => !$user->hasCompanies(),
            ]);

            // Set team context for role operations
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            // Create company-scoped roles
            $this->createCompanyRoles($company);

            // Assign owner role to creator
            $user->assignRole('owner');

            // Audit
            AuditService::record('company.created', $company, null, $company->toArray());
            AuditService::record('role.assigned', $user, null, [
                'role' => 'owner',
                'company_id' => $company->id,
            ]);

            return $company;
        });
    }

    /**
     * Create the standard roles for a company.
     */
    public function createCompanyRoles(Company $company): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $roles = ['owner', 'accountant', 'viewer'];
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
            if (!$user->belongsToCompany($company)) {
                $user->companies()->attach($company->id, [
                    'status' => 'active',
                ]);
            }

            // Set team context
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            // Remove existing roles for this company and assign new one
            $user->syncRoles([$roleName]);

            // Audit
            AuditService::record('member.added', $user, null, [
                'company_id' => $company->id,
                'role' => $roleName,
            ]);
        });
    }

    /**
     * Change a user's role in a company.
     */
    public function changeRole(Company $company, User $user, string $newRole): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $oldRoles = $user->getRoleNames()->toArray();

        $user->syncRoles([$newRole]);

        AuditService::record('role.changed', $user, [
            'roles' => $oldRoles,
        ], [
            'role' => $newRole,
            'company_id' => $company->id,
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
            $oldRoles = $user->getRoleNames()->toArray();
            $user->syncRoles([]);

            // Update pivot status
            $user->companies()->updateExistingPivot($company->id, [
                'status' => 'suspended',
            ]);

            AuditService::record('member.removed', $user, [
                'roles' => $oldRoles,
                'company_id' => $company->id,
            ]);
        });
    }

    /**
     * Get all members of a company with their roles.
     */
    public function getMembers(Company $company): array
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        return $company->activeUsers()
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->getRoleNames()->first(),
                    'joined_at' => $user->pivot->created_at,
                ];
            })
            ->toArray();
    }
}
