<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncRolePermissions extends Command
{
    protected $signature = 'rbac:sync-role-permissions {--company=}';

    protected $description = 'Sync role-permission mappings for companies';

    public function handle(): int
    {
        $matrix = config('role-permissions', []);

        if (empty($matrix)) {
            $this->error('No role-permissions matrix found in config/role-permissions.php');
            return self::FAILURE;
        }

        $companyId = $this->option('company');
        
        if ($companyId) {
            $company = Company::find($companyId);
            if (!$company) {
                $this->error("Company not found: {$companyId}");
                return self::FAILURE;
            }
            $companies = collect([$company]);
        } else {
            $companies = Company::where('is_active', true)->get();
        }

        if ($companies->isEmpty()) {
            $this->warn('No active companies found. Roles will be created when companies are added.');
            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $this->info("Company: {$company->name} (id: {$company->id})");
            
            app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

            foreach ($matrix as $roleName => $permissionNames) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);

                $permissions = Permission::whereIn('name', $permissionNames)
                    ->where('guard_name', 'web')
                    ->get();

                $role->syncPermissions($permissions);

                $this->line("  ✓ {$roleName}: {$permissions->count()} permissions");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Role permissions synced for {$companies->count()} company(ies).");

        return self::SUCCESS;
    }
}
