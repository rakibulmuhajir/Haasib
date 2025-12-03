<?php

namespace App\Services;

use App\Constants\Permissions;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSynchronizer
{
    public function __construct(
        private readonly PermissionRegistrar $registrar
    ) {
    }

    /**
    * Sync role-permission matrix for one or all companies.
    *
    * @return int number of companies synced
    */
    public function syncAll(array $matrix, ?string $companyId = null, ?callable $logger = null): int
    {
        if (empty($matrix)) {
            return 0;
        }

        $query = Company::query();
        if ($companyId) {
            $query->where('id', $companyId);
        }

        $companies = $query->cursor();
        $count = 0;

        foreach ($companies as $company) {
            $this->syncForCompany($company, $matrix, $logger);
            $count++;
        }

        $this->registrar->forgetCachedPermissions();

        return $count;
    }

    /**
    * Sync role-permission matrix for a single company.
    *
    * @return array<string,int> counts per role
    */
    public function syncForCompany(Company $company, array $matrix, ?callable $logger = null): array
    {
        $results = [];
        $originalTeam = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId($company->id);

        foreach ($matrix as $roleName => $permissionNames) {
            // Work with roles outside team scoping
            $this->registrar->setPermissionsTeamId(null);

            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('company_id', $company->id)
                ->where('guard_name', 'web')
                ->value('id');

            if (!$roleId) {
                $roleId = (string) Str::orderedUuid();
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $logger && $logger("Created role {$roleName} ({$roleId}) for {$company->name}");
            }

            $expandedPermissions = $this->expandPermissions($permissionNames);

            $permissionIds = DB::table('permissions')
                ->whereIn('name', $expandedPermissions)
                ->where('guard_name', 'web')
                ->pluck('id');

            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            $results[$roleName] = $permissionIds->count();
            $logger && $logger("{$roleName}: {$permissionIds->count()} permissions");

            // Restore team context for next iteration
            $this->registrar->setPermissionsTeamId($company->id);
        }

        $this->registrar->setPermissionsTeamId($originalTeam);

        return $results;
    }

    /**
    * Expand wildcard and de-duplicate permission names.
    */
    private function expandPermissions(array $permissionNames): array
    {
        if (in_array('*', $permissionNames, true)) {
            return Permissions::all();
        }

        return array_values(array_unique($permissionNames));
    }
}
