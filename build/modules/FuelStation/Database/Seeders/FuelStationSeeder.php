<?php

namespace App\Modules\FuelStation\Database\Seeders;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Models\Investor;
use App\Modules\FuelStation\Models\InvestorLot;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

class FuelStationSeeder extends Seeder
{
    /**
     * Seed fuel station demo data for a company.
     */
    public function run(?string $companyId = null): void
    {
        $company = $companyId
            ? Company::find($companyId)
            : Company::where('industry', 'fuel_station')->first();

        if (!$company) {
            $this->command?->warn('No fuel station company found. Skipping FuelStationSeeder.');
            return;
        }

        $this->command?->info("Seeding fuel station data for: {$company->name}");

        // Create fuel items
        $fuelItems = $this->createFuelItems($company);

        // Create tanks (warehouses)
        $tanks = $this->createTanks($company, $fuelItems);

        // Create pumps
        $this->createPumps($company, $tanks);

        // Create rate changes (current prices)
        $this->createRateChanges($company, $fuelItems);

        // Create fuel-specific accounts
        $this->createFuelAccounts($company);

        // Create sample vendors
        $this->createVendors($company);

        // Create sample investors
        $this->createInvestors($company, $fuelItems);

        // Create sample amanat customers
        $this->createAmanatCustomers($company);

        $this->command?->info('Fuel station seeding completed!');
    }

    /**
     * Create sample vendors.
     */
    private function createVendors(Company $company): void
    {
        $vendors = [
            [
                'name' => 'Fuel Supplier',
                'email' => 'fuel-supplier@example.com',
                'phone' => '042-30001234',
            ],
        ];

        $sequence = $this->nextVendorSequence($company->id);
        $baseCurrency = $company->base_currency ?? 'USD';

        foreach ($vendors as $vendorData) {
            $vendor = Vendor::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $vendorData['name'],
                ],
                [
                    'vendor_number' => 'VEND-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
                    'email' => $vendorData['email'],
                    'phone' => $vendorData['phone'],
                    'base_currency' => $baseCurrency,
                    'payment_terms' => 30,
                    'is_active' => true,
                ]
            );

            if ($vendor->wasRecentlyCreated) {
                $sequence++;
            }
        }
    }

    /**
     * Create fuel inventory items.
     */
    private function createFuelItems(Company $company): array
    {
        $baseCurrency = $company->base_currency ?? 'PKR';

        $fuelTypes = [
            [
                'name' => 'Petrol',
                'sku' => 'FUEL-PETROL',
                'fuel_category' => 'petrol',
                'unit' => 'liters',
                'cost_price' => 248.00,
            ],
            [
                'name' => 'Diesel',
                'sku' => 'FUEL-DIESEL',
                'fuel_category' => 'diesel',
                'unit' => 'liters',
                'cost_price' => 260.00,
            ],
            [
                'name' => 'Hi-Octane',
                'sku' => 'FUEL-HIOCTANE',
                'fuel_category' => 'high_octane',
                'unit' => 'liters',
                'cost_price' => 280.00,
            ],
        ];

        $items = [];
        foreach ($fuelTypes as $fuel) {
            $item = Item::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'sku' => $fuel['sku'],
                ],
                [
                    'name' => $fuel['name'],
                    'description' => "{$fuel['name']} fuel",
                    'unit_of_measure' => $fuel['unit'],
                    'fuel_category' => $fuel['fuel_category'],
                    'cost_price' => $fuel['cost_price'],
                    'avg_cost' => $fuel['cost_price'],
                    'selling_price' => $fuel['cost_price'] + 5, // Default margin
                    'currency' => $baseCurrency,
                    'track_inventory' => true,
                    'is_active' => true,
                ]
            );
            $items[$fuel['fuel_category']] = $item;
        }

        return $items;
    }

    /**
     * Create tank warehouses linked to fuel items.
     */
    private function createTanks(Company $company, array $fuelItems): array
    {
        $tankConfigs = [
            'petrol' => [
                'name' => 'Petrol Tank 1',
                'code' => 'TANK-P1',
                'capacity' => 25000, // 25,000 liters
            ],
            'diesel' => [
                'name' => 'Diesel Tank 1',
                'code' => 'TANK-D1',
                'capacity' => 20000, // 20,000 liters
            ],
            'high_octane' => [
                'name' => 'Hi-Octane Tank 1',
                'code' => 'TANK-H1',
                'capacity' => 10000, // 10,000 liters
            ],
        ];

        $tanks = [];
        foreach ($tankConfigs as $fuelCategory => $config) {
            if (!isset($fuelItems[$fuelCategory])) {
                continue;
            }

            $tank = Warehouse::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $config['code'],
                ],
                [
                    'name' => $config['name'],
                    'warehouse_type' => 'tank',
                    'capacity' => $config['capacity'],
                    'low_level_alert' => $config['capacity'] * 0.2, // 20% threshold
                    'linked_item_id' => $fuelItems[$fuelCategory]->id,
                    'is_active' => true,
                    'is_primary' => $fuelCategory === 'petrol',
                ]
            );
            $tanks[$fuelCategory] = $tank;
        }

        return $tanks;
    }

    /**
     * Create pumps linked to tanks.
     */
    private function createPumps(Company $company, array $tanks): void
    {
        $pumpConfigs = [
            // Petrol pumps
            ['name' => 'Point 1', 'tank_key' => 'petrol', 'meter' => 125000.50],
            ['name' => 'Point 2', 'tank_key' => 'petrol', 'meter' => 98500.25],
            ['name' => 'Point 3', 'tank_key' => 'petrol', 'meter' => 76200.00],
            // Diesel pumps
            ['name' => 'Point 4', 'tank_key' => 'diesel', 'meter' => 45000.75],
            ['name' => 'Point 5', 'tank_key' => 'diesel', 'meter' => 32100.00],
            // Hi-Octane pump
            ['name' => 'Point 6', 'tank_key' => 'high_octane', 'meter' => 15000.00],
        ];

        foreach ($pumpConfigs as $config) {
            if (!isset($tanks[$config['tank_key']])) {
                continue;
            }

            Pump::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $config['name'],
                ],
                [
                    'tank_id' => $tanks[$config['tank_key']]->id,
                    'current_meter_reading' => $config['meter'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Create current rate changes (government prices).
     */
    private function createRateChanges(Company $company, array $fuelItems): void
    {
        $rates = [
            'petrol' => ['purchase' => 248.00, 'sale' => 252.10],
            'diesel' => ['purchase' => 260.00, 'sale' => 263.50],
            'high_octane' => ['purchase' => 280.00, 'sale' => 285.00],
        ];

        foreach ($rates as $fuelCategory => $rate) {
            if (!isset($fuelItems[$fuelCategory])) {
                continue;
            }

            RateChange::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'item_id' => $fuelItems[$fuelCategory]->id,
                    'effective_date' => now()->startOfMonth()->toDateString(),
                ],
                [
                    'purchase_rate' => $rate['purchase'],
                    'sale_rate' => $rate['sale'],
                    'stock_quantity_at_change' => 10000,
                    'margin_impact' => 0,
                    'notes' => 'Initial rate setup',
                    'created_by_user_id' => null,
                ]
            );
        }
    }

    /**
     * Create fuel-specific GL accounts.
     */
    private function createFuelAccounts(Company $company): void
    {
        $accounts = [
            // Assets
            ['code' => '1030', 'name' => 'Vendor Card Clearing', 'type' => 'asset', 'subtype' => 'other_current_asset'],
            ['code' => '1040', 'name' => 'Card Payment Clearing', 'type' => 'asset', 'subtype' => 'other_current_asset'],
            ['code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash'],
            ['code' => '1060', 'name' => 'Attendant Cash in Transit', 'type' => 'asset', 'subtype' => 'other_current_asset'],
            ['code' => '1070', 'name' => 'Undeposited Funds', 'type' => 'asset', 'subtype' => 'other_current_asset'],
            ['code' => '1200', 'name' => 'Fuel Inventory', 'type' => 'asset', 'subtype' => 'inventory'],

            // Liabilities
            ['code' => '2200', 'name' => 'Amanat Deposits', 'type' => 'liability', 'subtype' => 'current_liability'],
            ['code' => '2210', 'name' => 'Investor Deposits', 'type' => 'liability', 'subtype' => 'current_liability'],
            ['code' => '2220', 'name' => 'Commission Payable', 'type' => 'liability', 'subtype' => 'current_liability'],

            // Revenue
            ['code' => '4100', 'name' => 'Fuel Sales', 'type' => 'revenue', 'subtype' => 'sales'],
            ['code' => '4210', 'name' => 'Sales Discounts', 'type' => 'revenue', 'subtype' => 'sales'], // Contra
            ['code' => '4900', 'name' => 'Fuel Variance Gain', 'type' => 'revenue', 'subtype' => 'other_income'],

            // COGS
            ['code' => '5100', 'name' => 'Fuel Cost of Goods Sold', 'type' => 'cogs', 'subtype' => 'cogs'],

            // Expenses
            ['code' => '6100', 'name' => 'Investor Commission Expense', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '6180', 'name' => 'Cash Over/Short', 'type' => 'expense', 'subtype' => 'operating_expense'],
            ['code' => '6300', 'name' => 'Fuel Shrinkage Loss', 'type' => 'expense', 'subtype' => 'operating_expense'],
        ];

        $baseCurrency = $company->base_currency ?? 'PKR';

        $normalBalanceMap = [
            'asset' => 'debit',
            'expense' => 'debit',
            'cogs' => 'debit',
            'liability' => 'credit',
            'equity' => 'credit',
            'revenue' => 'credit',
        ];

        foreach ($accounts as $account) {
            $normalBalance = $normalBalanceMap[$account['type']] ?? 'debit';

            Account::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $account['code'],
                ],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'subtype' => $account['subtype'],
                    'normal_balance' => $normalBalance,
                    'currency_code' => $baseCurrency,
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
        }
    }

    /**
     * Create sample investors with investment lots.
     */
    private function createInvestors(Company $company, array $fuelItems): void
    {
        $petrolItem = $fuelItems['petrol'] ?? null;
        if (!$petrolItem) {
            return;
        }

        // Get current rate for entitlement calculation
        $currentRate = RateChange::getCurrentRate($company->id, $petrolItem->id);
        $purchaseRate = $currentRate?->purchase_rate ?? 248.00;
        $commissionRate = ($currentRate?->sale_rate ?? 252.10) - $purchaseRate;

        $investors = [
            [
                'name' => 'Ahmed Khan',
                'phone' => '0300-1234567',
                'cnic' => '35201-1234567-1',
                'investment' => 500000, // 5 lac
            ],
            [
                'name' => 'Bilal Ahmed',
                'phone' => '0321-9876543',
                'cnic' => '35202-7654321-3',
                'investment' => 250000, // 2.5 lac
            ],
        ];

        foreach ($investors as $investorData) {
            $investor = Investor::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'cnic' => $investorData['cnic'],
                ],
                [
                    'name' => $investorData['name'],
                    'phone' => $investorData['phone'],
                    'total_invested' => $investorData['investment'],
                    'total_commission_earned' => 0,
                    'total_commission_paid' => 0,
                    'is_active' => true,
                ]
            );

            // Create investment lot if not exists
            if ($investor->wasRecentlyCreated) {
                $unitsEntitled = $investorData['investment'] / $purchaseRate;

                InvestorLot::create([
                    'company_id' => $company->id,
                    'investor_id' => $investor->id,
                    'deposit_date' => now()->subDays(30)->toDateString(),
                    'investment_amount' => $investorData['investment'],
                    'entitlement_rate' => $purchaseRate,
                    'commission_rate' => $commissionRate,
                    'units_entitled' => $unitsEntitled,
                    'units_remaining' => $unitsEntitled,
                    'commission_earned' => 0,
                    'status' => InvestorLot::STATUS_ACTIVE,
                ]);
            }
        }
    }

    /**
     * Create sample amanat (trust deposit) customers.
     */
    private function createAmanatCustomers(Company $company): void
    {
        $customers = [
            [
                'name' => 'ABC Transport Co.',
                'email' => 'accounts@abctransport.pk',
                'phone' => '042-35123456',
                'cnic' => null,
                'is_credit' => true,
                'is_amanat' => true,
                'amanat_balance' => 75000,
                'relationship' => 'external',
            ],
            [
                'name' => 'Muhammad Iqbal (Driver)',
                'email' => null,
                'phone' => '0333-5551234',
                'cnic' => '35201-5551234-5',
                'is_credit' => false,
                'is_amanat' => true,
                'amanat_balance' => 15000,
                'relationship' => 'employee',
            ],
        ];

        $sequence = $this->nextCustomerSequence($company->id);
        $baseCurrency = $company->base_currency ?? 'USD';

        foreach ($customers as $customerData) {
            // Create or find customer
            $customer = Customer::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $customerData['name'],
                ],
                [
                    'customer_number' => 'CUST-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
                    'email' => $customerData['email'],
                    'phone' => $customerData['phone'],
                    'base_currency' => $baseCurrency,
                    'is_active' => true,
                ]
            );

            if ($customer->wasRecentlyCreated) {
                $sequence++;
            }

            // Create fuel customer profile
            CustomerProfile::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                ],
                [
                    'is_credit_customer' => $customerData['is_credit'],
                    'is_amanat_holder' => $customerData['is_amanat'],
                    'is_investor' => false,
                    'relationship' => $customerData['relationship'],
                    'cnic' => $customerData['cnic'],
                    'amanat_balance' => $customerData['amanat_balance'],
                ]
            );
        }
    }

    private function nextCustomerSequence(string $companyId): int
    {
        $lastNumber = Customer::where('company_id', $companyId)
            ->whereNotNull('customer_number')
            ->orderByDesc('customer_number')
            ->value('customer_number');

        if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }

    private function nextVendorSequence(string $companyId): int
    {
        $lastNumber = Vendor::where('company_id', $companyId)
            ->whereNotNull('vendor_number')
            ->orderByDesc('vendor_number')
            ->value('vendor_number');

        if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            return ((int) $matches[1]) + 1;
        }

        return 1;
    }
}
