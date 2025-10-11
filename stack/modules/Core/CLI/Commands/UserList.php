<?php

namespace Modules\Core\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Console\Command;

class UserList extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'user:list
        {--user= : Acting user email or UUID}
        {--company= : Filter by company slug or UUID}
        {--role= : Filter by company role}
        {--include-inactive : Include inactive users}';

    protected $description = 'List users with optional company and role filters.';

    public function handle(AuthService $authService, ContextService $contextService): int
    {
        try {
            $actingUser = $this->resolveActingUser($this, $authService, $this->option('user'));
            $company = $this->resolveCompany(
                $this,
                $authService,
                $contextService,
                $actingUser,
                $this->option('company'),
                false
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $query = User::query()->orderBy('name');

        if (! $this->option('include-inactive')) {
            $query->where('is_active', true);
        }

        if ($company) {
            $query->whereHas('companies', function ($q) use ($company) {
                $q->where('auth.company_user.company_id', $company->id);
            });
        }

        if ($role = $this->option('role')) {
            $query->whereHas('companies', function ($q) use ($role, $company) {
                if ($company) {
                    $q->where('auth.company_user.company_id', $company->id);
                }
                $q->where('auth.company_user.role', $role);
            });
        }

        $users = $query->get(['id', 'name', 'email', 'system_role', 'is_active', 'created_at']);

        if ($users->isEmpty()) {
            $this->warn('No users found for the provided filters.');
            $this->cleanup($contextService, $actingUser);

            return self::SUCCESS;
        }

        $rows = $users->map(function (User $user) use ($company) {
            $role = null;
            if ($company) {
                $role = $user->companies->firstWhere('pivot.company_id', $company->id)?->pivot->role;
            }

            return [
                $user->id,
                $user->name,
                $user->email,
                $user->system_role,
                $company ? ($role ?? '—') : ($user->companies->pluck('pivot.role')->unique()->implode(', ') ?: '—'),
                $user->is_active ? 'active' : 'inactive',
                $user->created_at?->format('Y-m-d'),
            ];
        })->all();

        $this->table(['ID', 'Name', 'Email', 'System Role', $company ? 'Company Role' : 'Roles', 'Status', 'Created'], $rows);

        $this->cleanup($contextService, $actingUser);

        return self::SUCCESS;
    }

    protected function cleanup(ContextService $contextService, User $actingUser): void
    {
        $contextService->clearCurrentCompany($actingUser);
        $contextService->clearCLICompanyContext();
    }
}
