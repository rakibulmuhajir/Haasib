<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCompanyUserRoles extends Command
{
    protected $signature = 'app:sync-company-user-roles {--company= : Limit to a specific company ID}';

    protected $description = 'Backfill Spatie team-scoped roles from auth.company_user pivot records';

    protected $aliases = [
        'rbac:sync-company-user-roles',
    ];

    public function handle(CompanyContextService $context): int
    {
        $query = DB::table('auth.company_user')
            ->select('company_id', 'user_id', 'role')
            ->where('is_active', true);

        if ($companyId = $this->option('company')) {
            $query->where('company_id', $companyId);
        }

        // Both auth.company_user and auth.companies are tenant-scoped under row
        // level security. A backfill across every membership has no one tenant.
        [$rows, $companies] = $context->crossCompany(function () use ($query) {
            $rows = $query->get();

            return [$rows, Company::whereIn('id', $rows->pluck('company_id')->unique())->get()->keyBy('id')];
        });

        $synced = 0;

        foreach ($rows as $row) {
            $company = $companies->get($row->company_id);
            $user = User::find($row->user_id);

            if (! $company || ! $user) {
                $this->warn("Skipping missing company/user for row company={$row->company_id} user={$row->user_id}");
                continue;
            }

            $context->withContext($company, function () use ($context, $user, $row) {
                $context->assignRole($user, $row->role);
            });

            $synced++;
        }

        $this->info("Synced roles for {$synced} membership(s).");

        return self::SUCCESS;
    }
}
