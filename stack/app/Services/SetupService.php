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

class SetupService
{
    private const CACHE_KEY_INITIALIZED = 'system:initialized';

    private const CACHE_KEY_STATUS = 'system:status';

    private const CACHE_TTL_STATUS = 300; // 5 minutes

    /**
     * Check if the system has been initialized.
     */
    public function isInitialized(): bool
    {
        return Cache::remember(self::CACHE_KEY_INITIALIZED, self::CACHE_TTL_STATUS, function () {
            // System is considered initialized if there are users OR companies
            // Modules alone don't constitute initialization
            return User::exists() || Company::exists();
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
        return DB::transaction(function () use ($userData, $companyData) {
            $user = $this->createSystemOwnerCommand($userData);
            $company = $this->createCompanyCommand($companyData);
            $this->assignSystemOwnerToCompanies($user, [$company]);
            $modulesCreated = $this->createDefaultModules();

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

        return DB::transaction(function () use ($userData, $companiesData) {
            $systemOwner = $this->createSystemOwner($userData);
            $companies = $this->createCompanies($companiesData);
            $this->assignSystemOwnerToCompanies($systemOwner, $companies);
            $modulesCreated = $this->createDefaultModules();

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
