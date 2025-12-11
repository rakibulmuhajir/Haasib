<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class TestOnboarding extends Command
{
    protected $signature = 'test:onboarding {industry=restaurant}';
    protected $description = 'Test company onboarding flow end-to-end';

    public function handle(CompanyOnboardingService $onboardingService): int
    {
        $this->info('Testing Company Onboarding System...');
        $this->newLine();

        // Set up auth context (use super admin for testing)
        $superAdmin = \App\Models\User::where('email', 'admin@haasib.com')->first();
        if (!$superAdmin) {
            $this->error('Super admin not found. Run seeders first.');
            return 1;
        }
        Auth::login($superAdmin);

        $industryCode = $this->argument('industry');

        // Step 1: Create company with basic info
        $this->info('Step 1: Creating company...');
        $company = Company::create([
            'name' => "Test {$industryCode} Company",
            'slug' => 'test-' . $industryCode . '-' . uniqid(),
            'country' => 'PK',
            'base_currency' => 'PKR',
            'created_by_user_id' => $superAdmin->id,
            'is_active' => true,
        ]);
        $this->info("✓ Company created: {$company->name}");

        // Step 2: Set up company identity and COA
        $this->info("\nStep 2: Setting up company identity and industry COA...");
        $onboardingService->setupCompanyIdentity($company->fresh(), [
            'industry_code' => $industryCode,
            'registration_number' => 'TEST-REG-' . rand(1000, 9999),
            'trade_name' => 'Test Trading Name',
            'timezone' => 'Asia/Karachi',
        ]);
        $company = $company->fresh();
        $accountCount = Account::where('company_id', $company->id)->count();
        $this->info("✓ Industry COA created: {$accountCount} accounts");

        // Step 3: Set up fiscal year
        $this->info("\nStep 3: Setting up fiscal year...");
        $fiscalYear = $onboardingService->setupFiscalYear($company, [
            'fiscal_year_start_month' => 7, // July start
            'period_frequency' => 'monthly',
        ]);
        $company = $company->fresh();
        $periodCount = $fiscalYear->periods()->count();
        $this->info("✓ Fiscal year created: {$fiscalYear->name}");
        $this->info("✓ Accounting periods: {$periodCount}");

        // Step 4: Set up bank accounts
        $this->info("\nStep 4: Setting up bank accounts...");
        $bankAccounts = $onboardingService->setupBankAccounts($company, [
            ['account_name' => 'Meezan Bank PKR', 'currency' => 'PKR', 'account_type' => 'bank'],
            ['account_name' => 'HBL USD Account', 'currency' => 'USD', 'account_type' => 'bank'],
            ['account_name' => 'Cash Drawer', 'currency' => 'PKR', 'account_type' => 'cash'],
        ]);
        $this->info("✓ Bank accounts created: " . count($bankAccounts));

        // Step 5: Set up default accounts
        $this->info("\nStep 5: Setting up default accounts...");
        $arAccount = Account::where('company_id', $company->id)->where('subtype', 'accounts_receivable')->first();
        $apAccount = Account::where('company_id', $company->id)->where('subtype', 'accounts_payable')->first();
        $revenueAccount = Account::where('company_id', $company->id)->where('type', 'revenue')->first();
        $expenseAccount = Account::where('company_id', $company->id)->where('type', 'expense')->first();
        $reAccount = Account::where('company_id', $company->id)->where('subtype', 'retained_earnings')->first();

        $onboardingService->setupDefaultAccounts($company->fresh(), [
            'ar_account_id' => $arAccount->id,
            'ap_account_id' => $apAccount->id,
            'income_account_id' => $revenueAccount->id,
            'expense_account_id' => $expenseAccount->id,
            'bank_account_id' => $bankAccounts[0]->id,
            'retained_earnings_account_id' => $reAccount->id,
        ]);
        $this->info('✓ Default accounts configured');

        // Step 6: Set up tax settings
        $this->info("\nStep 6: Setting up tax settings...");
        $onboardingService->setupTaxSettings($company->fresh(), [
            'tax_registered' => true,
            'tax_rate' => 18.00,
            'tax_inclusive' => false,
        ]);
        $this->info('✓ Tax settings configured');

        // Step 7: Set up numbering
        $this->info("\nStep 7: Setting up numbering preferences...");
        $onboardingService->setupNumberingPreferences($company->fresh(), [
            'invoice_prefix' => 'INV-',
            'invoice_start_number' => 1001,
            'bill_prefix' => 'BILL-',
            'bill_start_number' => 2001,
        ]);
        $this->info('✓ Numbering preferences configured');

        // Step 8: Set up payment terms
        $this->info("\nStep 8: Setting up payment terms...");
        $onboardingService->setupPaymentTerms($company->fresh(), [
            'default_customer_payment_terms' => 30,
            'default_vendor_payment_terms' => 45,
        ]);
        $this->info('✓ Payment terms configured');

        // Complete onboarding
        $this->info("\nCompleting onboarding...");
        $onboardingService->completeOnboarding($company->fresh());
        $company = $company->fresh();
        $this->info('✓ Onboarding completed');

        // Verification
        $this->newLine();
        $this->info('=== VERIFICATION ===');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Industry', $company->industry_code],
                ['Fiscal Start Month', $company->fiscal_year_start_month],
                ['Invoice Prefix', $company->invoice_prefix],
                ['Invoice Start Number', $company->invoice_start_number],
                ['Customer Payment Terms', $company->default_customer_payment_terms],
                ['Vendor Payment Terms', $company->default_vendor_payment_terms],
                ['Tax Registered', $company->tax_registered ? 'Yes' : 'No'],
                ['Tax Rate', $company->tax_rate . '%'],
                ['Onboarding Complete', $company->onboarding_completed ? 'Yes' : 'No'],
            ]
        );

        $this->newLine();
        $this->info('=== ACCOUNTS CREATED ===');
        $accountsByType = Account::where('company_id', $company->id)
            ->selectRaw('type, subtype, COUNT(*) as count')
            ->groupBy('type', 'subtype')
            ->orderBy('type')
            ->orderBy('subtype')
            ->get();

        $tableData = [];
        foreach ($accountsByType as $row) {
            $tableData[] = [$row->type, $row->subtype, $row->count];
        }
        $this->table(['Type', 'Subtype', 'Count'], $tableData);

        $this->newLine();
        $this->info('✅ Onboarding test completed successfully!');
        $this->info("Company ID: {$company->id}");
        $this->info("Company Slug: {$company->slug}");

        return 0;
    }
}
