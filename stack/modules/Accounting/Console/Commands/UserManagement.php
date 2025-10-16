<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Models\User;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Services\UserService;
use App\Services\AuthService;

class UserManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:user
                            {action : The action to perform (list, create, deactivate, reactivate, invite, remove, stats)}
                            {--email= : User email address}
                            {--name= : User name}
                            {--password= : User password}
                            {--role=employee : User role}
                            {--company= : Company ID or slug}
                            {--force : Force action without confirmation}
                            {--all-companies : Show users from all companies}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage accounting users';

    public function __construct(
        private UserService $userService,
        private AuthService $authService
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
            'list' => $this->listUsers(),
            'create' => $this->createUser(),
            'deactivate' => $this->deactivateUser(),
            'reactivate' => $this->reactivateUser(),
            'invite' => $this->inviteUser(),
            'remove' => $this->removeUser(),
            'stats' => $this->showStats(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * List users.
     */
    private function listUsers(): int
    {
        $companyId = $this->option('company');

        if ($companyId || !$this->option('all-companies')) {
            // List users for specific company
            $company = $this->getCompany($companyId);
            if (!$company) {
                return 1;
            }

            $users = $this->userService->getCompanyUsers($company, null, true);

            if ($users->isEmpty()) {
                $this->info("No users found for company '{$company->name}'.");
                return 0;
            }

            $this->info("Users for Company: {$company->name} ({$company->slug})");
            $this->info(str_repeat('-', 100));

            $headers = ['ID', 'Name', 'Email', 'Role', 'Status', 'Joined', 'Invited By'];
            $rows = [];

            foreach ($users as $user) {
                $rows[] = [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->pivot->role,
                    $user->pivot->is_active ? 'Active' : 'Inactive',
                    $user->pivot->joined_at?->format('Y-m-d') ?? 'N/A',
                    $user->pivot->invitedBy?->name ?? 'N/A',
                ];
            }

            $this->table($headers, $rows);
        } else {
            // List all users
            $users = User::withCount('companies')->get();

            if ($users->isEmpty()) {
                $this->info('No users found.');
                return 0;
            }

            $this->info('All Users:');
            $this->info(str_repeat('-', 80));

            $headers = ['ID', 'Name', 'Email', 'System Role', 'Companies', 'Status', 'Created'];
            $rows = [];

            foreach ($users as $user) {
                $rows[] = [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->system_role ?? 'user',
                    $user->companies_count,
                    $user->is_active ? 'Active' : 'Inactive',
                    $user->created_at->format('Y-m-d'),
                ];
            }

            $this->table($headers, $rows);
        }

        return 0;
    }

    /**
     * Create a new user.
     */
    private function createUser(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address');
        }

        if (empty($email)) {
            $this->error('Email is required.');
            return 1;
        }

        if (!$this->userService->isEmailAvailable($email)) {
            $this->error("Email '{$email}' is already taken.");
            return 1;
        }

        $name = $this->option('name');
        if (!$name) {
            $name = $this->ask('Enter user name', $email);
        }

        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Enter password');
            $passwordConfirmation = $this->secret('Confirm password');

            if ($password !== $passwordConfirmation) {
                $this->error('Passwords do not match.');
                return 1;
            }
        }

        // Validate password strength
        $strengthCheck = $this->authService->validatePasswordStrength($password);
        if (!$strengthCheck['valid']) {
            $this->error('Password does not meet requirements:');
            foreach ($strengthCheck['errors'] as $error) {
                $this->error("  - {$error}");
            }
            return 1;
        }

        $systemRole = $this->choice('Select system role', ['user', 'admin', 'superadmin'], 0);

        try {
            $userData = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
                'system_role' => $systemRole === 'user' ? null : $systemRole,
            ];

            $user = $this->userService->createWithTemporaryPassword($userData, false);

            $this->info("✓ Created user: {$user->name} ({$user->email})");
            $this->info("  ID: {$user->id}");
            $this->info("  System Role: {$systemRole}");

            // Ask to add to company
            if ($this->confirm('Add user to a company?')) {
                $company = $this->selectCompany();
                if ($company) {
                    $role = $this->choice('Select company role', ['employee', 'viewer', 'manager', 'accountant', 'admin', 'owner'], 0);
                    $company->addUser($user, $role);
                    $this->info("✓ Added user to company '{$company->name}' with role '{$role}'");
                }
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create user: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Deactivate a user.
     */
    private function deactivateUser(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address');
        }

        if (empty($email)) {
            $this->error('Email is required.');
            return 1;
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User '{$email}' not found.");
            return 1;
        }

        if (!$user->is_active) {
            $this->warn("User '{$user->name}' is already inactive.");
            return 0;
        }

        $this->info("User: {$user->name} ({$user->email})");
        $this->info("Status: Active");
        $this->info("Companies: {$user->companies()->count()}");

        if (!$this->option('force')) {
            $this->warn("\nDeactivating this user will:");
            $this->warn("- Remove access to all companies");
            $this->warn("- Invalidate all API tokens");
            $this->warn("- Keep all data for potential reactivation");

            if (!$this->confirm("\nDo you want to continue?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $this->userService->deactivateUser($user);

            $this->info("✓ Deactivated user: {$user->name}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to deactivate user: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Reactivate a user.
     */
    private function reactivateUser(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address');
        }

        if (empty($email)) {
            $this->error('Email is required.');
            return 1;
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User '{$email}' not found.");
            return 1;
        }

        if ($user->is_active) {
            $this->warn("User '{$user->name}' is already active.");
            return 0;
        }

        $this->info("User: {$user->name} ({$user->email})");
        $this->info("Status: Inactive");

        if (!$this->confirm('Do you want to reactivate this user?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        try {
            $this->userService->reactivateUser($user);

            $this->info("✓ Reactivated user: {$user->name}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to reactivate user: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Invite user to company.
     */
    private function inviteUser(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address');
        }

        if (empty($email)) {
            $this->error('Email is required.');
            return 1;
        }

        $company = $this->selectCompany();
        if (!$company) {
            return 1;
        }

        $role = $this->option('role');
        if ($role === 'employee') {
            $role = $this->choice('Select company role', ['employee', 'viewer', 'manager', 'accountant', 'admin', 'owner'], 0);
        }

        try {
            $invitedBy = User::where('system_role', 'superadmin')->first() ?? auth()->user();
            if (!$invitedBy) {
                $this->error('No authenticated user found to send invitation.');
                return 1;
            }

            [$user, $status] = $this->userService->inviteToCompany($email, $company, $role, $invitedBy);

            $this->info("✓ " . ($status === 'created' ? 'Created and invited' : 'Invited') . " user: {$email}");
            $this->info("  Company: {$company->name}");
            $this->info("  Role: {$role}");

            if ($status === 'created') {
                $this->info("  Note: User was created with temporary password");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to invite user: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Remove user from company.
     */
    private function removeUser(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address');
        }

        if (empty($email)) {
            $this->error('Email is required.');
            return 1;
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("User '{$email}' not found.");
            return 1;
        }

        $company = $this->selectCompany();
        if (!$company) {
            return 1;
        }

        if (!$user->hasCompanyRole($company)) {
            $this->error("User is not a member of company '{$company->name}'.");
            return 1;
        }

        $role = $this->authService->getUserRole($user, $company);
        $this->info("User: {$user->name} ({$user->email})");
        $this->info("Company: {$company->name}");
        $this->info("Role: {$role}");

        if ($role === 'owner') {
            $ownerCount = $company->companyUsers()->where('role', 'owner')->active()->count();
            if ($ownerCount <= 1) {
                $this->error('Cannot remove the last owner of the company.');
                return 1;
            }
        }

        if (!$this->confirm("Remove user from company?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        try {
            $removedBy = User::where('system_role', 'superadmin')->first() ?? auth()->user();
            $this->userService->removeFromCompany($user, $company, $removedBy);

            $this->info("✓ Removed user from company '{$company->name}'");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to remove user: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Show user statistics.
     */
    private function showStats(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address (leave empty for global stats)');
        }

        if ($email) {
            // Individual user stats
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User '{$email}' not found.");
                return 1;
            }

            $this->info("User Statistics: {$user->name} ({$user->email})");
            $this->info(str_repeat('-', 50));
            $this->info("ID: {$user->id}");
            $this->info("System Role: {$user->system_role ?? 'user'}");
            $this->info("Status: " . ($user->is_active ? 'Active' : 'Inactive'));
            $this->info("Created: " . $user->created_at->format('Y-m-d H:i:s'));
            $this->info("Last Login: " . ($user->last_login_at ? $user->last_login_at->format('Y-m-d H:i:s') : 'Never'));

            $this->info("\nCompanies:");
            foreach ($user->companies as $company) {
                $status = $company->pivot->is_active ? 'Active' : 'Inactive';
                $this->info("  - {$company->name}: {$company->pivot->role} ({$status})");
            }

            // Recent activity
            $activity = $this->userService->getRecentActivity($user, 10);
            if (!$activity->isEmpty()) {
                $this->info("\nRecent Activity:");
                foreach ($activity as $entry) {
                    $this->info("  - {$entry->action}: {$entry->created_at->format('Y-m-d H:i:s')}");
                }
            }
        } else {
            // Global user statistics
            $stats = $this->userService->getUserStatistics();

            $this->info("Global User Statistics");
            $this->info(str_repeat('-', 30));
            $this->info("Total Users: {$stats['total_users']}");
            $this->info("Active Users: {$stats['active_users']}");
            $this->info("Inactive Users: {$stats['inactive_users']}");
            $this->info("Super Admins: {$stats['super_admins']}");
            $this->info("Users with Companies: {$stats['users_with_companies']}");
            $this->info("Recent Registrations (30 days): {$stats['recent_registrations']}");
        }

        return 0;
    }

    /**
     * Get company by option or selection.
     */
    private function getCompany(?string $identifier = null): ?Company
    {
        if ($identifier) {
            // Try by ID first
            if (is_numeric($identifier)) {
                $company = Company::find($identifier);
                if ($company) {
                    return $company;
                }
            }

            // Try by slug
            return Company::where('slug', $identifier)->first();
        }

        // List companies for selection
        $companies = Company::where('is_active', true)->get();
        if ($companies->isEmpty()) {
            $this->error('No active companies found.');
            return null;
        }

        $choices = $companies->mapWithKeys(function ($company) {
            return [$company->id => "{$company->name} ({$company->slug})"];
        })->toArray();

        $selected = $this->choice('Select a company', $choices);
        $companyId = array_search($selected, $choices);

        return Company::find($companyId);
    }

    /**
     * Select a company from list.
     */
    private function selectCompany(): ?Company
    {
        $companyId = $this->option('company');
        return $this->getCompany($companyId);
    }
}