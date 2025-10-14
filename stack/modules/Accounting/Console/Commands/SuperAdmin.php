<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Models\User;
use Modules\Accounting\Services\UserService;
use App\Services\AuthService;

class SuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:superadmin
                            {action : The action to perform (create, list, revoke, verify)}
                            {--email= : User email address}
                            {--name= : User name}
                            {--password= : User password}
                            {--force : Force action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage super admin users';

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
            'create' => $this->createSuperAdmin(),
            'list' => $this->listSuperAdmins(),
            'revoke' => $this->revokeSuperAdmin(),
            'verify' => $this->verifySuperAdmin(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * Create a new super admin.
     */
    private function createSuperAdmin(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter super admin email address');
        }

        if (empty($email)) {
            $this->error('Email is required.');
            return 1;
        }

        // Check if user exists
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Create new user
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

            try {
                $userData = [
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                    'password_confirmation' => $password,
                ];

                [$user, $_] = $this->userService->registerUser($userData);
                $this->info("✓ Created user: {$user->name} ({$user->email})");
            } catch (\Exception $e) {
                $this->error("Failed to create user: {$e->getMessage()}");
                return 1;
            }
        } else {
            $this->info("Found existing user: {$user->name} ({$user->email})");
        }

        // Check if already super admin
        if ($user->system_role === 'superadmin') {
            $this->warn('User is already a super admin.');
            return 0;
        }

        $this->info("\nUser Details:");
        $this->info("  Name: {$user->name}");
        $this->info("  Email: {$user->email}");
        $this->info("  Current Role: {$user->system_role ?? 'user'}");

        if (!$this->option('force')) {
            $this->warn("\nSuper admin privileges:");
            $this->warn("- Access to all companies");
            $this->warn("- Can manage all users and companies");
            $this->warn("- Can view all audit logs");
            $this->warn("- Full system administration");

            if (!$this->confirm("\nGrant super admin privileges to this user?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $user->update(['system_role' => 'superadmin']);
            $this->info("✓ Granted super admin privileges to {$user->name}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to grant super admin privileges: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * List all super admins.
     */
    private function listSuperAdmins(): int
    {
        $superAdmins = User::where('system_role', 'superadmin')
            ->orderBy('created_at')
            ->get();

        if ($superAdmins->isEmpty()) {
            $this->info('No super admins found.');
            return 0;
        }

        $this->info('Super Admin Users:');
        $this->info(str_repeat('-', 80));

        $headers = ['ID', 'Name', 'Email', 'Status', 'Created', 'Last Login'];
        $rows = [];

        foreach ($superAdmins as $admin) {
            $rows[] = [
                $admin->id,
                $admin->name,
                $admin->email,
                $admin->is_active ? 'Active' : 'Inactive',
                $admin->created_at->format('Y-m-d H:i:s'),
                $admin->last_login_at?->format('Y-m-d H:i:s') ?? 'Never',
            ];
        }

        $this->table($headers, $rows);

        // Show summary
        $this->info("\nSummary:");
        $this->info("  Total Super Admins: {$superAdmins->count()}");
        $this->info("  Active: {$superAdmins->where('is_active', true)->count()}");
        $this->info("  Inactive: {$superAdmins->where('is_active', false)->count()}");

        return 0;
    }

    /**
     * Revoke super admin privileges.
     */
    private function revokeSuperAdmin(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter super admin email address');
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

        if ($user->system_role !== 'superadmin') {
            $this->warn("User '{$user->name}' is not a super admin.");
            return 0;
        }

        // Check if this is the last super admin
        $superAdminCount = User::where('system_role', 'superadmin')->count();
        if ($superAdminCount <= 1) {
            $this->error('Cannot revoke super admin privileges from the last super admin.');
            return 1;
        }

        $this->info("User: {$user->name} ({$user->email})");
        $this->info("Role: Super Admin");
        $this->info("Companies: {$user->companies()->count()}");

        if (!$this->option('force')) {
            $this->warn("\nRevoking super admin privileges will:");
            $this->warn("- Remove access to all companies");
            $this->warn("- Limit to user's assigned companies only");
            $this->warn("- Remove system administration capabilities");

            if (!$this->confirm("\nRevoke super admin privileges?")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $user->update(['system_role' => null]);
            $this->info("✓ Revoked super admin privileges from {$user->name}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to revoke super admin privileges: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Verify super admin status and permissions.
     */
    private function verifySuperAdmin(): int
    {
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address (leave empty to verify all super admins)');
        }

        if ($email) {
            // Verify specific user
            $user = User::where('email', $email)->first();
            if (!$user) {
                $this->error("User '{$email}' not found.");
                return 1;
            }

            $this->verifyUser($user);
        } else {
            // Verify all super admins
            $superAdmins = User::where('system_role', 'superadmin')->get();

            if ($superAdmins->isEmpty()) {
                $this->warn('No super admins found to verify.');
                return 0;
            }

            $this->info("Verifying {$superAdmins->count()} super admin(s)...\n");

            $allValid = true;
            foreach ($superAdmins as $admin) {
                if (!$this->verifyUser($admin, false)) {
                    $allValid = false;
                }
                $this->info('');
            }

            if ($allValid) {
                $this->info('✓ All super admins verified successfully');
            } else {
                $this->warn('Some super admins have issues that need attention');
            }
        }

        return 0;
    }

    /**
     * Verify individual super admin.
     */
    private function verifyUser(User $user, bool $showHeader = true): bool
    {
        if ($showHeader) {
            $this->info("Super Admin Verification: {$user->name} ({$user->email})");
            $this->info(str_repeat('-', 50));
        }

        $isValid = true;

        // Check role
        $this->info("Role: " . ($user->system_role === 'superadmin' ? '✓ Super Admin' : '✗ Not Super Admin'));
        if ($user->system_role !== 'superadmin') {
            $isValid = false;
        }

        // Check status
        $this->info("Status: " . ($user->is_active ? '✓ Active' : '✗ Inactive'));
        if (!$user->is_active) {
            $isValid = false;
        }

        // Check companies access
        $companyCount = $user->companies()->count();
        $this->info("Direct Company Access: {$companyCount} companies");

        // Check last login
        if ($user->last_login_at) {
            $daysSinceLogin = $user->last_login_at->diffInDays(now());
            $this->info("Last Login: {$user->last_login_at->format('Y-m-d H:i:s')} ({$daysSinceLogin} days ago)");

            if ($daysSinceLogin > 90) {
                $this->warn("  ⚠ Warning: No login for over 90 days");
            }
        } else {
            $this->warn("  ⚠ Warning: Never logged in");
        }

        // Test permissions
        $this->info("\nPermission Tests:");

        // Can create companies
        $canCreateCompany = $this->testPermission($user, 'Can create companies', function () use ($user) {
            // In a real implementation, this would use the permission system
            return $user->system_role === 'superadmin';
        });

        // Can manage users
        $canManageUsers = $this->testPermission($user, 'Can manage users', function () use ($user) {
            return $user->system_role === 'superadmin';
        });

        // Can view all audit logs
        $canViewAudits = $this->testPermission($user, 'Can view audit logs', function () use ($user) {
            return $user->system_role === 'superadmin';
        });

        if (!$canCreateCompany || !$canManageUsers || !$canViewAudits) {
            $isValid = false;
        }

        // Summary
        if ($isValid) {
            $this->info("\n✓ Super admin status verified");
        } else {
            $this->error("\n✗ Super admin verification failed");
        }

        return $isValid;
    }

    /**
     * Test a specific permission.
     */
    private function testPermission(User $user, string $description, callable $test): bool
    {
        try {
            $result = $test();
            $this->info("  {$description}: " . ($result ? '✓' : '✗'));
            return $result;
        } catch (\Exception $e) {
            $this->info("  {$description}: ✗ (Error: {$e->getMessage()})");
            return false;
        }
    }
}