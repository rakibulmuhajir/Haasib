<?php

namespace App\Console\Commands;

use App\Constants\Permissions;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    protected $signature = 'rbac:sync-permissions';

    protected $description = 'Sync all permissions from Permissions class (global, not per-company)';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $allPermissions = Permissions::getAll();
        $created = 0;
        $existing = 0;

        $this->info('Syncing permissions...');

        foreach ($allPermissions as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $created++;
                $this->line("  ✓ Created: {$permissionName}");
            } else {
                $existing++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Permissions synced: {$created} created, {$existing} already existed.");

        return self::SUCCESS;
    }
}
