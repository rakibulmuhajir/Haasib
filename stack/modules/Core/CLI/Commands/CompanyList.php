<?php

namespace Modules\Core\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Models\Company;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Console\Command;

class CompanyList extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'company:list
        {--user= : Acting user email or UUID}
        {--include-inactive : Include inactive companies}
        {--with-modules : Show enabled modules}';

    protected $description = 'List companies accessible to the acting user.';

    public function handle(AuthService $authService, ContextService $contextService): int
    {
        try {
            $actingUser = $this->resolveActingUser($this, $authService, $this->option('user'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $query = Company::query()->orderBy('name');

        if (! $this->option('include-inactive')) {
            $query->where('is_active', true);
        }

        if (! $actingUser->isSuperAdmin()) {
            $query->whereHas('users', function ($q) use ($actingUser) {
                $q->where('auth.company_user.user_id', $actingUser->id)
                    ->where('auth.company_user.is_active', true);
            });
        }

        $companies = $query->get();

        if ($companies->isEmpty()) {
            $this->warn('No companies found for the acting user.');

            return self::SUCCESS;
        }

        $showModules = $this->option('with-modules');

        $rows = $companies->map(function (Company $company) use ($actingUser, $contextService, $showModules) {
            $role = $company->pivot?->role;

            if (! $role) {
                $role = $company->users()
                    ->where('auth.company_user.user_id', $actingUser->id)
                    ->value('auth.company_user.role');
            }

            $modules = '—';

            if ($showModules) {
                $contextService->setCurrentCompany($actingUser, $company);
                $modules = $company->modules()
                    ->wherePivot('is_active', true)
                    ->orderBy('modules.name')
                    ->pluck('modules.name')
                    ->implode(', ') ?: '—';
                $contextService->clearCurrentCompany($actingUser);
            }

            return [
                $company->id,
                $company->name,
                $company->slug,
                $company->base_currency,
                $role ?? '—',
                $company->is_active ? 'active' : 'inactive',
                $modules,
            ];
        })->all();

        $headers = ['ID', 'Name', 'Slug', 'Currency', 'Role', 'Status'];
        if ($showModules) {
            $headers[] = 'Modules';
        }

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
