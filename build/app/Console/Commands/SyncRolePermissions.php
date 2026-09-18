<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\CompanyContextService;
use App\Services\RolePermissionSynchronizer;
use Illuminate\Console\Command;

class SyncRolePermissions extends Command
{
    protected $signature = 'app:sync-role-permissions {--company= : Sync only for specific company ID}';

    protected $description = 'Sync role-permission matrix for companies';

    protected $aliases = [
        'rbac:sync-role-permissions',
    ];

    public function handle(CompanyContextService $context): int
    {
        $matrix = config('role-permissions', []);

        if (empty($matrix)) {
            $this->error('No role-permission matrix found in config/role-permissions.php');
            return self::FAILURE;
        }

        $companyId = $this->option('company') ?: null;
        /** @var RolePermissionSynchronizer $syncer */
        $syncer = app(RolePermissionSynchronizer::class);

        // syncAll enumerates auth.companies, which row level security scopes to
        // the current tenant; a matrix sync has no single tenant.
        $count = $context->crossCompany(fn () => $syncer->syncAll(
            matrix: $matrix,
            companyId: $companyId,
            logger: fn (string $line) => $this->line("  {$line}")
        ));

        $this->info("Role permissions synced for {$count} company(ies).");

        return self::SUCCESS;
    }
}
