<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\Module;
use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ModuleEnable extends Command
{
    protected $signature = 'acc:module:enable {moduleKey : Module key to enable} {--company= : Company slug (uses current company context if not specified)}';

    protected $description = 'Enable a module for a company (Accounting module)';

    public function handle(ContextService $contextService): int
    {
        $moduleKey = $this->argument('moduleKey');
        $companySlug = $this->option('company');

        // Check for user context
        $currentUser = $contextService->getCurrentUser();
        if (! $currentUser) {
            $this->error('No active user context. Please set user context first.');

            return 1;
        }

        // Determine target company
        if ($companySlug) {
            $company = DB::table('auth.companies')
                ->where('slug', $companySlug)
                ->first();

            if (! $company) {
                $this->error("Company '{$companySlug}' not found.");

                return 1;
            }
        } else {
            $company = $contextService->getCurrentCompany();
            if (! $company) {
                $this->error('No active company context. Please specify --company or set company context first.');

                return 1;
            }
        }

        // Check if user has permission to enable modules for this company
        $userRole = DB::table('auth.company_user')
            ->where('user_id', $currentUser->id)
            ->where('company_id', $company->id)
            ->value('role');

        if (! in_array($userRole, ['owner', 'admin'])) {
            $this->error('You do not have permission to enable modules for this company.');

            return 1;
        }

        // Find the module
        $module = Module::where('key', $moduleKey)
            ->where('is_active', true)
            ->first();

        if (! $module) {
            $this->error("Module '{$moduleKey}' not found or is inactive.");

            return 1;
        }

        // Check if module is already enabled
        $existing = DB::table('auth.company_modules')
            ->where('company_id', $company->id)
            ->where('module_id', $module->id)
            ->first();

        if ($existing && $existing->is_active) {
            $this->warn("Module '{$module->name}' is already enabled for {$company->name}.");

            return 0;
        }

        // Enable the module
        try {
            DB::beginTransaction();

            if ($existing) {
                // Reactivate existing module
                DB::table('auth.company_modules')
                    ->where('company_id', $company->id)
                    ->where('module_id', $module->id)
                    ->update([
                        'is_active' => true,
                        'enabled_by_user_id' => $currentUser->id,
                        'enabled_at' => now(),
                        'updated_at' => now(),
                    ]);
            } else {
                // Enable new module
                DB::table('auth.company_modules')->insert([
                    'company_id' => $company->id,
                    'module_id' => $module->id,
                    'is_active' => true,
                    'enabled_by_user_id' => $currentUser->id,
                    'enabled_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->info("✅ Module '{$module->name}' has been enabled for {$company->name}!");
            $this->info("  Module Key: {$module->key}");
            $this->info("  Version: {$module->version}");
            $this->info("  Enabled By: {$currentUser->name}");
            $this->info('  Enabled At: '.now()->format('Y-m-d H:i:s'));

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to enable module: {$e->getMessage()}");

            return 1;
        }
    }
}
