<?php

namespace Modules\Accounting\CLI\Commands;

use App\Models\User;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserSwitch extends Command
{
    protected $signature = 'acc:user:switch {email : Email address to switch to} {--show-companies : Show associated companies}';

    protected $description = 'Switch active user context (Accounting module)';

    public function handle(ContextService $contextService, AuthService $authService): int
    {
        $email = $this->argument('email');
        $showCompanies = $this->option('show-companies');

        // Find the user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User '{$email}' not found.");

            return 1;
        }

        // Check if user is active
        if (! $user->is_active) {
            $this->error("User '{$email}' is inactive and cannot be used.");

            return 1;
        }

        // Switch user context
        try {
            $authService->setCurrentUser($user);

            $this->info("Switched to user: {$user->name}");
            $this->info("  ID: {$user->id}");
            $this->info("  Email: {$user->email}");
            $this->info("  Role: {$user->system_role}");
            $this->info('  Last Login: '.($user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never'));

            if ($showCompanies) {
                // Show associated companies
                $companies = DB::table('auth.company_user as cu')
                    ->join('auth.companies as c', 'cu.company_id', '=', 'c.id')
                    ->where('cu.user_id', $user->id)
                    ->select('c.name', 'c.slug', 'c.base_currency', 'c.is_active', 'cu.role as company_role')
                    ->orderBy('c.name')
                    ->get();

                if ($companies->isNotEmpty()) {
                    $this->info("\nAssociated Companies:");
                    foreach ($companies as $company) {
                        $status = $company->is_active ? '[ACTIVE]' : '[INACTIVE]';
                        $this->info("  • {$company->name} ({$company->slug}) - {$company->company_role} {$status}");
                        $this->info("    Currency: {$company->base_currency}");
                    }
                } else {
                    $this->info("\nNo associated companies");
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to switch user: {$e->getMessage()}");

            return 1;
        }
    }
}
