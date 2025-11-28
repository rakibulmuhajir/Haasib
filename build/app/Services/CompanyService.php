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
    public function createForUser(User $user, array $data): Company
    {
        return DB::transaction(function () use ($user, $data) {
            $company = Company::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? null,
                'created_by_user_id' => $user->id,
                'is_active' => true,
            ]);

            $user->companies()->attach($company->id, [
                'role' => 'owner',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            $this->createCompanyRoles($company);

            $user->assignRole('owner');

            return $company;
        });
    }

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

            if (isset($matrix[$roleName])) {
                $permissions = Permission::whereIn('name', $matrix[$roleName])
                    ->where('guard_name', 'web')
                    ->get();

                $role->syncPermissions($permissions);
            }
        }
    }

    public function addMember(Company $company, User $user, string $roleName): void
    {
        DB::transaction(function () use ($company, $user, $roleName) {
            if (!$this->userBelongsToCompany($user, $company)) {
                $user->companies()->attach($company->id, [
                    'role' => $roleName,
                    'is_active' => true,
                    'joined_at' => now(),
                ]);
            }

            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            $user->syncRoles([$roleName]);
        });
    }

    public function changeRole(Company $company, User $user, string $newRole): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $user->syncRoles([$newRole]);

        $user->companies()->updateExistingPivot($company->id, [
            'role' => $newRole,
        ]);
    }

    public function removeMember(Company $company, User $user): void
    {
        DB::transaction(function () use ($company, $user) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            $user->syncRoles([]);

            $user->companies()->updateExistingPivot($company->id, [
                'is_active' => false,
                'left_at' => now(),
            ]);
        });
    }

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

    private function userBelongsToCompany(User $user, Company $company): bool
    {
        return $user->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
