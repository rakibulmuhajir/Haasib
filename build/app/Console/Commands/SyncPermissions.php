<?php

namespace App\Console\Commands;

use App\Services\CompanyRbacBootstrapper;
use Illuminate\Console\Command;
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

        $result = app(CompanyRbacBootstrapper::class)->ensureGlobalPermissions();

        // Clear permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Permissions synced: {$result['created']} created, {$result['existing']} already existed.");

        return self::SUCCESS;
    }
}
