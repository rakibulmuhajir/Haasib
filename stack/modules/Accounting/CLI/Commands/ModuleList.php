<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Module;
use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ModuleList extends Command
{
    protected $signature = 'acc:module:list {--company : Filter by company context} {--available : Show available modules not enabled for current company}';

    protected $description = 'List modules (Accounting module)';

    public function handle(ContextService $contextService): int
    {
        $filterByCompany = $this->option('company');
        $showAvailable = $this->option('available');

        // Check for user context
        $currentUser = $contextService->getCurrentUser();
        if (! $currentUser) {
            $this->error('No active user context. Please set user context first.');

            return 1;
        }

        if ($filterByCompany || $showAvailable) {
            // Check for company context
            $currentCompany = $contextService->getCurrentCompany();
            if (! $currentCompany) {
                $this->error('No active company context. Please set company context first.');

                return 1;
            }
        }

        if ($showAvailable) {
            // Show available modules not enabled for current company
            $enabledModuleIds = DB::table('auth.company_modules')
                ->where('company_id', $currentCompany->id)
                ->where('is_active', true)
                ->pluck('module_id')
                ->toArray();

            $modules = Module::where('is_active', true)
                ->whereNotIn('id', $enabledModuleIds)
                ->orderBy('name')
                ->get();

            $this->info("\nAvailable Modules (not enabled for {$currentCompany->name}):");
        } elseif ($filterByCompany) {
            // Show enabled modules for current company
            $modules = DB::table('auth.company_modules as cm')
                ->join('auth.modules as m', 'cm.module_id', '=', 'm.id')
                ->where('cm.company_id', $currentCompany->id)
                ->where('cm.is_active', true)
                ->select('m.*', 'cm.enabled_at', 'cm.enabled_by_user_id')
                ->orderBy('m.name')
                ->get();

            $this->info("\nEnabled Modules for {$currentCompany->name}:");
        } else {
            // Show all modules
            $modules = Module::where('is_active', true)->orderBy('name')->get();
            $this->info("\nAll Available Modules:");
        }

        $this->info(str_repeat('-', 80));

        if ($modules->isEmpty()) {
            $this->info('No modules found');

            return 0;
        }

        foreach ($modules as $module) {
            $this->info("• {$module->name} ({$module->key})");
            $this->info("  Version: {$module->version}");
            $this->info("  Description: {$module->description}");

            if (isset($module->enabled_at)) {
                $this->info('  Enabled: '.$module->enabled_at->format('Y-m-d H:i:s'));
                $this->info("  Enabled By: User ID {$module->enabled_by_user_id}");
            }

            $this->info('');
        }

        $this->info("Total: {$modules->count()} modules");

        return 0;
    }
}
