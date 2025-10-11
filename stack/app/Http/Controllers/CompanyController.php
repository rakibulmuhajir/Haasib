<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ContextService $contextService
    ) {}

    /**
     * Get all companies accessible to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companies = $user->getActiveCompanies();

        return response()->json([
            'companies' => $companies->map(function ($company) use ($user) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'role' => $this->authService->getUserRole($user, $company),
                    'is_active' => $company->pivot->is_active ?? true,
                ];
            }),
        ]);
    }

    /**
     * Create a new company.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
            'timezone' => 'required|string|timezone',
            'country' => 'nullable|string|size:2',
            'language' => 'nullable|string|max:10',
            'locale' => 'nullable|string|max:10',
        ]);

        $user = $request->user();

        // Generate a unique slug
        $slug = \Str::slug($request->name);
        $originalSlug = $slug;
        $counter = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $company = Company::create([
            'name' => $request->name,
            'slug' => $slug,
            'currency' => $request->currency,
            'timezone' => $request->timezone,
            'country' => $request->country,
            'language' => $request->language ?? 'en',
            'locale' => $request->locale ?? 'en_US',
            'is_active' => true,
        ]);

        // Assign the user as owner
        $company->users()->attach($user->id, [
            'role' => 'owner',
            'is_active' => true,
        ]);

        // Create fiscal year and chart of accounts
        $this->createCompanyAccountingStructure($company);

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
                'country' => $company->country,
                'language' => $company->language,
                'locale' => $company->locale,
                'is_active' => $company->is_active,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
            ],
            'meta' => [
                'fiscal_year_created' => true,
                'chart_of_accounts_created' => true,
            ],
        ], 201);
    }

    /**
     * Show a specific company.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        try {
            $company = Company::find($id);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Invalid company ID format',
            ], 422);
        }

        if (!$company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        if (!$this->authService->canAccessCompany($user, $company)) {
            return response()->json([
                'message' => 'Access denied to this company',
            ], 403);
        }

        // Get fiscal year information
        $fiscalYear = \DB::table('acct.fiscal_years')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        // Get user's role for this company
        $userRole = $this->authService->getUserRole($user, $company);

        return response()->json([
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'currency' => $company->currency,
                'timezone' => $company->timezone,
                'country' => $company->country,
                'language' => $company->language,
                'locale' => $company->locale,
                'is_active' => $company->is_active,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at,
                'fiscal_year' => $fiscalYear ? [
                    'id' => $fiscalYear->id,
                    'name' => $fiscalYear->name,
                    'start_date' => $fiscalYear->start_date,
                    'end_date' => $fiscalYear->end_date,
                    'is_current' => $fiscalYear->is_active
                ] : null,
                'user_role' => $userRole
            ],
        ]);
    }

    /**
     * Create fiscal year and chart of accounts for a new company.
     */
    private function createCompanyAccountingStructure(Company $company): void
    {
        // Create fiscal year
        \DB::table('acct.fiscal_years')->insert([
            'id' => \Str::uuid(),
            'company_id' => $company->id,
            'name' => now()->year . ' Fiscal Year',
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_active' => true,
            'is_locked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Get account classes (these are already created by the migration)
        $assetClass = \DB::table('acct.account_classes')->where('name', 'Assets')->first();
        $liabilityClass = \DB::table('acct.account_classes')->where('name', 'Liabilities')->first();
        $equityClass = \DB::table('acct.account_classes')->where('name', 'Equity')->first();
        $revenueClass = \DB::table('acct.account_classes')->where('name', 'Revenue')->first();
        $expenseClass = \DB::table('acct.account_classes')->where('name', 'Expenses')->first();

        // Create default accounts for the company
        if ($assetClass) {
            $this->createAccount($company, $assetClass, '1000', 'Cash', 'cash');
            $this->createAccount($company, $assetClass, '1100', 'Accounts Receivable', 'receivable');
            $this->createAccount($company, $assetClass, '1200', 'Inventory', 'inventory');
        }

        if ($liabilityClass) {
            $this->createAccount($company, $liabilityClass, '2000', 'Accounts Payable', 'payable');
            $this->createAccount($company, $liabilityClass, '2100', 'Accrued Expenses', 'payable');
        }

        if ($equityClass) {
            $this->createAccount($company, $equityClass, '3000', 'Owner\'s Equity', 'equity');
            $this->createAccount($company, $equityClass, '3100', 'Retained Earnings', 'equity');
        }

        if ($revenueClass) {
            $this->createAccount($company, $revenueClass, '4000', 'Sales Revenue', 'revenue');
            $this->createAccount($company, $revenueClass, '4100', 'Service Revenue', 'revenue');
        }

        if ($expenseClass) {
            $this->createAccount($company, $expenseClass, '5000', 'Cost of Goods Sold', 'expense');
            $this->createAccount($company, $expenseClass, '5100', 'Operating Expenses', 'expense');
        }
    }

    /**
     * Create a single account for a company
     */
    private function createAccount(Company $company, $accountClass, string $code, string $name, string $accountType): void
    {
        // Create account group if needed (using the account class as group)
        $groupId = \Str::uuid();
        \DB::table('acct.account_groups')->insert([
            'id' => $groupId,
            'account_class_id' => $accountClass->id,
            'name' => $name . ' Group',
            'order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create the account
        \DB::table('acct.accounts')->insert([
            'id' => \Str::uuid(),
            'company_id' => $company->id,
            'account_group_id' => $groupId,
            'code' => $code,
            'name' => $name,
            'description' => 'Default ' . $name . ' account',
            'normal_balance' => $accountClass->normal_balance,
            'is_active' => true,
            'allow_manual_entries' => true,
            'account_type' => $accountType,
            'currency' => $company->currency ?? 'USD',
            'opening_balance' => 0,
            'opening_balance_date' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Switch the active company for the current user.
     */
    public function switch(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|uuid',
        ]);

        $user = $request->user();

        try {
            $company = Company::find($request->company_id);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Invalid company ID format',
            ], 422);
        }

        if (!$company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        if (!$company->is_active) {
            return response()->json([
                'message' => 'Company is inactive',
            ], 400);
        }

        if (!$this->authService->canAccessCompany($user, $company)) {
            return response()->json([
                'message' => 'Access denied to this company',
            ], 403);
        }

        $success = $this->contextService->setCurrentCompany($user, $company);

        if (!$success) {
            return response()->json([
                'message' => 'Failed to switch company',
            ], 500);
        }

        return response()->json([
            'message' => 'Company switched successfully',
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'role' => $this->authService->getUserRole($user, $company),
            ],
        ]);
    }
}
