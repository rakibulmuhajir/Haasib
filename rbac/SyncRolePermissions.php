<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
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
            // Get or create role for this company
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            // Get permission models (permissions are global, no team_id)
            $permissions = Permission::whereIn('name', $permissionNames)
                ->where('guard_name', 'web')
                ->get();

            // Sync permissions to role
            $role->syncPermissions($permissions);

            $this->line("  {$roleName}: {$permissions->count()} permissions");
        }
    }
}
