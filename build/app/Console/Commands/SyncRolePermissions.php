<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolePermissions extends Command
{
    protected $signature = 'app:sync-role-permissions {--company= : Sync only for specific company ID}';

    protected $description = 'Sync role-permission matrix for companies';

    public function handle(): int
    {
        $matrix = config('role-permissions', []);

        if (empty($matrix)) {
            $this->error('No role-permission matrix found in config/role-permissions.php');
            return self::FAILURE;
        }

        $companyId = $this->option('company');

        $query = Company::query();
        if ($companyId) {
            $query->where('id', $companyId);
        }

        $companies = $query->cursor();
        $count = 0;

        foreach ($companies as $company) {
            $this->syncForCompany($company, $matrix);
            $count++;
        }

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Role permissions synced for {$count} company(ies).");

        return self::SUCCESS;
    }

    private function syncForCompany(Company $company, array $matrix): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        $this->line("Syncing roles for: {$company->name}");

        foreach ($matrix as $roleName => $permissionNames) {
            // Temporarily clear team context to work with roles
            app(PermissionRegistrar::class)->setPermissionsTeamId(null);

            // Check if role exists for this company using raw query
            $roleId = DB::table('roles')
                ->where('name', $roleName)
                ->where('company_id', $company->id)
                ->where('guard_name', 'web')
                ->value('id');

            if (!$roleId) {
                // Create new role with explicit UUID and company_id
                $roleId = (string) \Illuminate\Support\Str::orderedUuid();
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->line("  Created role - ID: {$roleId}, Company: {$company->id}");
            }

            // Get permission IDs using raw query
            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->pluck('id');

            // Manually sync permissions by deleting and reinserting
            DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }

            $this->line("  {$roleName}: {$permissionIds->count()} permissions");

            // Restore team context
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        }
    }
}
