<?php

namespace Modules\Core\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Models\Company;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Console\Command;

class UserSwitch extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'user:switch
        {identifier? : User email or UUID}
        {--list : List companies associated with the user}
        {--clear : Clear the stored CLI user context}';

    protected $description = 'Remember a user for subsequent CLI operations and optionally inspect their companies.';

    public function handle(AuthService $authService, ContextService $contextService): int
    {
        if ($this->option('clear')) {
            $this->cliContext()->forgetUser();
            $this->info('Cleared stored CLI user context.');

            return self::SUCCESS;
        }

        $identifier = $this->argument('identifier');

        if (! $identifier) {
            $current = $this->cliContext()->getUser();
            if ($current) {
                $this->info("Current CLI user: {$current->email} ({$current->id})");
                if ($this->option('list')) {
                    $this->displayCompanies($current, $authService, $contextService);
                }

                return self::SUCCESS;
            }

            $this->error('Provide a user email/UUID or use --clear to reset context.');

            return self::FAILURE;
        }

        $user = $this->findUser($identifier);

        if (! $user) {
            $this->error("User '{$identifier}' not found.");

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error("User '{$user->email}' is inactive.");

            return self::FAILURE;
        }

        $this->cliContext()->rememberUser($user);

        $this->info("Active CLI user set to {$user->email} ({$user->id}).");

        if ($this->option('list')) {
            $this->displayCompanies($user, $authService, $contextService);
        }

        return self::SUCCESS;
    }

    protected function displayCompanies(User $user, AuthService $authService, ContextService $contextService): void
    {
        $companies = $user->companies()->orderBy('auth.companies.name')->get();

        if ($companies->isEmpty()) {
            $this->line('  No companies associated with this user.');

            return;
        }

        $rows = $companies->map(function (Company $company) use ($user, $contextService) {
            $contextService->setCurrentCompany($user, $company);
            $modules = $company->modules()->wherePivot('is_active', true)->pluck('modules.name')->implode(', ');
            $contextService->clearCurrentCompany($user);

            return [
                $company->id,
                $company->name,
                $company->slug,
                $company->base_currency,
                $company->pivot->role,
                $company->is_active ? 'active' : 'inactive',
                $modules ?: '—',
            ];
        })->all();

        $this->table(['ID', 'Name', 'Slug', 'Currency', 'Role', 'Status', 'Modules'], $rows);
    }
}
