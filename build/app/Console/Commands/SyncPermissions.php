<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'app:sync-permissions';

    protected $description = 'Sync all permissions from config (global, not per-company)';

    protected $aliases = [
        'rbac:sync-permissions',
    ];

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
                    // Check if permission exists
                    $permission = Permission::where('name', $permissionName)
                        ->where('guard_name', 'web')
                        ->first();

                    if (!$permission) {
                        // Create new permission with explicit UUID
                        $permission = new Permission();
                        $permission->id = \Illuminate\Support\Str::orderedUuid();
                        $permission->name = $permissionName;
                        $permission->guard_name = 'web';
                        $permission->save();

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
