<?php

namespace App\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class CompanyRbacBootstrapper
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly PermissionRegistrar $registrar,
        private readonly RolePermissionSynchronizer $rolePermissionSynchronizer,
    ) {
    }

    public function bootstrap(Company $company, ?User $owner = null): void
    {
        $this->ensureGlobalPermissions();

        $matrix = config('role-permissions', []);
        if (! empty($matrix)) {
            $this->rolePermissionSynchronizer->syncForCompany($company, $matrix);
        }

        if ($owner) {
            $this->companyContext->withContext($company, function () use ($owner) {
                $this->companyContext->assignRole($owner, 'owner');
            });
        }

        $this->registrar->forgetCachedPermissions();
    }

    public function ensureGlobalPermissions(): array
    {
        $originalTeam = $this->registrar->getPermissionsTeamId();
        $this->registrar->setPermissionsTeamId(null);
        $created = 0;
        $existing = 0;

        try {
            foreach ($this->permissionNames() as $permissionName) {
                $exists = DB::table('permissions')
                    ->where('name', $permissionName)
                    ->where('guard_name', 'web')
                    ->exists();

                if ($exists) {
                    $existing++;
                    continue;
                }

                DB::table('permissions')->insert([
                    'id' => (string) Str::orderedUuid(),
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            }
        } finally {
            $this->registrar->setPermissionsTeamId($originalTeam);
        }

        return [
            'created' => $created,
            'existing' => $existing,
        ];
    }

    private function permissionNames(): array
    {
        $names = Permissions::all();

        foreach (config('permissions', []) as $models) {
            foreach ($models as $permissionList) {
                foreach ((array) $permissionList as $permissionName) {
                    $names[] = $permissionName;
                }
            }
        }

        return array_values(array_unique(array_filter($names)));
    }
}
