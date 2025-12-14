<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'app:sync-permissions';

    protected $description = 'Sync all permissions from config (global, not per-company)';

    public function handle(): int
    {
        // Clear team context - permissions are GLOBAL
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $modules = config('permissions', []);
        $created = 0;
        $existing = 0;

        foreach ($modules as $moduleName => $models) {
            foreach ($models as $modelName => $permissions) {
                foreach ($permissions as $permissionName) {
                    $permission = Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);

                    if ($permission->wasRecentlyCreated) {
                        $created++;
                        $this->line("  Created: {$permissionName}");
                    } else {
                        $existing++;
                    }
                }
            }
        }

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Permissions synced: {$created} created, {$existing} already existed.");

        return self::SUCCESS;
    }
}
