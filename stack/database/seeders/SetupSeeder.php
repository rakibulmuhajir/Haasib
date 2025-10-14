<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\CompanyUser;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Accounting\Models\ChartOfAccount;

class SetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Setting up initial system state...');

        // Start transaction for data consistency
        DB::beginTransaction();

        try {
            $this->createModules();
            $this->createUsers();
            $this->createCompanies();
            $this->assignCompanyUsers();
            $this->enableCompanyModules();
            $this->createChartOfAccounts();

            DB::commit();
            $this->command->info('System setup completed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('System setup failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createModules(): void
    {
        $modules = [
            [
                'name' => 'Accounting',
                'version' => '1.0.0',
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['name' => $module['name']],
                $module
            );
        }

        $this->command->info('✓ Core modules created');
    }

    private function createUsers(): void
    {
        $users = [
            [
                'name' => 'System Owner',
                'email' => 'admin@haasib.local',
                'username' => 'system_owner',
                'password' => Hash::make('admin123'),
                'role' => 'system_owner',
                'is_active' => true,
            ],
            [
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed@grandhotel.local',
                'username' => 'ahmed_hotel',
                'password' => Hash::make('hotel123'),
                'role' => 'company_owner',
                'is_active' => true,
            ],
            [
                'name' => 'Fatima Khalid',
                'email' => 'fatima@retailmart.local',
                'username' => 'fatima_retail',
                'password' => Hash::make('retail123'),
                'role' => 'company_owner',
                'is_active' => true,
            ],
            [
                'name' => 'Mohammed Ali',
                'email' => 'mohammed@consultpro.local',
                'username' => 'mohammed_consulting',
                'password' => Hash::make('consult123'),
                'role' => 'company_owner',
                'is_active' => true,
            ],
            [
                'name' => 'Sarah Omar',
                'email' => 'sarah@grandhotel.local',
                'username' => 'sarah_accountant',
                'password' => Hash::make('account123'),
                'role' => 'accountant',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('✓ Users created');
    }

    private function createCompanies(): void
    {
        $companies = [
            [
                'name' => 'Grand Hotel Alexandria',
                'industry' => 'hospitality',
                'base_currency' => 'EGP',
                'fiscal_year_start' => '2024-01-01',
                'is_active' => true,
            ],
            [
                'name' => 'RetailMart Egypt',
                'industry' => 'retail',
                'base_currency' => 'EGP',
                'fiscal_year_start' => '2024-01-01',
                'is_active' => true,
            ],
            [
                'name' => 'ConsultPro Solutions',
                'industry' => 'professional_services',
                'base_currency' => 'EGP',
                'fiscal_year_start' => '2024-01-01',
                'is_active' => true,
            ],
        ];

        foreach ($companies as $companyData) {
            Company::updateOrCreate(
                ['name' => $companyData['name']],
                array_merge($companyData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Companies created');
    }

    private function assignCompanyUsers(): void
    {
        $assignments = [
            // System Owner has access to all companies
            ['user_email' => 'admin@haasib.local', 'company_name' => 'Grand Hotel Alexandria', 'role' => 'owner'],
            ['user_email' => 'admin@haasib.local', 'company_name' => 'RetailMart Egypt', 'role' => 'owner'],
            ['user_email' => 'admin@haasib.local', 'company_name' => 'ConsultPro Solutions', 'role' => 'owner'],

            // Hotel company assignments
            ['user_email' => 'ahmed@grandhotel.local', 'company_name' => 'Grand Hotel Alexandria', 'role' => 'owner'],
            ['user_email' => 'sarah@grandhotel.local', 'company_name' => 'Grand Hotel Alexandria', 'role' => 'accountant'],

            // Retail company assignments
            ['user_email' => 'fatima@retailmart.local', 'company_name' => 'RetailMart Egypt', 'role' => 'owner'],

            // Consulting company assignments
            ['user_email' => 'mohammed@consultpro.local', 'company_name' => 'ConsultPro Solutions', 'role' => 'owner'],
        ];

        foreach ($assignments as $assignment) {
            $user = User::where('email', $assignment['user_email'])->first();
            $company = Company::where('name', $assignment['company_name'])->first();

            if ($user && $company) {
                CompanyUser::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'company_id' => $company->id,
                    ],
                    [
                        'role' => $assignment['role'],
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info('✓ User-company assignments created');
    }

    private function enableCompanyModules(): void
    {
        $companies = Company::all();
        $accountingModule = Module::where('name', 'Accounting')->first();

        foreach ($companies as $company) {
            CompanyModule::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'module_id' => $accountingModule->id,
                ],
                [
                    'is_enabled' => true,
                    'enabled_at' => now(),
                    'enabled_by' => User::where('role', 'system_owner')->first()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('✓ Company modules enabled');
    }

    private function createChartOfAccounts(): void
    {
        // Standard chart of accounts template for all companies
        $accounts = [
            ['code' => '1000', 'name' => 'Assets', 'type' => 'group', 'parent_id' => null],
            ['code' => '1100', 'name' => 'Current Assets', 'type' => 'group', 'parent_code' => '1000'],
            ['code' => '1110', 'name' => 'Cash', 'type' => 'account', 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'Accounts Receivable', 'type' => 'account', 'parent_code' => '1100'],
            ['code' => '1200', 'name' => 'Fixed Assets', 'type' => 'group', 'parent_code' => '1000'],
            ['code' => '1210', 'name' => 'Equipment', 'type' => 'account', 'parent_code' => '1200'],

            ['code' => '2000', 'name' => 'Liabilities', 'type' => 'group', 'parent_id' => null],
            ['code' => '2100', 'name' => 'Current Liabilities', 'type' => 'group', 'parent_code' => '2000'],
            ['code' => '2110', 'name' => 'Accounts Payable', 'type' => 'account', 'parent_code' => '2100'],

            ['code' => '3000', 'name' => 'Equity', 'type' => 'group', 'parent_id' => null],
            ['code' => '3100', 'name' => 'Capital', 'type' => 'account', 'parent_code' => '3000'],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'account', 'parent_code' => '3000'],

            ['code' => '4000', 'name' => 'Revenue', 'type' => 'group', 'parent_id' => null],
            ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'account', 'parent_code' => '4000'],
            ['code' => '4200', 'name' => 'Service Revenue', 'type' => 'account', 'parent_code' => '4000'],

            ['code' => '5000', 'name' => 'Expenses', 'type' => 'group', 'parent_id' => null],
            ['code' => '5100', 'name' => 'Operating Expenses', 'type' => 'group', 'parent_code' => '5000'],
            ['code' => '5110', 'name' => 'Salaries', 'type' => 'account', 'parent_code' => '5100'],
            ['code' => '5120', 'name' => 'Rent', 'type' => 'account', 'parent_code' => '5100'],
        ];

        foreach (Company::all() as $company) {
            $accountMap = [];

            foreach ($accounts as $accountData) {
                $accountId = $this->createAccount($company, $accountData, $accountMap);
                $accountMap[$accountData['code']] = $accountId;
            }
        }

        $this->command->info('✓ Chart of accounts created');
    }

    private function createAccount(Company $company, array $accountData, array $accountMap): ?string
    {
        $data = [
            'company_id' => $company->id,
            'code' => $accountData['code'],
            'name' => $accountData['name'],
            'type' => $accountData['type'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (isset($accountData['parent_id'])) {
            $data['parent_id'] = $accountData['parent_id'];
        } elseif (isset($accountData['parent_code']) && isset($accountMap[$accountData['parent_code']])) {
            $data['parent_id'] = $accountMap[$accountData['parent_code']];
        }

        $account = ChartOfAccount::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => $accountData['code'],
            ],
            $data
        );

        return $account->id;
    }
}
