<?php

namespace Modules\Accounting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\User;
use Modules\Accounting\Services\CompanyService;
use Modules\Accounting\Services\UserService;

class CompanyManagement extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:company
                            {action : The action to perform (list, create, deactivate, reactivate, stats, transfer)}
                            {--id= : Company ID}
                            {--name= : Company name (for creation)}
                            {--slug= : Company slug (for creation)}
                            {--country=US : Company country code}
                            {--currency=USD : Company currency}
                            {--owner= : Owner email address}
                            {--new-owner= : New owner email address (for transfer)}
                            {--force : Force action without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage accounting companies';

    public function __construct(
        private CompanyService $companyService,
        private UserService $userService
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
            'list' => $this->listCompanies(),
            'create' => $this->createCompany(),
            'deactivate' => $this->deactivateCompany(),
            'reactivate' => $this->reactivateCompany(),
            'stats' => $this->showStats(),
            'transfer' => $this->transferOwnership(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * List all companies.
     */
    private function listCompanies(): int
    {
        $companies = Company::withCount('users')->get();

        if ($companies->isEmpty()) {
            $this->info('No companies found.');

            return 0;
        }

        $this->info('Companies:');
        $this->info(str_repeat('-', 100));

        $headers = ['ID', 'Name', 'Slug', 'Country', 'Currency', 'Users', 'Status', 'Created'];
        $rows = [];

        foreach ($companies as $company) {
            $rows[] = [
                $company->id,
                $company->name,
                $company->slug,
                $company->country,
                $company->currency,
                $company->users_count,
                $company->is_active ? 'Active' : 'Inactive',
                $company->created_at->format('Y-m-d'),
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    /**
     * Create a new company.
     */
    private function createCompany(): int
    {
        $name = $this->option('name');
        if (! $name) {
            $name = $this->ask('Enter company name');
        }

        if (empty($name)) {
            $this->error('Company name is required.');

            return 1;
        }

        $slug = $this->option('slug') ?: $this->generateSlug($name);
        $country = $this->option('country');
        $currency = $this->option('currency');

        // Check if slug is available
        if (! $this->companyService->isSlugAvailable($slug)) {
            $this->error("Slug '{$slug}' is already taken.");

            return 1;
        }

        // Get owner
        $ownerEmail = $this->option('owner');
        if (! $ownerEmail) {
            $ownerEmail = $this->ask('Enter owner email address');
        }

        if (empty($ownerEmail)) {
            $this->error('Owner email is required.');

            return 1;
        }

        $owner = User::where('email', $ownerEmail)->first();
        if (! $owner) {
            if (! $this->confirm("User '{$ownerEmail}' not found. Create new user?")) {
                $this->error('Owner user is required.');

                return 1;
            }

            $name = $this->ask('Enter user name', $ownerEmail);
            $password = $this->secret('Enter temporary password');

            try {
                $userData = [
                    'name' => $name,
                    'email' => $ownerEmail,
                    'password' => $password,
                    'password_confirmation' => $password,
                ];

                [$owner, $_] = $this->userService->registerUser($userData);
                $this->info("Created user: {$owner->name} ({$owner->email})");
            } catch (\Exception $e) {
                $this->error("Failed to create user: {$e->getMessage()}");

                return 1;
            }
        }

        // Create company
        try {
            $companyData = [
                'name' => $name,
                'slug' => $slug,
                'country' => $country,
                'currency' => $currency,
            ];

            $company = $this->companyService->createCompany($companyData, $owner);

            $this->info("✓ Created company: {$company->name} ({$company->slug})");
            $this->info("  ID: {$company->id}");
            $this->info("  Owner: {$owner->name} ({$owner->email})");
            $this->info("  Country: {$company->country}");
            $this->info("  Currency: {$company->currency}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to create company: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Deactivate a company.
     */
    private function deactivateCompany(): int
    {
        $companyId = $this->option('id');
        if (! $companyId) {
            $companyId = $this->ask('Enter company ID');
        }

        if (empty($companyId)) {
            $this->error('Company ID is required.');

            return 1;
        }

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company with ID '{$companyId}' not found.");

            return 1;
        }

        if (! $company->is_active) {
            $this->warn("Company '{$company->name}' is already inactive.");

            return 0;
        }

        $this->info("Company: {$company->name} ({$company->slug})");
        $this->info('Status: Active');
        $this->info("Users: {$company->users()->count()}");

        if (! $this->option('force')) {
            $this->warn("\nDeactivating this company will:");
            $this->warn('- Deactivate all users in the company');
            $this->warn('- Disable access to all company data');
            $this->warn('- Keep all data for potential reactivation');

            if (! $this->confirm("\nDo you want to continue?")) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        try {
            $this->companyService->deactivateCompany($company);

            $this->info("✓ Deactivated company: {$company->name}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to deactivate company: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Reactivate a company.
     */
    private function reactivateCompany(): int
    {
        $companyId = $this->option('id');
        if (! $companyId) {
            $companyId = $this->ask('Enter company ID');
        }

        if (empty($companyId)) {
            $this->error('Company ID is required.');

            return 1;
        }

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company with ID '{$companyId}' not found.");

            return 1;
        }

        if ($company->is_active) {
            $this->warn("Company '{$company->name}' is already active.");

            return 0;
        }

        $this->info("Company: {$company->name} ({$company->slug})");
        $this->info('Status: Inactive');

        if (! $this->confirm('Do you want to reactivate this company?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            $this->companyService->reactivateCompany($company);

            $this->info("✓ Reactivated company: {$company->name}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to reactivate company: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Show company statistics.
     */
    private function showStats(): int
    {
        $companyId = $this->option('id');
        if (! $companyId) {
            // List companies for selection
            $companies = Company::all();
            if ($companies->isEmpty()) {
                $this->info('No companies found.');

                return 0;
            }

            $choices = $companies->mapWithKeys(function ($company) {
                return [$company->id => "{$company->name} ({$company->slug})"];
            })->toArray();

            $selectedId = $this->choice('Select a company', $choices);
            $companyId = array_search($selectedId, $choices);
        }

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company with ID '{$companyId}' not found.");

            return 1;
        }

        $stats = $this->companyService->getCompanyStatistics($company);

        $this->info("Company Statistics: {$company->name}");
        $this->info(str_repeat('-', 50));
        $this->info("ID: {$company->id}");
        $this->info("Slug: {$company->slug}");
        $this->info("Country: {$company->country}");
        $this->info("Currency: {$company->currency}");
        $this->info('Status: '.($company->is_active ? 'Active' : 'Inactive'));
        $this->info('Created: '.$company->created_at->format('Y-m-d H:i:s'));

        $this->info("\nUsers:");
        $this->info("  Total: {$stats['total_users']}");
        $this->info("  Active: {$stats['active_users']}");
        $this->info("  Owners: {$stats['owners']}");
        $this->info("  Admins: {$stats['admins']}");

        $this->info("\nModules:");
        $this->info("  Enabled: {$stats['enabled_modules']}");
        $this->info("  Total: {$stats['total_modules']}");

        if ($stats['last_activity']) {
            $this->info("\nLast Activity: ".$stats['last_activity']->format('Y-m-d H:i:s'));
        }

        // Show recent users
        $recentUsers = $company->companyUsers()->with('user')->latest()->limit(5)->get();
        if (! $recentUsers->isEmpty()) {
            $this->info("\nRecent Users:");
            foreach ($recentUsers as $companyUser) {
                $status = $companyUser->is_active ? 'Active' : 'Inactive';
                $this->info("  - {$companyUser->user->name} ({$companyUser->user->email}) - {$companyUser->role} - {$status}");
            }
        }

        return 0;
    }

    /**
     * Transfer company ownership.
     */
    private function transferOwnership(): int
    {
        $companyId = $this->option('id');
        if (! $companyId) {
            $companyId = $this->ask('Enter company ID');
        }

        if (empty($companyId)) {
            $this->error('Company ID is required.');

            return 1;
        }

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("Company with ID '{$companyId}' not found.");

            return 1;
        }

        $currentOwner = $company->owner;
        if (! $currentOwner) {
            $this->error('Company has no owner.');

            return 1;
        }

        $this->info("Company: {$company->name} ({$company->slug})");
        $this->info("Current Owner: {$currentOwner->name} ({$currentOwner->email})");

        $newOwnerEmail = $this->option('new-owner');
        if (! $newOwnerEmail) {
            $newOwnerEmail = $this->ask('Enter new owner email address');
        }

        if (empty($newOwnerEmail)) {
            $this->error('New owner email is required.');

            return 1;
        }

        if ($newOwnerEmail === $currentOwner->email) {
            $this->error('New owner cannot be the same as current owner.');

            return 1;
        }

        $newOwner = User::where('email', $newOwnerEmail)->first();
        if (! $newOwner) {
            $this->error("User '{$newOwnerEmail}' not found.");

            return 1;
        }

        if (! $newOwner->hasCompanyRole($company, 'member')) {
            $this->warn("User '{$newOwnerEmail}' is not a member of this company.");
            if (! $this->confirm('Add them as a member and transfer ownership?')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $this->info("\nNew Owner: {$newOwner->name} ({$newOwner->email})");
        $this->info("\nThis will:");
        $this->info("- Transfer ownership from {$currentOwner->email} to {$newOwner->email}");
        $this->info("- Change current owner role to 'admin'");

        if (! $this->confirm("\nDo you want to continue?")) {
            $this->info('Operation cancelled.');

            return 0;
        }

        try {
            $this->companyService->transferOwnership($company, $newOwner);

            $this->info("✓ Transferred ownership of '{$company->name}' to {$newOwner->name}");
            $this->info("  Previous owner ({$currentOwner->email}) is now an admin");

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to transfer ownership: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Generate slug from name.
     */
    private function generateSlug(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    }
}
