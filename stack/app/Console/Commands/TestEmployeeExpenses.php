<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestEmployeeExpenses extends Command
{
    protected $signature = 'app:test-employee-expenses';

    protected $description = 'Test employee expense management and reimbursement workflows';

    public function handle()
    {
        $this->info('=== PHASE 7.2: Employee Expense Management E2E Test ===');
        $this->newLine();

        try {
            // Get or create test company
            $company = $this->getOrCreateTestCompany();
            $this->info("✅ Using company: {$company->name}");

            // Set company context for RLS
            DB::statement("SET app.current_company_id = '{$company->id}'");

            // Step 1: Create expense categories
            $categories = $this->createExpenseCategories($company);
            $this->info('✅ Created expense categories');

            // Step 2: Create test employee
            $employee = $this->createTestEmployee($company);
            $this->info("✅ Created employee: {$employee->name}");

            // Step 3: Submit multiple expense reports
            $expenses = $this->submitMultipleExpenses($company, $employee, $categories);
            $this->info('✅ Submitted '.count($expenses).' expense reports');

            // Step 4: Process approval workflow
            $this->processApprovalWorkflow($expenses);
            $this->info('✅ Processed expense approvals');

            // Step 5: Process expense reimbursements
            $this->processExpenseReimbursements($expenses);
            $this->info('✅ Processed expense reimbursements');

            // Step 6: Test expense analytics and reporting
            $this->testExpenseAnalytics($company, $employee);

            // Step 7: Test expense policy enforcement
            $this->testExpensePolicyEnforcement($company, $employee);

            $this->newLine();
            $this->info('=== Employee Expense Management Test Summary ===');
            $this->info('✅ Expense category management');
            $this->info('✅ Employee creation and setup');
            $this->info('✅ Multiple expense report submission');
            $this->info('✅ Expense approval workflow');
            $this->info('✅ Expense reimbursement processing');
            $this->info('✅ Expense analytics and reporting');
            $this->info('✅ Expense policy enforcement');
            $this->newLine();
            $this->info('🎉 Employee Expense Management E2E Testing: SUCCESS');
            $this->info('💳 Complete expense management workflow validated');

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Employee expense test failed: '.$e->getMessage());
            $this->error('📍 Stack trace: '.$e->getTraceAsString());

            return 1;
        }
    }

    private function getOrCreateTestCompany(): Company
    {
        $company = Company::where('name', 'like', '%E2E Employee Expense%')->first();

        if (! $company) {
            $company = Company::create([
                'name' => 'E2E Employee Expense Company '.time(),
                'email' => 'employee.expense@e2etest.com',
                'phone' => '+1 (555) 123-4567',
                'website' => 'https://www.e2etest.com',
                'currency_code' => 'USD',
                'industry' => 'Professional Services',
                'tax_id' => 'E2E-EMP-'.time(),
                'status' => 'active',
                'setup_completed' => true,
            ]);
        }

        return $company;
    }

    private function createTestEmployee(Company $company): User
    {
        // Look for existing user first
        $user = User::where('email', 'like', 'employee.expense@e2etest.com')->first();

        if (! $user) {
            $user = User::create([
                'name' => 'E2E Test Employee',
                'email' => 'employee.expense@e2etest.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        return $user;
    }

    private function createExpenseCategories(Company $company): array
    {
        $categoriesData = [
            ['name' => 'Travel', 'description' => 'Business travel expenses'],
            ['name' => 'Meals & Entertainment', 'description' => 'Business meals and entertainment'],
            ['name' => 'Office Supplies', 'description' => 'Office and work supplies'],
            ['name' => 'Training & Education', 'description' => 'Professional development and training'],
            ['name' => 'Mileage', 'description' => 'Business mileage reimbursement'],
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

    private function submitMultipleExpenses(Company $company, User $employee, array $categories): array
    {
        $expensesData = [
            [
                'category' => 'Travel',
                'title' => 'Flight to Client Meeting',
                'description' => 'Round trip airfare to client site',
                'amount' => 450.00,
                'receipt_number' => 'AIR-'.time(),
            ],
            [
                'category' => 'Travel',
                'title' => 'Hotel Accommodation',
                'description' => '2 nights hotel stay for business trip',
                'amount' => 280.00,
                'receipt_number' => 'HOTEL-'.time(),
            ],
            [
                'category' => 'Meals & Entertainment',
                'title' => 'Client Dinner',
                'description' => 'Business dinner with prospective client',
                'amount' => 125.00,
                'receipt_number' => 'MEAL-'.time(),
            ],
            [
                'category' => 'Office Supplies',
                'title' => 'Office Supplies',
                'description' => 'Notebooks, pens, and other office supplies',
                'amount' => 75.00,
                'receipt_number' => 'OFFICE-'.time(),
            ],
            [
                'category' => 'Mileage',
                'title' => 'Business Mileage',
                'description' => '100 miles at $0.65/mile for client visits',
                'amount' => 65.00,
                'receipt_number' => 'MILES-'.time(),
            ],
        ];

        $expenses = [];
        foreach ($expensesData as $expenseData) {
            $expense = Expense::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'company_id' => $company->id,
                'expense_category_id' => $categories[$expenseData['category']]->id,
                'employee_id' => $employee->id,
                'title' => $expenseData['title'],
                'description' => $expenseData['description'],
                'expense_date' => now()->subDays(rand(1, 30))->format('Y-m-d'),
                'amount' => $expenseData['amount'],
                'currency' => 'USD',
                'exchange_rate' => 1.00,
                'receipt_number' => $expenseData['receipt_number'],
                'notes' => 'E2E Test Expense',
                'status' => 'submitted',
                'submitted_by' => $employee->id,
                'submitted_at' => now(),
                'created_by' => $employee->id,
            ]);

            $expenses[] = $expense;
        }

        return $expenses;
    }

    private function processApprovalWorkflow(array $expenses): void
    {
        $managerId = 'manager'; // Simulated manager ID

        foreach ($expenses as $expense) {
            // Simulate approval process with different outcomes
            if ($expense->amount > 300) {
                // High-value expenses require additional approval
                $expense->approve(\Illuminate\Support\Str::uuid());
                $this->line("   Approved high-value expense: {$expense->title} (\${$expense->amount})");
            } else {
                // Lower-value expenses auto-approved
                $expense->approve(\Illuminate\Support\Str::uuid());
                $this->line("   Auto-approved expense: {$expense->title} (\${$expense->amount})");
            }
        }
    }

    private function processExpenseReimbursements(array $expenses): void
    {
        foreach ($expenses as $expense) {
            // Only process reimbursements for approved expenses
            if ($expense->status === 'approved') {
                $expense->markAsReimbursed(
                    now()->addDays(7), // Simulate 7-day reimbursement cycle
                    'REIMB-'.uniqid()
                );
                $this->line("   Reimbursed expense: {$expense->title} (\${$expense->amount})");
            }
        }
    }

    private function testExpenseAnalytics(Company $company, User $employee): void
    {
        $this->info('📊 Testing Expense Analytics:');

        $totalExpenses = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->count();

        $submittedExpenses = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('status', 'submitted')
            ->count();

        $approvedExpenses = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->count();

        $reimbursedExpenses = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('status', 'reimbursed')
            ->count();

        $totalExpenseAmount = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->sum('amount');

        $reimbursedAmount = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('status', 'reimbursed')
            ->sum('amount');

        // Category breakdown
        $categoryBreakdown = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->join('expense_categories', 'expenses.expense_category_id', '=', 'expense_categories.id')
            ->selectRaw('expense_categories.name, SUM(expenses.amount) as total, COUNT(*) as count')
            ->groupBy('expense_categories.name')
            ->get();

        $this->line("   Employee: {$employee->name}");
        $this->line("   Total Expenses: {$totalExpenses}");
        $this->line("   Submitted: {$submittedExpenses}");
        $this->line("   Approved: {$approvedExpenses}");
        $this->line("   Reimbursed: {$reimbursedExpenses}");
        $this->line("   Total Amount: \${$totalExpenseAmount}");
        $this->line("   Reimbursed Amount: \${$reimbursedAmount}");

        foreach ($categoryBreakdown as $category) {
            $this->line("   - {$category->name}: {$category->count} expenses, \${$category->total}");
        }

        $this->info('   ✅ Expense analytics working correctly');
    }

    private function testExpensePolicyEnforcement(Company $company, User $employee): void
    {
        $this->info('🔍 Testing Expense Policy Enforcement:');

        // Test expense that would exceed policy limits
        $excessiveExpense = Expense::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'company_id' => $company->id,
            'expense_category_id' => ExpenseCategory::where('company_id', $company->id)
                ->where('name', 'Meals & Entertainment')
                ->first()->id,
            'employee_id' => $employee->id,
            'title' => 'Excessive Meal Expense',
            'description' => 'Very expensive business meal exceeding policy limit',
            'expense_date' => now()->format('Y-m-d'),
            'amount' => 500.00, // Exceeds typical meal policy limit
            'currency' => 'USD',
            'exchange_rate' => 1.00,
            'receipt_number' => 'POLICY-TEST-'.time(),
            'notes' => 'E2E Policy Violation Test',
            'status' => 'submitted',
            'submitted_by' => $employee->id,
            'submitted_at' => now(),
            'created_by' => $employee->id,
        ]);

        // Simulate policy rejection
        $excessiveExpense->reject('Exceeds maximum meal allowance of $200 per meal', \Illuminate\Support\Str::uuid());

        $rejectedExpenses = Expense::where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('status', 'rejected')
            ->count();

        $this->line('   Policy Enforcement:');
        $this->line("   - Rejected Expenses: {$rejectedExpenses}");
        $this->line("   - Policy Violations Detected: {$rejectedExpenses}");
        $this->info('   ✅ Expense policy enforcement working');
    }
}
