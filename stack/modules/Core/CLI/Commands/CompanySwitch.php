<?php

namespace Modules\Core\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Console\Command;

class CompanySwitch extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'company:switch
        {identifier? : Company slug or UUID}
        {--user= : Acting user email or UUID}
        {--clear : Clear the stored company context}';

    protected $description = 'Switch the active company context for CLI operations.';

    public function handle(AuthService $authService, ContextService $contextService): int
    {
        if ($this->option('clear')) {
            $this->cliContext()->forgetCompany();
            $contextService->clearCLICompanyContext();
            $this->info('Cleared stored company context.');

            return self::SUCCESS;
        }

        $identifier = $this->argument('identifier');

        try {
            $actingUser = $this->resolveActingUser($this, $authService, $this->option('user'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $identifier) {
            $company = $this->cliContext()->getCompany();
            if ($company) {
                $this->info("Current company: {$company->name} ({$company->id})");

                return self::SUCCESS;
            }

            $this->error('Provide a company slug/UUID or use --clear to reset context.');

            return self::FAILURE;
        }

        $company = $this->findCompany($identifier);

        if (! $company) {
            $this->error("Company '{$identifier}' not found.");

            return self::FAILURE;
        }

        if (! $authService->canAccessCompany($actingUser, $company)) {
            $this->error("User '{$actingUser->email}' does not have access to '{$company->name}'.");

            return self::FAILURE;
        }

        if (! $contextService->setCurrentCompany($actingUser, $company)) {
            $this->error('Failed to set company context.');

            return self::FAILURE;
        }

        $this->cliContext()->rememberCompany($company);

        $this->info("Active company set to {$company->name} ({$company->id}).");

        return self::SUCCESS;
    }
}
