<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\RolePermissionSynchronizer;
use Illuminate\Console\Command;

class SyncRolePermissions extends Command
{
    protected $signature = 'app:sync-role-permissions {--company= : Sync only for specific company ID}';

    protected $description = 'Sync role-permission matrix for companies';

    protected $aliases = [
        'rbac:sync-role-permissions',
    ];

    public function handle(): int
    {
        $matrix = config('role-permissions', []);

        if (empty($matrix)) {
            $this->error('No role-permission matrix found in config/role-permissions.php');
            return self::FAILURE;
        }

        $companyId = $this->option('company') ?: null;
        /** @var RolePermissionSynchronizer $syncer */
        $syncer = app(RolePermissionSynchronizer::class);

        $count = $syncer->syncAll(
            matrix: $matrix,
            companyId: $companyId,
            logger: fn(string $line) => $this->line("  {$line}")
        );

        $this->info("Role permissions synced for {$count} company(ies).");

        return self::SUCCESS;
    }
}
