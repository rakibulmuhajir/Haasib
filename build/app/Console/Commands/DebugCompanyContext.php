<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CompanyContextManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugCompanyContext extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'debug:company-context 
                            {user? : User ID or email to debug}
                            {--all : Show debug info for all users}
                            {--json : Output as JSON}
                            {--simulate-request : Simulate HTTP request context}';

    /**
     * The console command description.
     */
    protected $description = 'Debug company context resolution for users';

    public function __construct(
        private CompanyContextManager $companyContextManager
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->debugAllUsers();
        }

        $userIdentifier = $this->argument('user');
        
        if (!$userIdentifier) {
            $this->error('Please provide a user ID/email or use --all flag');
            return self::FAILURE;
        }

        $user = $this->findUser($userIdentifier);
        
        if (!$user) {
            $this->error("User not found: {$userIdentifier}");
            return self::FAILURE;
        }

        return $this->debugUser($user);
    }

    /**
     * Debug company context for a specific user.
     */
    private function debugUser(User $user): int
    {
        $this->info("Company Context Debug for User: {$user->name} ({$user->email})");
        $this->line('');

        try {
            // Get debug information
            $request = $this->option('simulate-request') ? $this->createMockRequest() : null;
            $debugInfo = $this->companyContextManager->getDebugInfo($user, $request);
            $activeCompany = $this->companyContextManager->getActiveCompany($user, $request);
            $userCompanies = $this->companyContextManager->getUserCompanies($user);

            if ($this->option('json')) {
                $this->line(json_encode([
                    'debug_info' => $debugInfo,
                    'active_company' => $activeCompany,
                    'user_companies' => $userCompanies,
                ], JSON_PRETTY_PRINT));
                return self::SUCCESS;
            }

            // Display user basic info
            $this->displayUserInfo($user);
            $this->line('');

            // Display resolution steps
            $this->displayResolutionSteps($debugInfo['resolution_steps']);
            $this->line('');

            // Display final resolution
            $this->displayFinalResolution($activeCompany);
            $this->line('');

            // Display user companies
            $this->displayUserCompanies($userCompanies);
            $this->line('');

            // Display cache information
            $this->displayCacheInfo($debugInfo['cache_keys']);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to debug user: {$e->getMessage()}");
            $this->error("Stack trace: {$e->getTraceAsString()}");
            return self::FAILURE;
        }
    }

    /**
     * Debug company context for all users.
     */
    private function debugAllUsers(): int
    {
        $users = User::with('companies')->limit(10)->get();

        if ($users->isEmpty()) {
            $this->warn('No users found');
            return self::SUCCESS;
        }

        $this->info('Company Context Debug for All Users (first 10)');
        $this->line('');

        $results = [];

        foreach ($users as $user) {
            try {
                $activeCompany = $this->companyContextManager->getActiveCompany($user);
                $userCompanies = $this->companyContextManager->getUserCompanies($user);

                $results[] = [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'active_company' => $activeCompany['name'] ?? 'None',
                    'active_company_id' => $activeCompany['id'] ?? null,
                    'company_count' => count($userCompanies),
                    'preferred_company_id' => $user->preferred_company_id,
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'user_id' => $user->id,
                    'user_email' => $user->email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        // Display as table
        $this->table(
            ['User Email', 'Active Company', 'Company Count', 'Preferred ID', 'Status'],
            collect($results)->map(function ($result) {
                return [
                    $result['user_email'],
                    $result['active_company'] ?? 'Error',
                    $result['company_count'] ?? 'N/A',
                    $result['preferred_company_id'] ?? 'None',
                    isset($result['error']) ? '❌ Error' : '✅ OK',
                ];
            })->toArray()
        );

        return self::SUCCESS;
    }

    /**
     * Find user by ID or email.
     */
    private function findUser(string $identifier): ?User
    {
        // Try by ID first
        if (ctype_digit($identifier) || preg_match('/^[0-9a-f-]+$/i', $identifier)) {
            $user = User::find($identifier);
            if ($user) return $user;
        }

        // Try by email
        return User::where('email', $identifier)->first();
    }

    /**
     * Create a mock HTTP request for testing.
     */
    private function createMockRequest(): \Illuminate\Http\Request
    {
        $request = new \Illuminate\Http\Request();
        $request->setSession(app('session.store'));
        return $request;
    }

    /**
     * Display user information.
     */
    private function displayUserInfo(User $user): void
    {
        $this->table(
            ['Property', 'Value'],
            [
                ['User ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['System Role', $user->system_role ?? 'None'],
                ['Preferred Company ID', $user->preferred_company_id ?? 'None'],
                ['Is Active', $user->is_active ? '✅ Yes' : '❌ No'],
                ['Created', $user->created_at->format('Y-m-d H:i:s')],
            ]
        );
    }

    /**
     * Display resolution steps.
     */
    private function displayResolutionSteps(array $steps): void
    {
        $this->info('🔍 Company Resolution Steps:');

        foreach ($steps as $step => $data) {
            $status = $this->getStepStatus($step, $data);
            $details = $this->getStepDetails($step, $data);

            $this->line("  {$status} <comment>{$step}</comment>: {$details}");
        }
    }

    /**
     * Get status icon for a resolution step.
     */
    private function getStepStatus(string $step, array $data): string
    {
        switch ($step) {
            case 'route':
                return $data['valid'] ? '✅' : '❌';
            case 'session':
                return $data['user_has_access'] && $data['valid_uuid'] ? '✅' : '❌';
            case 'database':
                return $data['user_has_access'] && $data['company_exists'] ? '✅' : '❌';
            case 'first':
                return $data['available'] ? '✅' : '❌';
            default:
                return '❓';
        }
    }

    /**
     * Get details string for a resolution step.
     */
    private function getStepDetails(string $step, array $data): string
    {
        switch ($step) {
            case 'route':
                if (!$data['available']) return 'No route company parameter';
                return $data['valid'] ? 'Valid route company' : 'Invalid route company';

            case 'session':
                if (!$data['company_id']) return 'No session company ID';
                if (!$data['valid_uuid']) return "Invalid UUID: {$data['company_id']}";
                if (!$data['user_has_access']) return "No access to: {$data['company_id']}";
                return "Valid session company: {$data['company_id']}";

            case 'database':
                if (!$data['preferred_company_id']) return 'No preferred company set';
                if (!$data['valid_uuid']) return "Invalid UUID: {$data['preferred_company_id']}";
                if (!$data['company_exists']) return "Company not found: {$data['preferred_company_id']}";
                if (!$data['user_has_access']) return "No access to: {$data['preferred_company_id']}";
                return "Valid preferred company: {$data['preferred_company_id']}";

            case 'first':
                if (!$data['available']) return 'No companies available';
                return "First company: {$data['company_name']} ({$data['company_id']})";

            default:
                return json_encode($data);
        }
    }

    /**
     * Display final resolution result.
     */
    private function displayFinalResolution(?array $company): void
    {
        $this->info('🎯 Final Resolution:');

        if (!$company) {
            $this->warn('  No active company resolved');
            return;
        }

        $this->table(
            ['Property', 'Value'],
            [
                ['Company ID', $company['id']],
                ['Company Name', $company['name']],
                ['Industry', $company['industry'] ?? 'N/A'],
                ['Currency', $company['base_currency'] ?? 'N/A'],
                ['User Role', $company['user_role'] ?? 'N/A'],
                ['Is Active', ($company['is_user_active'] ?? false) ? '✅ Yes' : '❌ No'],
                ['Can Switch To', ($company['can_switch_to'] ?? false) ? '✅ Yes' : '❌ No'],
            ]
        );
    }

    /**
     * Display user companies.
     */
    private function displayUserCompanies(array $companies): void
    {
        $this->info('🏢 User Companies:');

        if (empty($companies)) {
            $this->warn('  No companies found');
            return;
        }

        $this->table(
            ['Name', 'ID', 'Role', 'Active', 'Can Switch'],
            collect($companies)->map(function ($company) {
                return [
                    $company['name'],
                    substr($company['id'], 0, 8) . '...',
                    $company['user_role'] ?? 'N/A',
                    ($company['is_user_active'] ?? false) ? '✅' : '❌',
                    ($company['can_switch_to'] ?? false) ? '✅' : '❌',
                ];
            })->toArray()
        );
    }

    /**
     * Display cache information.
     */
    private function displayCacheInfo(array $cacheKeys): void
    {
        $this->info('🗄️  Cache Keys:');

        foreach ($cacheKeys as $key) {
            $exists = cache()->has($key);
            $status = $exists ? '✅ Exists' : '❌ Missing';
            $this->line("  {$status} {$key}");
        }
    }
}