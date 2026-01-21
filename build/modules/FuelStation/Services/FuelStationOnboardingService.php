<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Models\CompanyOnboarding;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class FuelStationOnboardingService
{
    /**
     * Get current onboarding status for a company.
     */
    public function getOnboardingStatus(string $companyId): array
    {
        $company = Company::find($companyId);
        $onboarding = CompanyOnboarding::where('company_id', $companyId)->first();

        $steps = [
            'company_identity' => $this->checkCompanyIdentitySetup($company, $onboarding),
            'fiscal_year' => $this->checkFiscalYearSetup($companyId, $onboarding),
            'bank_accounts' => $this->buildOnboardingStep($onboarding, 'bank-accounts', 'Bank Accounts', 'Business bank and cash accounts'),
            'default_accounts' => $this->buildOnboardingStep($onboarding, 'default-accounts', 'Default Accounts', 'System account mappings'),
            'partners' => $this->checkPartnersSetup($companyId),
            'employees' => $this->checkEmployeesSetup($companyId),
            'tax_settings' => $this->buildOnboardingStep($onboarding, 'tax-settings', 'Tax Settings', 'Sales tax/VAT configuration'),
            'numbering' => $this->buildOnboardingStep($onboarding, 'numbering', 'Numbering', 'Invoice and bill numbering'),
            'payment_terms' => $this->buildOnboardingStep($onboarding, 'payment-terms', 'Payment Terms', 'Default payment terms'),
            'fuel_items' => $this->checkFuelItemsSetup($companyId),
            'tanks' => $this->checkTanksSetup($companyId),
            'pumps' => $this->checkPumpsSetup($companyId),
            'rates' => $this->checkRatesSetup($companyId),
            'lubricants' => ['complete' => true], // Optional step, always allow proceeding
            'initial_stock' => ['complete' => true], // Optional step
            'opening_cash' => ['complete' => true], // Optional step
        ];

        $completedStepIds = array_keys(array_filter($steps, fn($step) => $step['complete']));
        $totalSteps = count($steps);
        $completedCount = count($completedStepIds);

        // Determine current step (first incomplete step)
        $currentStep = 'fiscal_year';
        foreach ($steps as $stepId => $stepData) {
            if (!$stepData['complete']) {
                $currentStep = $stepId;
                break;
            }
        }

        // If all complete, current step is 'complete'
        if ($completedCount === $totalSteps) {
            $currentStep = 'complete';
        }

        return [
            'is_complete' => $completedCount === $totalSteps,
            'progress_percentage' => round(($completedCount / $totalSteps) * 100),
            'completed_steps' => $completedStepIds,
            'total_steps' => $totalSteps,
            'current_step' => $currentStep,
            'steps' => $steps,
            'company_name' => $company?->name ?? '',
            'industry' => $company?->industry_code ?? 'fuel_station',
        ];
    }

    /**
     * Check if company identity is set up.
     */
    private function checkCompanyIdentitySetup(?Company $company, ?CompanyOnboarding $onboarding): array
    {
        $isComplete = $this->isOnboardingStepComplete($onboarding, 'company-identity');

        return [
            'name' => 'Company Identity',
            'description' => 'Business details and industry selection',
            'complete' => $isComplete || !empty($company?->industry_code),
        ];
    }

    private function checkFiscalYearSetup(string $companyId, ?CompanyOnboarding $onboarding): array
    {
        $isComplete = $this->isOnboardingStepComplete($onboarding, 'fiscal-year');
        $hasFiscalYear = false;

        try {
            $hasFiscalYear = FiscalYear::where('company_id', $companyId)->exists();
        } catch (\Throwable $e) {
            // Table might not exist yet
        }

        return [
            'name' => 'Fiscal Year',
            'description' => 'Accounting periods configuration',
            'complete' => $isComplete || $hasFiscalYear,
            'hidden' => $hasFiscalYear && !$isComplete,
        ];
    }

    private function buildOnboardingStep(?CompanyOnboarding $onboarding, string $step, string $name, string $description): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'complete' => $this->isOnboardingStepComplete($onboarding, $step),
        ];
    }

    private function isOnboardingStepComplete(?CompanyOnboarding $onboarding, string $step): bool
    {
        return $onboarding?->isStepCompleted($step) ?? false;
    }

    /**
     * Check if partners are set up.
     */
    private function checkPartnersSetup(string $companyId): array
    {
        // Partners are optional - use raw query to avoid any model issues
        $hasPartners = false;
        try {
            $hasPartners = DB::table('auth.partners')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->exists();
        } catch (\Throwable $e) {
            // Table might not exist or other issue - ignore
        }

        return [
            'name' => 'Partners',
            'description' => 'Business partners and profit sharing',
            'complete' => true, // Optional step
            'has_data' => $hasPartners,
        ];
    }

    /**
     * Check if employees are set up.
     */
    private function checkEmployeesSetup(string $companyId): array
    {
        // Employees are optional - use raw query to avoid any model issues
        $hasEmployees = false;
        try {
            $hasEmployees = DB::table('pay.employees')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->exists();
        } catch (\Throwable $e) {
            // Table might not exist or other issue - ignore
        }

        return [
            'name' => 'Employees',
            'description' => 'Pump attendants and staff',
            'complete' => true, // Optional step
            'has_data' => $hasEmployees,
        ];
    }

    /**
     * Check if fuel items are created.
     */
    private function checkFuelItemsSetup(string $companyId): array
    {
        $fuelItems = Item::where('company_id', $companyId)
            ->whereNotNull('fuel_category')
            ->get();

        $categories = $fuelItems->pluck('fuel_category')
            ->map(fn ($category) => $this->normalizeFuelCategory($category))
            ->unique()
            ->values();
        $expectedCategories = ['petrol', 'diesel', 'high_octane'];
        $missing = array_diff($expectedCategories, $categories->toArray());

        return [
            'name' => 'Products You Sell',
            'description' => 'Select fuels and products your station deals with',
            'complete' => $fuelItems->count() >= 1, // At least one fuel item
            'items_count' => $fuelItems->count(),
            'categories' => $categories,
            'suggested_missing' => $missing,
        ];
    }

    /**
     * Check if tanks are created.
     */
    private function checkTanksSetup(string $companyId): array
    {
        $tanks = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'tank')
            ->get();

        $tanksWithItems = $tanks->filter(fn($t) => $t->linked_item_id !== null);

        return [
            'name' => 'Storage Tanks',
            'description' => 'Physical tanks for fuel storage',
            'complete' => $tanks->count() >= 1 && $tanksWithItems->count() === $tanks->count(),
            'tanks_count' => $tanks->count(),
            'tanks_with_fuel_items' => $tanksWithItems->count(),
            'tanks_missing_item_link' => $tanks->count() - $tanksWithItems->count(),
        ];
    }

    /**
     * Check if pumps are created.
     */
    private function checkPumpsSetup(string $companyId): array
    {
        $pumps = Pump::where('company_id', $companyId)->get();
        $activePumps = $pumps->where('is_active', true);

        return [
            'name' => 'Fuel Pumps',
            'description' => 'Fuel dispensers linked to tanks',
            'complete' => $pumps->count() >= 1,
            'pumps_count' => $pumps->count(),
            'active_pumps' => $activePumps->count(),
        ];
    }

    /**
     * Check if rates are set.
     */
    private function checkRatesSetup(string $companyId): array
    {
        $fuelItems = Item::where('company_id', $companyId)
            ->whereNotNull('fuel_category')
            ->get();

        $itemsWithRates = 0;
        foreach ($fuelItems as $item) {
            $hasRate = RateChange::where('company_id', $companyId)
                ->where('item_id', $item->id)
                ->exists();
            if ($hasRate) {
                $itemsWithRates++;
            }
        }

        return [
            'name' => 'Fuel Rates',
            'description' => 'Current purchase and sale rates',
            'complete' => $fuelItems->count() > 0 && $itemsWithRates === $fuelItems->count(),
            'fuel_items_count' => $fuelItems->count(),
            'items_with_rates' => $itemsWithRates,
        ];
    }

    /**
     * Check if initial tank readings exist.
     */
    private function checkInitialReadings(string $companyId): array
    {
        $tanks = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'tank')
            ->get();

        $tanksWithReadings = 0;
        foreach ($tanks as $tank) {
            $hasReading = TankReading::where('company_id', $companyId)
                ->where('tank_id', $tank->id)
                ->exists();
            if ($hasReading) {
                $tanksWithReadings++;
            }
        }

        return [
            'name' => 'Initial Tank Readings',
            'description' => 'Opening dip measurements for variance tracking',
            'complete' => $tanks->count() > 0 && $tanksWithReadings === $tanks->count(),
            'tanks_count' => $tanks->count(),
            'tanks_with_readings' => $tanksWithReadings,
        ];
    }

    /**
     * Create required accounts if missing.
     * This creates all fuel station specific accounts needed for daily operations.
     */
    public function ensureRequiredAccounts(string $companyId): array
    {
        $created = [];

        $company = Company::findOrFail($companyId);
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        $accounts = [
            // ===== ASSETS =====
            [
                'code' => '1000',
                'name' => 'Operating Bank Account',
                'type' => 'asset',
                'subtype' => 'bank',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'description' => 'Primary bank account for daily deposits',
            ],
            [
                'code' => '1030',
                'name' => 'Vendor Card Clearing',
                'type' => 'asset',
                'subtype' => 'other_current_asset',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'description' => 'Vendor card sales pending settlement',
            ],
            [
                'code' => '1040',
                'name' => 'Card Receipts Clearing',
                'type' => 'asset',
                'subtype' => 'other_current_asset',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'description' => 'Debit/credit card sales pending bank settlement',
            ],
            [
                'code' => '1050',
                'name' => 'Cash on Hand',
                'type' => 'asset',
                'subtype' => 'cash',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'description' => 'Physical cash in the station safe/drawer',
            ],
            [
                'code' => '1100',
                'name' => 'Accounts Receivable',
                'type' => 'asset',
                'subtype' => 'accounts_receivable',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'is_system' => true,
                'system_identifier' => 'ar_control',
            ],
            [
                'code' => '1150',
                'name' => 'Employee Advances',
                'type' => 'asset',
                'subtype' => 'other_current_asset',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'description' => 'Salary advances given to employees, recoverable from payroll',
            ],
            [
                'code' => '1200',
                'name' => 'Fuel Inventory',
                'type' => 'asset',
                'subtype' => 'inventory',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'description' => 'Value of fuel in tanks',
            ],
            [
                'code' => '1210',
                'name' => 'Lubricants Inventory',
                'type' => 'asset',
                'subtype' => 'inventory',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'description' => 'Value of motor oils and lubricants',
            ],

            // ===== LIABILITIES =====
            [
                'code' => '2100',
                'name' => 'Accounts Payable – Fuel Supplier',
                'type' => 'liability',
                'subtype' => 'accounts_payable',
                'normal_balance' => 'credit',
                'currency' => $baseCurrency,
                'is_system' => true,
                'system_identifier' => 'ap_control',
                'description' => 'Amounts owed to fuel suppliers for purchases',
            ],
            [
                'code' => '2200',
                'name' => 'Amanat Deposits',
                'type' => 'liability',
                'subtype' => 'other_current_liability',
                'normal_balance' => 'credit',
                'currency' => $baseCurrency,
                'description' => 'Customer trust deposits (prepaid fuel accounts)',
            ],
            [
                'code' => '2210',
                'name' => 'Investor Deposits',
                'type' => 'liability',
                'subtype' => 'other_current_liability',
                'normal_balance' => 'credit',
                'currency' => $baseCurrency,
                'description' => 'Capital deposits from investors/partners',
            ],

            // ===== EQUITY =====
            [
                'code' => '3100',
                'name' => 'Retained Earnings',
                'type' => 'equity',
                'subtype' => 'retained_earnings',
                'normal_balance' => 'credit',
                'is_system' => true,
                'system_identifier' => 'retained_earnings',
            ],
            [
                'code' => '3200',
                'name' => 'Partner Drawings',
                'type' => 'equity',
                'subtype' => 'equity',
                'normal_balance' => 'debit',
                'is_contra' => true,
                'description' => 'Partner withdrawals against their capital/profit share',
            ],

            // ===== REVENUE =====
            [
                'code' => '4100',
                'name' => 'Fuel Sales',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'credit',
                'is_system' => true,
                'system_identifier' => 'primary_revenue',
            ],
            [
                'code' => '4110',
                'name' => 'Shop Sales',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'credit',
                'description' => 'Convenience store / non-fuel sales',
            ],
            [
                'code' => '4200',
                'name' => 'Lubricant Sales',
                'type' => 'revenue',
                'subtype' => 'revenue',
                'normal_balance' => 'credit',
                'description' => 'Motor oil and lubricant sales',
            ],
            [
                'code' => '4300',
                'name' => 'Discounts Received',
                'type' => 'other_income',
                'subtype' => 'other_income',
                'normal_balance' => 'credit',
                'description' => 'Supplier discounts applied to bills',
            ],

            // ===== COST OF GOODS SOLD =====
            [
                'code' => '5100',
                'name' => 'Cost of Goods – Fuel',
                'type' => 'cogs',
                'subtype' => 'cogs',
                'normal_balance' => 'debit',
                'is_system' => true,
                'system_identifier' => 'primary_cogs',
            ],
            [
                'code' => '5200',
                'name' => 'Cost of Goods – Lubricants',
                'type' => 'cogs',
                'subtype' => 'cogs',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '5900',
                'name' => 'Fuel Shrinkage Loss',
                'type' => 'cogs',
                'subtype' => 'cogs',
                'normal_balance' => 'debit',
                'description' => 'Petrol evaporation and measurement variance losses',
            ],

            // ===== EXPENSES =====
            [
                'code' => '6100',
                'name' => 'Investor Commission Expense',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
                'description' => 'Commission paid to investors based on profit share',
            ],
            [
                'code' => '6150',
                'name' => 'Salaries & Wages',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '6180',
                'name' => 'Cash Short/Over',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
                'description' => 'Daily cash variance (shortage or overage)',
            ],
            [
                'code' => '6200',
                'name' => 'Card Processing Fees',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
                'description' => 'Fees charged by vendors and banks for card transactions',
            ],
            [
                'code' => '6300',
                'name' => 'Utilities',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '6400',
                'name' => 'Pump Maintenance',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
            ],
            [
                'code' => '6500',
                'name' => 'General Expenses',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
                'description' => 'Miscellaneous operating expenses',
            ],
        ];

        foreach ($accounts as $accountData) {
            $exists = Account::where('company_id', $companyId)
                ->where('code', $accountData['code'])
                ->exists();

            if (!$exists) {
                Account::create(array_merge([
                    'company_id' => $companyId,
                    'is_active' => true,
                    'created_by_user_id' => $company->created_by_user_id,
                    'currency' => $accountData['currency'] ?? null,
                ], $accountData));
                $created[] = $accountData['name'];
            }
        }

        // Update company default account references
        $this->updateCompanyDefaultAccounts($company);

        return $created;
    }

    /**
     * Update company with default account IDs for system use.
     */
    private function updateCompanyDefaultAccounts(Company $company): void
    {
        $updates = [];

        // AR Control
        $arAccount = Account::where('company_id', $company->id)
            ->where('system_identifier', 'ar_control')
            ->first();
        if ($arAccount && !$company->ar_account_id) {
            $updates['ar_account_id'] = $arAccount->id;
        }

        // AP Control
        $apAccount = Account::where('company_id', $company->id)
            ->where('system_identifier', 'ap_control')
            ->first();
        if ($apAccount && !$company->ap_account_id) {
            $updates['ap_account_id'] = $apAccount->id;
        }

        // Primary Revenue (Fuel Sales)
        $incomeAccount = Account::where('company_id', $company->id)
            ->where('system_identifier', 'primary_revenue')
            ->first();
        if ($incomeAccount && !$company->income_account_id) {
            $updates['income_account_id'] = $incomeAccount->id;
        }

        // Default Expense (General Expenses preferred)
        $expenseAccount = Account::where('company_id', $company->id)
            ->where('type', 'expense')
            ->orderByRaw("CASE WHEN code = '6500' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->first();
        if ($expenseAccount && !$company->expense_account_id) {
            $updates['expense_account_id'] = $expenseAccount->id;
        }

        // Default Bank/Cash (prefer bank if available)
        $bankAccount = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->orderByRaw("CASE WHEN subtype = 'bank' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->first();
        if ($bankAccount && !$company->bank_account_id) {
            $updates['bank_account_id'] = $bankAccount->id;
        }

        // Retained Earnings
        $retainedEarnings = Account::where('company_id', $company->id)
            ->where('system_identifier', 'retained_earnings')
            ->first();
        if ($retainedEarnings && !$company->retained_earnings_account_id) {
            $updates['retained_earnings_account_id'] = $retainedEarnings->id;
        }

        if (!empty($updates)) {
            $company->update($updates);
        }
    }

    /**
     * Quick setup: Create default fuel items.
     */
    public function createDefaultFuelItems(string $companyId): array
    {
        $created = [];

        $company = Company::findOrFail($companyId);
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        $fuelTypes = [
            ['name' => 'Petrol', 'fuel_category' => 'petrol', 'sku' => 'FUEL-PET'],
            ['name' => 'Hi-Octane', 'fuel_category' => 'high_octane', 'sku' => 'FUEL-HOC'],
            ['name' => 'Diesel', 'fuel_category' => 'diesel', 'sku' => 'FUEL-DSL'],
        ];

        foreach ($fuelTypes as $fuel) {
            $normalizedCategory = $this->normalizeFuelCategory($fuel['fuel_category']);
            $existsQuery = Item::where('company_id', $companyId);
            if ($normalizedCategory === 'high_octane') {
                $existsQuery->whereIn('fuel_category', ['high_octane', 'hi_octane']);
            } else {
                $existsQuery->where('fuel_category', $normalizedCategory);
            }
            $exists = $existsQuery->exists();

            if (!$exists) {
                Item::create([
                    'company_id' => $companyId,
                    'name' => $fuel['name'],
                    'sku' => $fuel['sku'],
                    'fuel_category' => $normalizedCategory,
                    'item_type' => 'product',
                    'is_active' => true,
                    'track_inventory' => true,
                    'unit_of_measure' => 'liters',
                    'currency' => $baseCurrency,
                    'created_by_user_id' => $company->created_by_user_id,
                ]);
                $created[] = $fuel['name'];
            }
        }

        return $created;
    }

    /**
     * Get onboarding wizard data.
     */
    public function getWizardData(string $companyId): array
    {
        $status = $this->getOnboardingStatus($companyId);

        // Get existing data for forms
        $fuelItems = collect();
        try {
            $fuelItems = Item::where('company_id', $companyId)
                ->whereNotNull('fuel_category')
                ->get();
        } catch (\Throwable $e) {
            // Ignore
        }

        $tanks = collect();
        try {
            $tanks = Warehouse::where('company_id', $companyId)
                ->where('warehouse_type', 'tank')
                ->with('linkedItem')
                ->get();
        } catch (\Throwable $e) {
            // Ignore
        }

        $pumps = collect();
        try {
            $pumps = Pump::where('company_id', $companyId)
                ->with('tank')
                ->get();
        } catch (\Throwable $e) {
            // Ignore
        }

        // Return status fields directly (flattened) for Vue compatibility
        return array_merge($status, [
            'fuel_items' => $fuelItems,
            'tanks' => $tanks,
            'pumps' => $pumps,
            'suggested_rates' => $this->getSuggestedRates(),
        ]);
    }

    /**
     * Get suggested rates (can be updated based on current OGRA rates).
     */
    private function getSuggestedRates(): array
    {
        // These are example rates - in production, could fetch from OGRA API
        return [
            'petrol' => ['purchase' => 248.50, 'sale' => 252.10],
            'high_octane' => ['purchase' => 268.50, 'sale' => 272.82],
            'diesel' => ['purchase' => 253.50, 'sale' => 257.13],
        ];
    }

    private function normalizeFuelCategory(?string $category): ?string
    {
        if ($category === null) {
            return null;
        }

        return $category === 'hi_octane' ? 'high_octane' : $category;
    }
}
