<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SetupService extends BaseService
{
    private const CACHE_KEY_INITIALIZED = 'system:initialized';

    private const CACHE_KEY_STATUS = 'system:status';

    private const CACHE_TTL_STATUS = 300; // 5 minutes

    public function __construct(ServiceContext $context)
    {
        parent::__construct($context);
    }

    /**
     * Check if the system has been initialized.
     */
    public function isInitialized(): bool
    {
        return Cache::remember(self::CACHE_KEY_INITIALIZED, self::CACHE_TTL_STATUS, function () {
            // System is considered initialized if there are users OR companies
            // Modules alone don't constitute initialization
            // Use raw DB queries to bypass RLS policies completely
            try {
                // Temporarily disable RLS for this check
                DB::statement('SET ROW LEVEL SECURITY OFF');

                $userCount = DB::table('auth.users')->count();
                $companyCount = DB::table('auth.companies')->count();

                // Re-enable RLS
                DB::statement('SET ROW LEVEL SECURITY ON');

                return $userCount > 0 || $companyCount > 0;
            } catch (\Exception $e) {
                // Ensure RLS is re-enabled even if query fails
                try {
                    DB::statement('SET ROW LEVEL SECURITY ON');
                } catch (\Exception $e2) {
                    // Ignore RLS re-enable error
                }

                // As last resort, check if we can access tables without RLS
                try {
                    $result = DB::selectOne("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = 'auth' AND table_name IN ('users', 'companies')");

                    return $result->count > 0; // Tables exist but might be empty
                } catch (\Exception $e3) {
                    return false; // Assume not initialized if we can't check anything
                }
            }
        });
    }

    /**
     * Check if the system has been initialized (alias method for tests).
     */
    public function isSystemInitialized(): bool
    {
        return $this->isInitialized();
    }

    /**
     * Get detailed initialization status.
     */
    public function getStatus(): array
    {
        return Cache::remember(self::CACHE_KEY_STATUS, self::CACHE_TTL_STATUS, function () {
            $hasAnyUser = User::exists();
            $companyCount = Company::count();
            $moduleCount = Module::where('is_active', true)->count();

            return [
                'initialized' => $this->isInitialized(),
                'requirements' => [
                    'system_owner' => $hasAnyUser,
                    'companies' => $companyCount,
                    'modules' => $moduleCount,
                    'min_companies' => 1,
                    'min_modules' => 1,
                ],
                'timestamp' => now()->toISOString(),
            ];
        });
    }

    /**
     * Initialize the system with user and company (for command).
     */
    public function initializeSystem(array $userData, array $companyData): array
    {
        return $this->executeInTransaction(function () use ($userData, $companyData) {
            $user = $this->createSystemOwnerCommand($userData);
            $company = $this->createCompanyCommand($companyData);
            $this->assignSystemOwnerToCompanies($user, [$company]);
            $modulesCreated = $this->createDefaultModules();

            // Log system initialization
            $this->audit('system.initialized', [
                'system_owner_id' => $user->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'modules_created' => $modulesCreated,
                'initialized_by_user_id' => $this->getUserId(),
                'request_id' => $this->getRequestId(),
            ]);

            // Clear caches
            Cache::forget(self::CACHE_KEY_INITIALIZED);
            Cache::forget(self::CACHE_KEY_STATUS);

            return [$user, $company];
        });
    }

    /**
     * Initialize the system with user and companies.
     */
    public function initialize(array $userData, array $companiesData): array
    {
        if ($this->isInitialized()) {
            throw new \Exception('System is already initialized');
        }

        // Validate input data
        $this->validateInitializationData($userData, $companiesData);

        return $this->executeInTransaction(function () use ($userData, $companiesData) {
            $systemOwner = $this->createSystemOwner($userData);
            $companies = $this->createCompanies($companiesData);
            $this->assignSystemOwnerToCompanies($systemOwner, $companies);
            $modulesCreated = $this->createDefaultModules();

            // Log system initialization
            $this->audit('system.initialized_multi_company', [
                'system_owner_id' => $systemOwner->id,
                'companies_count' => count($companies),
                'companies_created' => $companies,
                'modules_created' => $modulesCreated,
                'initialized_by_user_id' => $this->getUserId(),
                'request_id' => $this->getRequestId(),
            ]);

            // Clear caches
            Cache::forget(self::CACHE_KEY_INITIALIZED);
            Cache::forget(self::CACHE_KEY_STATUS);

            return [
                'success' => true,
                'system_owner' => $systemOwner->fresh()->toArray(),
                'companies_created' => count($companies),
                'modules_enabled' => $modulesCreated,
            ];
        });
    }

    /**
     * Validate initialization data.
     */
    private function validateInitializationData(array $userData, array $companiesData): void
    {
        $validator = Validator::make([
            'user' => $userData,
            'companies' => $companiesData,
        ], [
            'user.name' => 'required|string|min:3|max:255',
            'user.email' => 'required|email|unique:users,email',
            'user.username' => 'required|string|min:3|max:255|unique:users,username',
            'user.password' => 'required|string|min:8',
            'user.system_role' => 'sometimes|in:system_owner,company_owner,manager,employee',
            'companies' => 'required|array|min:1',
            'companies.*.name' => 'required|string|min:3|max:255',
            'companies.*.industry' => 'required|string|in:technology,hospitality,retail,professional_services,other',
            'companies.*.base_currency' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Create the system owner user (for command).
     */
    private function createSystemOwnerCommand(array $userData): User
    {
        return User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'username' => $this->generateUsername($userData['email']),
            'password' => Hash::make($userData['password']),
            'system_role' => 'superadmin',
            'is_active' => true,
        ]);
    }

    /**
     * Create company (for command).
     */
    private function createCompanyCommand(array $companyData): Company
    {
        return Company::create([
            'name' => $companyData['name'],
            'slug' => $companyData['slug'],
            'base_currency' => $companyData['base_currency'],
            'is_active' => true,
        ]);
    }

    /**
     * Create the system owner user.
     */
    private function createSystemOwner(array $userData): User
    {
        return User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'username' => $userData['username'],
            'password' => Hash::make($userData['password']),
            'system_role' => $userData['system_role'] ?? 'system_owner',
            'is_active' => true,
        ]);
    }

    /**
     * Create companies.
     */
    private function createCompanies(array $companiesData): array
    {
        $companies = [];
        foreach ($companiesData as $companyData) {
            $companies[] = Company::create([
                'name' => $companyData['name'],
                'industry' => $companyData['industry'],
                'base_currency' => $companyData['base_currency'],
                'fiscal_year_start' => $companyData['fiscal_year_start'] ?? '2024-01-01',
                'is_active' => true,
            ]);
        }

        return $companies;
    }

    /**
     * Assign system owner to all companies.
     */
    private function assignSystemOwnerToCompanies(User $systemOwner, array $companies): void
    {
        foreach ($companies as $company) {
            $company->users()->attach($systemOwner->id, [
                'role' => 'owner',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Create default modules.
     */
    private function createDefaultModules(): int
    {
        $modules = [
            [
                'key' => 'accounting',
                'name' => 'Accounting',
                'version' => '1.0.0',
                'is_enabled' => true,
                'is_active' => true,
                'category' => 'general',
            ],
        ];

        $count = 0;
        foreach ($modules as $moduleData) {
            $module = Module::firstOrCreate(
                ['key' => $moduleData['key']],
                $moduleData
            );

            if ($module->wasRecentlyCreated) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Generate a username from email.
     */
    private function generateUsername(string $email): string
    {
        return explode('@', $email)[0];
    }

    /**
     * Clear system caches.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_INITIALIZED);
        Cache::forget(self::CACHE_KEY_STATUS);
    }
}
