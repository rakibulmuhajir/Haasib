<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Company;
use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompanySwitch extends Command
{
    protected $signature = 'acc:company:switch {slug : Company slug to switch to} {--show-modules : Show enabled modules}';

    protected $description = 'Switch active company context (Accounting module)';

    public function handle(ContextService $contextService): int
    {
        $slug = $this->argument('slug');
        $showModules = $this->option('show-modules');

        // Check for user context
        $currentUser = $contextService->getCurrentUser();
        if (! $currentUser) {
            $this->error('No active user context. Please set user context first.');

            return 1;
        }

        // Find the company
        $company = Company::where('slug', $slug)->first();

        if (! $company) {
            $this->error("Company '{$slug}' not found.");

            return 1;
        }

        // Check if company is active
        if (! $company->is_active) {
            $this->error("Company '{$slug}' is inactive and cannot be used.");

            return 1;
        }

        // Check if user has access to this company
        $hasAccess = DB::table('auth.company_user')
            ->where('user_id', $currentUser->id)
            ->where('company_id', $company->id)
            ->exists();

        if (! $hasAccess) {
            $this->error("You do not have access to company '{$slug}'.");

            return 1;
        }

        // Switch company context
        try {
            $contextService->setCurrentCompany($currentUser, $company);

            $this->info("Switched to company: {$company->name}");
            $this->info("  ID: {$company->id}");
            $this->info("  Slug: {$company->slug}");
            $this->info("  Currency: {$company->base_currency}");

            if ($showModules) {
                // Show enabled modules
                $modules = DB::table('auth.company_modules as cm')
                    ->join('auth.modules as m', 'cm.module_id', '=', 'm.id')
                    ->where('cm.company_id', $company->id)
                    ->where('cm.is_active', true)
                    ->select('m.name', 'm.key', 'm.version', 'cm.enabled_at')
                    ->orderBy('m.name')
                    ->get();

                if ($modules->isNotEmpty()) {
                    $this->info("\nEnabled Modules:");
                    foreach ($modules as $module) {
                        $this->info("  • {$module->name} ({$module->key}) v{$module->version}");
                        $this->info('    Enabled: '.$module->enabled_at->format('Y-m-d H:i:s'));
                    }
                } else {
                    $this->info("\nNo modules enabled");
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to switch company: {$e->getMessage()}");

            return 1;
        }
    }
}
