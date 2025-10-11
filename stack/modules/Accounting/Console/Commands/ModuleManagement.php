<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\Module;
use Modules\Accounting\Models\User;
use Modules\Accounting\Services\ContextService;
use Modules\Accounting\Services\ModuleService;

class ModuleManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:module
                            {action : The action to perform (list, enable, disable, status, install, uninstall)}
                            {--module= : Module key or ID}
                            {--company= : Company ID or slug}
                            {--all-companies : Apply to all companies}
                            {--settings= : JSON string of module settings}
                            {--force : Force action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage accounting modules for companies';

    public function __construct(
        private ModuleService $moduleService,
        private ContextService $contextService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listModules(),
            'enable' => $this->enableModule(),
            'disable' => $this->disableModule(),
            'status' => $this->moduleStatus(),
            'install' => $this->installModule(),
            'uninstall' => $this->uninstallModule(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * List all available modules.
     */
    private function listModules(): int
    {
        $modules = $this->moduleService->getAllModules();

        if ($modules->isEmpty()) {
            $this->info('No modules found.');

            return 0;
        }

        $this->info('Available Modules:');
        $this->info(str_repeat('-', 80));

        $headers = ['ID', 'Name', 'Key', 'Category', 'Version', 'Active'];
        $rows = [];

        foreach ($modules as $module) {
            $rows[] = [
                $module->id,
                $module->name,
                $module->key,
                $module->category,
                $module->version,
                $module->is_active ? 'Yes' : 'No',
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    /**
     * Enable a module for a company.
     */
    private function enableModule(): int
    {
        $moduleKey = $this->option('module');
        if (! $moduleKey) {
            $this->error('Module key or ID is required. Use --module=<key|id>');

            return 1;
        }

        $module = $this->findModule($moduleKey);
        if (! $module) {
            $this->error("Module '{$moduleKey}' not found.");

            return 1;
        }

        $companies = $this->getTargetCompanies();
        if ($companies->isEmpty()) {
            $this->error('No companies found.');

            return 1;
        }

        $settings = [];
        if ($this->option('settings')) {
            $settings = json_decode($this->option('settings'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON in settings parameter.');

                return 1;
            }

            // Validate settings
            $validation = $this->moduleService->validateModuleSettings($module, $settings);
            if (! $validation['valid']) {
                $this->error('Invalid module settings:');
                foreach ($validation['errors'] as $error) {
                    $this->error("  - {$error}");
                }

                return 1;
            }
        }

        $superAdmin = User::where('system_role', 'superadmin')->first();
        if (! $superAdmin) {
            $this->error('No super admin user found for module operations.');

            return 1;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($companies as $company) {
            try {
                // Set company context for CLI operations
                $this->contextService->setCLICompanyContext($company);

                if ($company->hasModuleEnabled($module->key)) {
                    $this->warn("Module '{$module->name}' is already enabled for company '{$company->name}'.");

                    continue;
                }

                // Check dependencies
                if (! $this->moduleService->areDependenciesMet($company, $module)) {
                    $missingDeps = $module->checkDependencies();
                    $this->error("Cannot enable module for company '{$company->name}': Missing dependencies: ".implode(', ', $missingDeps));
                    $failureCount++;

                    continue;
                }

                $result = $this->moduleService->enableModule($company, $module->key, $superAdmin, $settings);

                if ($result) {
                    $this->info("✓ Enabled module '{$module->name}' for company '{$company->name}'");
                    $successCount++;
                } else {
                    $this->error("✗ Failed to enable module '{$module->name}' for company '{$company->name}'");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error enabling module for company '{$company->name}': {$e->getMessage()}");
                $failureCount++;
            }
        }

        // Clear CLI context
        $this->contextService->clearCLICompanyContext();

        $this->info("\nSummary:");
        $this->info("  Enabled: {$successCount} companies");
        $this->info("  Failed: {$failureCount} companies");

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * Disable a module for a company.
     */
    private function disableModule(): int
    {
        $moduleKey = $this->option('module');
        if (! $moduleKey) {
            $this->error('Module key or ID is required. Use --module=<key|id>');

            return 1;
        }

        $module = $this->findModule($moduleKey);
        if (! $module) {
            $this->error("Module '{$moduleKey}' not found.");

            return 1;
        }

        $companies = $this->getTargetCompanies();
        if ($companies->isEmpty()) {
            $this->error('No companies found.');

            return 1;
        }

        $superAdmin = User::where('system_role', 'superadmin')->first();
        if (! $superAdmin) {
            $this->error('No super admin user found for module operations.');

            return 1;
        }

        if (! $this->option('force')) {
            $this->warn('This will disable the module and all its data may become inaccessible.');
            if (! $this->confirm('Do you want to continue?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($companies as $company) {
            try {
                // Set company context for CLI operations
                $this->contextService->setCLICompanyContext($company);

                if (! $company->hasModuleEnabled($module->key)) {
                    $this->warn("Module '{$module->name}' is not enabled for company '{$company->name}'.");

                    continue;
                }

                // Check dependents
                $dependents = $this->moduleService->getDependentModules($company, $module);
                if (! empty($dependents) && ! $this->option('force')) {
                    $this->error("Cannot disable module for company '{$company->name}': Other modules depend on it: ".implode(', ', $dependents));
                    $this->info('Use --force to override this check.');
                    $failureCount++;

                    continue;
                }

                $result = $this->moduleService->disableModule($company, $module->key, $superAdmin);

                if ($result) {
                    $this->info("✓ Disabled module '{$module->name}' for company '{$company->name}'");
                    $successCount++;
                } else {
                    $this->error("✗ Failed to disable module '{$module->name}' for company '{$company->name}'");
                    $failureCount++;
                }
            } catch (\Exception $e) {
                $this->error("✗ Error disabling module for company '{$company->name}': {$e->getMessage()}");
                $failureCount++;
            }
        }

        // Clear CLI context
        $this->contextService->clearCLICompanyContext();

        $this->info("\nSummary:");
        $this->info("  Disabled: {$successCount} companies");
        $this->info("  Failed: {$failureCount} companies");

        return $failureCount > 0 ? 1 : 0;
    }

    /**
     * Show module status for companies.
     */
    private function moduleStatus(): int
    {
        $moduleKey = $this->option('module');
        if (! $moduleKey) {
            $this->error('Module key or ID is required. Use --module=<key|id>');

            return 1;
        }

        $module = $this->findModule($moduleKey);
        if (! $module) {
            $this->error("Module '{$moduleKey}' not found.");

            return 1;
        }

        $companies = $this->getTargetCompanies();
        if ($companies->isEmpty()) {
            $this->error('No companies found.');

            return 1;
        }

        $this->info("Module Status: {$module->name} ({$module->key})");
        $this->info(str_repeat('-', 80));

        $headers = ['Company', 'Status', 'Enabled At', 'Settings'];
        $rows = [];

        foreach ($companies as $company) {
            $status = 'Disabled';
            $enabledAt = 'N/A';
            $settings = 'N/A';

            if ($company->hasModuleEnabled($module->key)) {
                $status = 'Enabled';
                $companyModule = $company->companyModules()->where('module_id', $module->id)->first();
                $enabledAt = $companyModule?->enabled_at?->format('Y-m-d H:i:s') ?? 'N/A';
                $settings = $companyModule?->settings ? json_encode($companyModule->settings) : '{}';
            }

            $rows[] = [
                $company->name,
                $status,
                $enabledAt,
                $settings,
            ];
        }

        $this->table($headers, $rows);

        // Show usage statistics
        $stats = $this->moduleService->getModuleUsageStats($module);
        $this->info("\nUsage Statistics:");
        $this->info("  Total Companies: {$stats['total_companies']}");
        $this->info("  Active Companies: {$stats['active_companies']}");
        $this->info("  Enabled This Month: {$stats['enabled_this_month']}");

        return 0;
    }

    /**
     * Install a new module.
     */
    private function installModule(): int
    {
        $this->error('Module installation is not yet implemented.');

        return 1;
    }

    /**
     * Uninstall a module.
     */
    private function uninstallModule(): int
    {
        $this->error('Module uninstallation is not yet implemented.');

        return 1;
    }

    /**
     * Find module by key or ID.
     */
    private function findModule(string $identifier): ?Module
    {
        // Try by ID first
        if (is_numeric($identifier)) {
            $module = Module::find($identifier);
            if ($module) {
                return $module;
            }
        }

        // Try by key
        return $this->moduleService->getModuleByKey($identifier);
    }

    /**
     * Get target companies based on options.
     */
    private function getTargetCompanies(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->option('all-companies')) {
            return Company::where('is_active', true)->get();
        }

        $companyIdentifier = $this->option('company');
        if (! $companyIdentifier) {
            $this->error('Company ID/slug is required. Use --company=<id|slug> or --all-companies');

            return collect();
        }

        // Try by ID first
        if (is_numeric($companyIdentifier)) {
            $company = Company::find($companyIdentifier);

            return $company ? collect([$company]) : collect();
        }

        // Try by slug
        $company = Company::where('slug', $companyIdentifier)->first();

        return $company ? collect([$company]) : collect();
    }
}
