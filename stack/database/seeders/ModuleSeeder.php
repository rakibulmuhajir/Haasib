<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyModule;
use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding modules...');

        DB::beginTransaction();

        try {
            $this->createDefaultModules();
            $this->enableModulesForCompanies();

            DB::commit();
            $this->command->info('✓ Modules seeded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Module seeding failed: '.$e->getMessage());
            throw $e;
        }
    }

    private function createDefaultModules(): void
    {
        $modules = [
            [
                'name' => 'Accounting',
                'version' => '1.0.0',
                'is_enabled' => true,
                'description' => 'Complete accounting module with invoicing, payments, ledger management, and financial reporting',
                'capabilities' => json_encode([
                    'customer_management',
                    'invoice_generation',
                    'payment_processing',
                    'chart_of_accounts',
                    'journal_entries',
                    'financial_reports',
                    'audit_trail',
                    'multi_currency',
                ]),
                'dependencies' => json_encode([]),
                'module_class' => 'Modules\\Accounting\\Providers\\AccountingServiceProvider',
            ],
            [
                'name' => 'Inventory',
                'version' => '1.0.0',
                'is_enabled' => false,
                'description' => 'Inventory management for product-based businesses with stock tracking and reordering',
                'capabilities' => json_encode([
                    'product_catalog',
                    'stock_management',
                    'purchase_orders',
                    'supplier_management',
                    'stock_movements',
                    'inventory_valuation',
                    'low_stock_alerts',
                ]),
                'dependencies' => json_encode(['Accounting']),
                'module_class' => null, // Not yet implemented
            ],
            [
                'name' => 'Payroll',
                'version' => '1.0.0',
                'is_enabled' => false,
                'description' => 'Employee payroll management with salary calculations and statutory deductions',
                'capabilities' => json_encode([
                    'employee_management',
                    'salary_calculation',
                    'timesheet_management',
                    'leave_management',
                    'statutory_deductions',
                    'payroll_reports',
                    'payslip_generation',
                ]),
                'dependencies' => json_encode(['Accounting']),
                'module_class' => null, // Not yet implemented
            ],
            [
                'name' => 'CRM',
                'version' => '1.0.0',
                'is_enabled' => false,
                'description' => 'Customer relationship management with sales pipeline and communication tracking',
                'capabilities' => json_encode([
                    'contact_management',
                    'lead_tracking',
                    'sales_pipeline',
                    'communication_history',
                    'task_management',
                    'document_management',
                    'sales_reports',
                ]),
                'dependencies' => json_encode([]),
                'module_class' => null, // Not yet implemented
            ],
            [
                'name' => 'Projects',
                'version' => '1.0.0',
                'is_enabled' => false,
                'description' => 'Project management for service-based businesses with time tracking and billing',
                'capabilities' => json_encode([
                    'project_management',
                    'task_tracking',
                    'time_tracking',
                    'budget_management',
                    'resource_allocation',
                    'project_billing',
                    'progress_reports',
                ]),
                'dependencies' => json_encode(['Accounting']),
                'module_class' => null, // Not yet implemented
            ],
        ];

        foreach ($modules as $moduleData) {
            Module::updateOrCreate(
                ['name' => $moduleData['name']],
                array_merge($moduleData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Created '.count($modules).' default modules');
    }

    private function enableModulesForCompanies(): void
    {
        $companies = Company::all();
        $accountingModule = Module::where('name', 'Accounting')->first();

        if (! $accountingModule) {
            $this->command->error('Accounting module not found');

            return;
        }

        $enabledCount = 0;

        foreach ($companies as $company) {
            // Enable Accounting module for all companies
            CompanyModule::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'module_id' => $accountingModule->id,
                ],
                [
                    'is_enabled' => true,
                    'enabled_at' => now(),
                    'enabled_by' => $this->getSystemOwnerId(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $enabledCount++;
        }

        $this->command->info("✓ Enabled Accounting module for {$enabledCount} companies");

        // Enable industry-specific modules
        $this->enableIndustrySpecificModules();
    }

    private function enableIndustrySpecificModules(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            switch ($company->industry) {
                case 'retail':
                    $this->enableModuleForCompany($company, 'Inventory');
                    break;

                case 'professional_services':
                    $this->enableModuleForCompany($company, 'Projects');
                    $this->enableModuleForCompany($company, 'CRM');
                    break;

                case 'hospitality':
                    $this->enableModuleForCompany($company, 'CRM');
                    break;
            }
        }
    }

    private function enableModuleForCompany(Company $company, string $moduleName): void
    {
        $module = Module::where('name', $moduleName)->first();
        if (! $module) {
            return;
        }

        // Check if dependencies are enabled
        $dependencies = json_decode($module->dependencies ?? '[]', true);
        foreach ($dependencies as $dependency) {
            $depModule = Module::where('name', $dependency)->first();
            if (! $depModule) {
                continue;
            }

            $companyModule = CompanyModule::where('company_id', $company->id)
                ->where('module_id', $depModule->id)
                ->first();
            if (! $companyModule || ! $companyModule->is_enabled) {
                // Enable dependency first
                CompanyModule::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'module_id' => $depModule->id,
                    ],
                    [
                        'is_enabled' => true,
                        'enabled_at' => now(),
                        'enabled_by' => $this->getSystemOwnerId(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $this->command->info("✓ Enabled dependency '{$dependency}' for company: {$company->name}");
            }
        }

        // Enable the module
        CompanyModule::updateOrCreate(
            [
                'company_id' => $company->id,
                'module_id' => $module->id,
            ],
            [
                'is_enabled' => true,
                'enabled_at' => now(),
                'enabled_by' => $this->getSystemOwnerId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->command->info("✓ Enabled module '{$moduleName}' for company: {$company->name}");
    }

    private function getSystemOwnerId(): ?string
    {
        // This would typically get the ID of the system_owner user
        // For now, we'll use a placeholder logic
        $systemUser = DB::table('users')->where('role', 'system_owner')->first();

        return $systemUser ? $systemUser->id : null;
    }
}
