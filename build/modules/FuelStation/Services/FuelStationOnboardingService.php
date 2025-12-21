<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
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
        $steps = [
            'accounts' => $this->checkAccountsSetup($companyId),
            'fuel_items' => $this->checkFuelItemsSetup($companyId),
            'tanks' => $this->checkTanksSetup($companyId),
            'pumps' => $this->checkPumpsSetup($companyId),
            'rates' => $this->checkRatesSetup($companyId),
            'initial_readings' => $this->checkInitialReadings($companyId),
        ];

        $completedSteps = array_filter($steps, fn($step) => $step['complete']);
        $totalSteps = count($steps);
        $completedCount = count($completedSteps);

        return [
            'is_complete' => $completedCount === $totalSteps,
            'progress_percentage' => round(($completedCount / $totalSteps) * 100),
            'completed_steps' => $completedCount,
            'total_steps' => $totalSteps,
            'steps' => $steps,
            'next_step' => $this->getNextStep($steps),
        ];
    }

    /**
     * Check if required accounts exist.
     */
    private function checkAccountsSetup(string $companyId): array
    {
        $missing = [];

        $hasCash = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('subtype', 'cash')
            ->where('is_active', true)
            ->exists();
        if (! $hasCash) {
            $missing[] = 'cash';
        }

        $hasBank = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('subtype', 'bank')
            ->where('is_active', true)
            ->exists();
        if (! $hasBank) {
            $missing[] = 'bank';
        }

        $hasInventory = Account::where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('subtype', 'inventory')
            ->where('is_active', true)
            ->exists();
        if (! $hasInventory) {
            $missing[] = 'inventory';
        }

        return [
            'name' => 'Chart of Accounts',
            'description' => 'Required accounts for fuel station operations',
            'complete' => empty($missing),
            'missing' => $missing,
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

        $categories = $fuelItems->pluck('fuel_category')->unique()->values();
        $expectedCategories = ['petrol', 'diesel', 'hi_octane'];
        $missing = array_diff($expectedCategories, $categories->toArray());

        return [
            'name' => 'Fuel Items',
            'description' => 'Inventory items for each fuel type',
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
     * Get the next incomplete step.
     */
    private function getNextStep(array $steps): ?string
    {
        foreach ($steps as $key => $step) {
            if (!$step['complete']) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Create required accounts if missing.
     */
    public function ensureRequiredAccounts(string $companyId): array
    {
        $created = [];

        $company = Company::findOrFail($companyId);
        $baseCurrency = strtoupper((string) ($company->base_currency ?: 'PKR'));

        $accounts = [
            [
                'code' => '1150',
                'name' => 'Parco Card Receivable',
                'type' => 'asset',
                'subtype' => 'other_current_asset',
                'normal_balance' => 'debit',
                'currency' => $baseCurrency,
                'is_system' => true,
            ],
            [
                'code' => '2100',
                'name' => 'Investor Deposits',
                'type' => 'liability',
                'subtype' => 'other_current_liability',
                'normal_balance' => 'credit',
                'currency' => $baseCurrency,
                'is_system' => true,
            ],
            [
                'code' => '2200',
                'name' => 'Customer Amanat Deposits',
                'type' => 'liability',
                'subtype' => 'other_current_liability',
                'normal_balance' => 'credit',
                'currency' => $baseCurrency,
                'is_system' => true,
            ],
            [
                'code' => '5100',
                'name' => 'Fuel Variance Expense',
                'type' => 'cogs',
                'subtype' => 'cogs',
                'normal_balance' => 'debit',
                'currency' => null,
                'is_system' => true,
            ],
            [
                'code' => '6100',
                'name' => 'Investor Commission Expense',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
                'currency' => null,
                'is_system' => true,
            ],
            [
                'code' => '6200',
                'name' => 'Parco Card Fees',
                'type' => 'expense',
                'subtype' => 'expense',
                'normal_balance' => 'debit',
                'currency' => null,
                'is_system' => true,
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
                ], $accountData));
                $created[] = $accountData['name'];
            }
        }

        return $created;
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
            ['name' => 'Hi-Octane', 'fuel_category' => 'hi_octane', 'sku' => 'FUEL-HOC'],
            ['name' => 'Diesel', 'fuel_category' => 'diesel', 'sku' => 'FUEL-DSL'],
        ];

        foreach ($fuelTypes as $fuel) {
            $exists = Item::where('company_id', $companyId)
                ->where('fuel_category', $fuel['fuel_category'])
                ->exists();

            if (!$exists) {
                Item::create([
                    'company_id' => $companyId,
                    'name' => $fuel['name'],
                    'sku' => $fuel['sku'],
                    'fuel_category' => $fuel['fuel_category'],
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
        $fuelItems = Item::where('company_id', $companyId)
            ->whereNotNull('fuel_category')
            ->get();

        $tanks = Warehouse::where('company_id', $companyId)
            ->where('warehouse_type', 'tank')
            ->with('linkedItem')
            ->get();

        $pumps = Pump::where('company_id', $companyId)
            ->with('tank')
            ->get();

        return [
            'status' => $status,
            'fuel_items' => $fuelItems,
            'tanks' => $tanks,
            'pumps' => $pumps,
            'suggested_rates' => $this->getSuggestedRates(),
        ];
    }

    /**
     * Get suggested rates (can be updated based on current OGRA rates).
     */
    private function getSuggestedRates(): array
    {
        // These are example rates - in production, could fetch from OGRA API
        return [
            'petrol' => ['purchase' => 248.50, 'sale' => 252.10],
            'hi_octane' => ['purchase' => 268.50, 'sale' => 272.82],
            'diesel' => ['purchase' => 253.50, 'sale' => 257.13],
        ];
    }
}
