<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\User;
use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserList extends Command
{
    protected $signature = 'acc:user:list {--role= : Filter by role} {--detailed : Show detailed information}';

    protected $description = 'List all users in the system (Accounting module)';

    public function handle(ContextService $contextService): int
    {
        $role = $this->option('role');
        $detailed = $this->option('detailed');

        // Check for user context
        $currentUser = $contextService->getCurrentUser();
        if (! $currentUser) {
            $this->error('No active user context. Please set user context first.');

            return 1;
        }

        // Build query
        $query = User::query();

        // Apply role filter
        if ($role) {
            $query->where('system_role', $role);
        }

        $users = $query->orderBy('name')->get();

        if ($users->isEmpty()) {
            $this->info('No users found');

            return 0;
        }

        $this->info("\nUsers:");
        $this->info(str_repeat('-', 100));

        foreach ($users as $user) {
            $status = $user->is_active ? '[ACTIVE]' : '[INACTIVE]';
            $this->info("• {$user->name} ({$user->email}) - {$user->system_role} {$status}");

            if ($detailed) {
                $this->info("  ID: {$user->id}");
                $this->info("  Created: {$user->created_at->format('Y-m-d H:i:s')}");
                $this->info('  Last Login: '.($user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never'));

                // Show associated companies
                $companies = DB::table('auth.company_user as cu')
                    ->join('auth.companies as c', 'cu.company_id', '=', 'c.id')
                    ->where('cu.user_id', $user->id)
                    ->select('c.name', 'c.slug', 'cu.role as company_role')
                    ->get();

                if ($companies->isNotEmpty()) {
                    $this->info('  Companies:');
                    foreach ($companies as $company) {
                        $this->info("    - {$company->name} ({$company->slug}) - {$company->company_role}");
                    }
                }
            }

            $this->info('');
        }

        $this->info("Total: {$users->count()} users");

        return 0;
    }
}
