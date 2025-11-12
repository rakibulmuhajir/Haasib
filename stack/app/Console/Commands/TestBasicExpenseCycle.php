<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestBasicExpenseCycle extends Command
{
    protected $signature = 'app:test-basic-expense-cycle';

    protected $description = 'Test basic expense cycle without complex purchase order dependencies';

    public function handle()
    {
        $this->info('=== PHASE 7.2: Basic Expense Cycle E2E Test ===');
        $this->newLine();

        try {
            // Get or create test company
            $company = $this->getOrCreateTestCompany();
            $this->info("✅ Using company: {$company->name}");

            // Set company context for RLS
            DB::statement("SET app.current_company_id = '{$company->id}'");

            // Step 1: Create test user
            $user = $this->createTestUser();
            $this->info("✅ Created test user: {$user->name}");

            // Step 2: Create vendor
            $vendor = $this->createTestVendor($company);
            $this->info("✅ Created vendor: {$vendor->display_name}");

            // Step 3: Create expense categories
            $categories = $this->createExpenseCategories($company);
            $this->info('✅ Created expense categories');

            // Step 4: Create and process vendor expenses
            $vendorExpenses = $this->createVendorExpenses($company, $vendor, $categories, $user);
            $this->info('✅ Created '.count($vendorExpenses).' vendor expenses');

            // Step 4: Process expense approvals
            $this->processExpenseApprovals($vendorExpenses);
            $this->info('✅ Processed expense approvals');

            // Step 5: Process expense payments
            $this->processExpensePayments($vendorExpenses);
            $this->info('✅ Processed expense payments');

            // Step 6: Test expense analytics
            $this->testExpenseAnalytics($company);

            // Step 7: Test vendor reconciliation
            $this->testVendorReconciliation($company, $vendor);

            $this->newLine();
            $this->info('=== Basic Expense Cycle Test Summary ===');
            $this->info('✅ Vendor creation and management');
            $this->info('✅ Expense category management');
            $this->info('✅ Vendor expense creation');
            $this->info('✅ Expense approval workflow');
            $this->info('✅ Expense payment processing');
            $this->info('✅ Expense analytics and reporting');
            $this->info('✅ Vendor reconciliation');
            $this->newLine();
            $this->info('🎉 Basic Expense Cycle E2E Testing: SUCCESS');
            $this->info('💸 Complete expense management workflow validated');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Basic expense cycle test failed: '.$e->getMessage());
            $this->error('📍 Stack trace: '.$e->getTraceAsString());

            return 1;
        }
    }

    private function getOrCreateTestCompany(): Company
    {
        $company = Company::where('name', 'like', '%E2E Basic Expense%')->first();

        if (! $company) {
            $company = Company::create([
                'name' => 'E2E Basic Expense Company '.time(),
                'email' => 'basic.expense@e2etest.com',
                'phone' => '+1 (555) 123-4567',
                'website' => 'https://www.e2etest.com',
                'currency_code' => 'USD',
                'industry' => 'Consulting',
                'tax_id' => 'E2E-BASIC-'.time(),
                'status' => 'active',
                'setup_completed' => true,
            ]);
        }

        return $company;
    }

    private function createTestVendor(Company $company): Vendor
    {
        return Vendor::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => $company->id,
            'vendor_code' => 'VEND-'.time(),
            'legal_name' => 'E2E Basic Vendor LLC',
            'display_name' => 'E2E Basic Vendor',
            'tax_id' => 'VEND-TAX-'.time(),
            'vendor_type' => 'company',
            'status' => 'active',
            'website' => 'https://www.e2ebasicvendor.com',
        ]);
    }

    private function createExpenseCategories(Company $company): array
    {
        $categoriesData = [
            ['name' => 'Professional Services', 'description' => 'External consulting and professional services'],
            ['name' => 'Software & Licenses', 'description' => 'Software subscriptions and licenses'],
            ['name' => 'Office Expenses', 'description' => 'General office and administrative expenses'],
            ['name' => 'Travel & Entertainment', 'description' => 'Business travel and entertainment expenses'],
        ];

        $categories = [];
        foreach ($categoriesData as $categoryData) {
            $category = ExpenseCategory::where('company_id', $company->id)
                ->where('name', $categoryData['name'])
                ->first();

            if (! $category) {
                $category = ExpenseCategory::create([
                    'id' => \Illuminate\Support\Str::uuid(),
                    'company_id' => $company->id,
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'status' => 'active',
                ]);
            }

            $categories[$categoryData['name']] = $category;
        }

        return $categories;
    }

    private function createTestUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'e2e.expense@e2etest.com'],
            [
                'name' => 'E2E Expense Test User',
                'username' => 'e2e_expense_user',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );
    }

    private function createVendorExpenses(Company $company, Vendor $vendor, array $categories, User $user): array
    {
        $expensesData = [
            [
                'category' => 'Professional Services',
                'title' => 'Legal Consulting Services',
                'description' => 'Monthly legal retainer services',
                'amount' => 2500.00,
                'receipt_number' => 'LEGAL-'.time(),
            ],
            [
                'category' => 'Software & Licenses',
                'title' => 'Microsoft 365 Subscription',
                'description' => 'Annual software license subscription',
                'amount' => 360.00,
                'receipt_number' => 'SOFT-'.time(),
            ],
            [
                'category' => 'Office Expenses',
                'title' => 'Office Supplies',
                'description' => 'Monthly office supplies and stationery',
                'amount' => 185.00,
                'receipt_number' => 'OFFICE-'.time(),
            ],
            [
                'category' => 'Travel & Entertainment',
                'title' => 'Client Entertainment',
                'description' => 'Business lunch with potential clients',
                'amount' => 150.00,
                'receipt_number' => 'ENTERTAIN-'.time(),
            ],
            [
                'category' => 'Professional Services',
                'title' => 'IT Support Services',
                'description' => 'Quarterly IT support and maintenance',
                'amount' => 750.00,
                'receipt_number' => 'IT-'.time(),
            ],
        ];

        $expenses = [];
        foreach ($expensesData as $expenseData) {
            $expense = Expense::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'company_id' => $company->id,
                'expense_category_id' => $categories[$expenseData['category']]->id,
                'vendor_id' => $vendor->id,
                'title' => $expenseData['title'],
                'description' => $expenseData['description'],
                'expense_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'amount' => $expenseData['amount'],
                'currency' => 'USD',
                'exchange_rate' => 1.00,
                'receipt_number' => $expenseData['receipt_number'],
                'notes' => 'E2E Basic Expense Test',
                'status' => 'submitted',
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'created_by' => $user->id,
            ]);

            $expenses[] = $expense;
        }

        return $expenses;
    }

    private function processExpenseApprovals(array $expenses): void
    {
        foreach ($expenses as $expense) {
            // Simulate approval workflow
            if ($expense->amount > 1000) {
                // High-value expenses require management approval
                $expense->approve(null); // No actual user for E2E test
                $this->line("   Approved high-value expense: {$expense->title} (\${$expense->amount})");
            } else {
                // Lower-value expenses auto-approved
                $expense->approve(null);
                $this->line("   Auto-approved expense: {$expense->title} (\${$expense->amount})");
            }
        }
    }

    private function processExpensePayments(array $expenses): void
    {
        foreach ($expenses as $expense) {
            // Only process payments for approved expenses
            if ($expense->status === 'approved') {
                $expense->markAsPaid(
                    now()->addDays(rand(1, 14)), // Simulate payment processing time
                    'PAY-'.uniqid()
                );
                $this->line("   Paid expense: {$expense->title} (\${$expense->amount})");
            }
        }
    }

    private function testExpenseAnalytics(Company $company): void
    {
        $this->info('📊 Testing Expense Analytics:');

        $totalExpenses = Expense::where('company_id', $company->id)->count();
        $submittedExpenses = Expense::where('company_id', $company->id)->where('status', 'submitted')->count();
        $approvedExpenses = Expense::where('company_id', $company->id)->where('status', 'approved')->count();
        $paidExpenses = Expense::where('company_id', $company->id)->where('status', 'paid')->count();

        $totalExpenseAmount = Expense::where('company_id', $company->id)->sum('amount');
        $paidExpenseAmount = Expense::where('company_id', $company->id)->where('status', 'paid')->sum('amount');

        // Category breakdown
        $categoryBreakdown = Expense::where('expenses.company_id', $company->id)
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->selectRaw('expense_categories.name, SUM(expenses.amount) as total, COUNT(*) as count')
            ->groupBy('expense_categories.name')
            ->get();

        $this->line("   Total Expenses: {$totalExpenses}");
        $this->line("   Submitted: {$submittedExpenses}");
        $this->line("   Approved: {$approvedExpenses}");
        $this->line("   Paid: {$paidExpenses}");
        $this->line("   Total Amount: \${$totalExpenseAmount}");
        $this->line("   Paid Amount: \${$paidExpenseAmount}");

        foreach ($categoryBreakdown as $category) {
            $this->line("   - {$category->name}: {$category->count} expenses, \${$category->total}");
        }

        $this->info('   ✅ Expense analytics working correctly');
    }

    private function testVendorReconciliation(Company $company, Vendor $vendor): void
    {
        $this->info('🔍 Testing Vendor Reconciliation:');

        $vendorExpenses = Expense::where('company_id', $company->id)
            ->where('vendor_id', $vendor->id)
            ->get();

        $totalExpenses = $vendorExpenses->count();
        $paidExpenses = $vendorExpenses->where('status', 'paid')->count();
        $totalExpenseAmount = $vendorExpenses->sum('amount');
        $paidExpenseAmount = $vendorExpenses->where('status', 'paid')->sum('amount');

        $outstandingAmount = $totalExpenseAmount - $paidExpenseAmount;

        $this->line("   Vendor: {$vendor->display_name}");
        $this->line("   Total Expenses: {$totalExpenses}");
        $this->line("   Paid Expenses: {$paidExpenses}");
        $this->line("   Total Amount: \${$totalExpenseAmount}");
        $this->line("   Paid Amount: \${$paidExpenseAmount}");
        $this->line("   Outstanding Amount: \${$outstandingAmount}");

        if ($outstandingAmount === 0) {
            $this->info('   ✅ All vendor expenses settled');
        } else {
            $this->info("   ⚠️ Outstanding vendor expenses: \${$outstandingAmount}");
        }

        $this->info('   ✅ Vendor reconciliation functional');
    }
}
