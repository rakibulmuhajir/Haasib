<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Company;
use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompanyList extends Command
{
    protected $signature = 'acc:company:list {--active-only : Show only active companies} {--detailed : Show detailed information}';

    protected $description = 'List all companies (Accounting module)';

    public function handle(ContextService $contextService): int
    {
        $activeOnly = $this->option('active-only');
        $detailed = $this->option('detailed');

        // Check for user context
        $currentUser = $contextService->getCurrentUser();
        if (! $currentUser) {
            $this->error('No active user context. Please set user context first.');

            return 1;
        }

        // Build query
        $query = Company::query();

        // Apply active filter
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $companies = $query->orderBy('name')->get();

        if ($companies->isEmpty()) {
            $this->info('No companies found');

            return 0;
        }

        $this->info("\nCompanies:");
        $this->info(str_repeat('-', 100));

        foreach ($companies as $company) {
            $status = $company->is_active ? '[ACTIVE]' : '[INACTIVE]';
            $this->info("• {$company->name} ({$company->slug}) - {$company->base_currency} {$status}");

            if ($detailed) {
                $this->info("  ID: {$company->id}");
                $this->info("  Created: {$company->created_at->format('Y-m-d H:i:s')}");

                // Show user count
                $userCount = DB::table('auth.company_user')
                    ->where('company_id', $company->id)
                    ->count();
                $this->info("  Users: {$userCount}");

                // Show enabled modules
                $modules = DB::table('auth.company_modules as cm')
                    ->join('auth.modules as m', 'cm.module_id', '=', 'm.id')
                    ->where('cm.company_id', $company->id)
                    ->where('cm.is_active', true)
                    ->select('m.name', 'm.version')
                    ->get();

                if ($modules->isNotEmpty()) {
                    $this->info('  Enabled Modules:');
                    foreach ($modules as $module) {
                        $this->info("    - {$module->name} v{$module->version}");
                    }
                }
            }

            $this->info('');
        }

        $this->info("Total: {$companies->count()} companies");

        return 0;
    }
}
