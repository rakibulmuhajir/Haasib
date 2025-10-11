<?php

namespace Modules\Core\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Models\Module;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Console\Command;

class ModuleList extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'module:list
        {--user= : Acting user email or UUID}
        {--company= : Company slug or UUID to scope results}
        {--all : Include inactive modules}
        {--installed : Only show modules enabled for the company}';

    protected $description = 'List modules in the catalog or for a specific company.';

    public function handle(AuthService $authService, ContextService $contextService): int
    {
        $companyIdentifier = $this->option('company');
        $installedOnly = (bool) $this->option('installed');

        $company = null;
        $actingUser = null;

        if ($companyIdentifier || $installedOnly) {
            try {
                $actingUser = $this->resolveActingUser($this, $authService, $this->option('user'));
                $company = $this->resolveCompany(
                    $this,
                    $authService,
                    $contextService,
                    $actingUser,
                    $companyIdentifier,
                    true
                );
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
        }

        $query = Module::query()->orderBy('menu_order')->orderBy('name');

        if (! $this->option('all')) {
            $query->where('is_active', true);
        }

        $modules = $query->get();

        if ($modules->isEmpty()) {
            $this->warn('No modules found.');
            $this->cleanup($contextService, $actingUser);

            return self::SUCCESS;
        }

        $rows = $modules->map(function (Module $module) use ($company, $installedOnly) {
            $isInstalled = $company
                ? $company->modules()->where('auth.company_modules.module_id', $module->id)->wherePivot('is_active', true)->exists()
                : false;

            if ($company && $installedOnly && ! $isInstalled) {
                return null;
            }

            return [
                $module->id,
                $module->key,
                $module->name,
                $module->category,
                $module->version,
                $module->is_active ? 'active' : 'inactive',
                $isInstalled ? 'yes' : 'no',
            ];
        })->filter()->values()->all();

        if (empty($rows)) {
            $this->warn('No modules matched the supplied filters.');
            $this->cleanup($contextService, $actingUser);

            return self::SUCCESS;
        }

        $this->table(['ID', 'Key', 'Name', 'Category', 'Version', 'Status', 'Installed'], $rows);

        $this->cleanup($contextService, $actingUser);

        return self::SUCCESS;
    }

    protected function cleanup(ContextService $contextService, ?User $actingUser): void
    {
        if ($actingUser) {
            $contextService->clearCurrentCompany($actingUser);
        }
        $contextService->clearCLICompanyContext();
    }
}
