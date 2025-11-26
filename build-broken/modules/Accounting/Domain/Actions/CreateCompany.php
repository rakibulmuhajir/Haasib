<?php

namespace Modules\Accounting\Domain\Actions;

use App\Services\CurrencyService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\User;

class CreateCompany
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    /**
     * Create a new company.
     *
     * @throws ValidationException
     */
    public function execute(array $data, ?User $createdBy = null): Company
    {
        // Validate input
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:pgsql.auth.companies,slug'],
            'country' => ['sometimes', 'string', 'max:2'],
            'country_id' => ['sometimes', 'uuid', 'exists:countries,id'],
            'base_currency' => ['sometimes', 'string', 'max:3', 'size:3'],
            'currency_id' => ['sometimes', 'uuid', 'exists:currencies,id'],
            'exchange_rate_id' => ['sometimes', 'integer', 'exists:exchange_rates,id'],
            'language' => ['sometimes', 'string', 'max:10'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'settings' => ['sometimes', 'array'],
            'created_by_user_id' => ['sometimes', 'uuid', 'exists:pgsql.auth.users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Set creator if not provided
        if (! isset($validated['created_by_user_id']) && $createdBy) {
            $validated['created_by_user_id'] = $createdBy->id;
        }

        // Set defaults
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['language'] = $validated['language'] ?? 'en';
        $validated['locale'] = $validated['locale'] ?? 'en_US';
        $validated['base_currency'] = $validated['base_currency'] ?? 'USD';

        // If country is provided but not country_id, try to find country
        if (isset($validated['country']) && ! isset($validated['country_id'])) {
            $country = \App\Models\Country::where('code', $validated['country'])->first();
            if ($country) {
                $validated['country_id'] = $country->id;
            }
        }

        // If base_currency is provided but not currency_id, try to find currency
        if (isset($validated['base_currency']) && ! isset($validated['currency_id'])) {
            $currency = \App\Models\Currency::where('code', $validated['base_currency'])->first();
            if ($currency) {
                $validated['currency_id'] = $currency->id;
            }
        }

        // Create company
        $company = Company::create($validated);

        // Set up multi-currency system with base currency
        $this->setupCompanyCurrency($company);

        // Enable core modules by default
        $this->enableCoreModules($company, $createdBy);

        // Log audit entry
        $company->auditEntries()->create([
            'action' => 'company_created',
            'entity_type' => 'company',
            'entity_id' => $company->id,
            'user_id' => $validated['created_by_user_id'] ?? $createdBy?->id,
            'new_values' => [
                'name' => $company->name,
                'country' => $company->country,
                'base_currency' => $company->base_currency,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $company;
    }

    /**
     * Create a company and automatically add user as owner.
     *
     * @throws ValidationException
     */
    public function executeWithOwner(array $companyData, User $owner): Company
    {
        // Create company
        $company = $this->execute($companyData, $owner);

        // Add owner
        $company->addUser($owner, 'owner');

        return $company;
    }

    /**
     * Enable core modules for a new company.
     */
    protected function enableCoreModules(Company $company, ?User $enabledBy): void
    {
        if (! $enabledBy && ! auth()->check()) {
            return; // Skip if no user is available
        }

        $coreModules = \Modules\Accounting\Models\Module::core()->active()->get();
        $actingUser = $enabledBy ?? auth()->user();

        foreach ($coreModules as $module) {
            // Check if module has dependencies
            $missingDeps = $module->checkDependencies();
            if (! empty($missingDeps)) {
                continue; // Skip modules with missing dependencies
            }

            $company->enableModule($module, $actingUser);
        }
    }

    /**
     * Validate company creation data without creating a company.
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:pgsql.auth.companies,slug'],
            'country' => ['sometimes', 'string', 'max:2'],
            'base_currency' => ['sometimes', 'string', 'max:3', 'size:3'],
            'language' => ['sometimes', 'string', 'max:10'],
            'locale' => ['sometimes', 'string', 'max:10'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Generate a unique slug from company name.
     */
    public function generateSlug(string $name): string
    {
        $slug = \Illuminate\Support\Str::slug($name);
        $count = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = \Illuminate\Support\Str::slug($name).'-'.$count++;
        }

        return $slug;
    }

    /**
     * Check if company name is available.
     */
    public function isNameAvailable(string $name): bool
    {
        return ! Company::where('name', $name)->exists();
    }

    /**
     * Create company with default settings.
     *
     * @throws ValidationException
     */
    public function createWithDefaults(
        string $name,
        string $country,
        string $currency,
        User $owner
    ): Company {
        $data = [
            'name' => $name,
            'country' => $country,
            'base_currency' => $currency,
            'language' => 'en',
            'locale' => 'en_US',
            'settings' => [
                'fiscal_year_start' => '01-01',
                'timezone' => 'UTC',
                'date_format' => 'Y-m-d',
                'number_format' => 'en_US',
            ],
        ];

        return $this->executeWithOwner($data, $owner);
    }

    /**
     * Set up multi-currency system for a new company.
     */
    protected function setupCompanyCurrency(Company $company): void
    {
        if (!$company->base_currency) {
            return; // No base currency set, skip setup
        }

        try {
            // Set up the base currency in the multi-currency system
            $this->currencyService->setupBaseCurrency(
                $company->id,
                $company->base_currency
            );
        } catch (\Exception $e) {
            // Log the error but don't fail company creation
            \Log::warning('Failed to setup currency for new company', [
                'company_id' => $company->id,
                'base_currency' => $company->base_currency,
                'error' => $e->getMessage()
            ]);
        }
    }
}
