<?php

namespace Modules\Core\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Console\Command;
use Modules\Core\Services\ModuleService;

class ModuleEnable extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'module:enable
        {module : Module key or name}
        {--user= : Acting user email or UUID}
        {--company= : Company slug or UUID}
        {--disable : Disable the module instead of enabling it}
        {--setting=* : Module setting in key=value format}';

    protected $description = 'Enable or disable a module for a company.';

    public function handle(ModuleService $moduleService, AuthService $authService, ContextService $contextService): int
    {
        $moduleKey = $this->argument('module');
        $settings = $this->parseSettings((array) $this->option('setting'));

        try {
            $actingUser = $this->resolveActingUser($this, $authService, $this->option('user'));
            $company = $this->resolveCompany(
                $this,
                $authService,
                $contextService,
                $actingUser,
                $this->option('company'),
                true
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        try {
            if ($this->option('disable')) {
                $moduleService->disableModule($company, $moduleKey, $actingUser);
                $this->info("Module '{$moduleKey}' disabled for {$company->name}.");
            } else {
                $moduleService->enableModule($company, $moduleKey, $actingUser, $settings);
                $this->info("Module '{$moduleKey}' enabled for {$company->name}.");
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->cleanup($contextService, $actingUser);

            return self::FAILURE;
        }

        $this->cleanup($contextService, $actingUser);

        return self::SUCCESS;
    }

    protected function parseSettings(array $settings): array
    {
        $parsed = [];

        foreach ($settings as $entry) {
            if (! str_contains($entry, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $entry, 2);
            $parsed[$key] = $value;
        }

        return $parsed;
    }

    protected function cleanup(ContextService $contextService, $actingUser): void
    {
        if ($actingUser) {
            $contextService->clearCurrentCompany($actingUser);
        }
        $contextService->clearCLICompanyContext();
    }
}
