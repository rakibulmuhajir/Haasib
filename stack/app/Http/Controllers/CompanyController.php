<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ContextService $contextService
    ) {}

    /**
     * Get all companies accessible to the current user.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $companies = $user->getActiveCompanies();

        // For API requests, return JSON
        if ($request->expectsJson()) {
            $mappedCompanies = $companies->map(function ($company) use ($user) {
                $companyUser = $company->users()->where('user_id', $user->id)->first();

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'industry' => $company->industry,
                    'country' => $company->country,
                    'currency' => $company->currency,
                    'is_active' => $company->is_active,
                    'created_at' => $company->created_at,
                    'user_role' => $companyUser?->role ?? 'member',
                    'is_active_in_company' => $companyUser?->is_active ?? false,
                ];
            });

            return response()->json([
                'data' => $mappedCompanies,
                'meta' => [
                    'total' => $mappedCompanies->count(),
                    'per_page' => $mappedCompanies->count(),
                    'current_page' => 1,
                    'last_page' => 1,
                ],
            ]);
        }

        // For web requests, return Inertia view
        return Inertia::render('Companies/Index', [
            'companies' => $companies->map(function ($company) use ($user) {
                $companyUser = $company->users()->where('user_id', $user->id)->first();

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'industry' => $company->industry,
                    'country' => $company->country,
                    'currency' => $company->currency,
                    'is_active' => $company->is_active,
                    'created_at' => $company->created_at,
                    'user_role' => $companyUser?->role ?? 'member',
                    'is_active_in_company' => $companyUser?->is_active ?? false,
                ];
            }),
        ]);
    }

    /**
     * Show the form for creating a new company.
     */
    public function create(Request $request)
    {
        return Inertia::render('Companies/Create', [
            'currencies' => $this->getCurrencies(),
            'countries' => $this->getCountries(),
            'timezones' => $this->getTimezones(),
            'industries' => $this->getIndustries(),
        ]);
    }

    /**
     * Create a new company.
     */
    public function store(Request $request)
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
            $slug = $originalSlug.'-'.$counter;
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

        $response = [
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
        ];

        // Check if this is an Inertia request (coming from the web form)
        if ($request->header('X-Inertia')) {
            // For Inertia requests, redirect to the company list with a flash message
            return redirect()->route('companies.index')
                ->with('success', 'Company created successfully!');
        }

        // For API requests, return JSON response
        return response()->json($response, 201);
    }

    /**
     * Show a specific company.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();

        try {
            $company = Company::find($id);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Invalid company ID format',
                ], 422);
            }
            abort(422, 'Invalid company ID format');
        }

        if (! $company) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Company not found',
                ], 404);
            }
            abort(404, 'Company not found');
        }

        if (! $this->authService->canAccessCompany($user, $company)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied to this company',
                ], 403);
            }
            abort(403, 'Access denied to this company');
        }

        // Get fiscal year information
        $fiscalYear = \DB::table('acct.fiscal_years')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->first();

        // Get user's role for this company
        $userRole = $this->authService->getUserRole($user, $company);

        // Get company users
        $users = $company->users()->withPivot(['role', 'is_active', 'created_at'])->get();

        // Get company invitations
        $invitations = $company->invitations()->where('status', 'pending')->get();

        $companyData = [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'industry' => $company->industry,
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
                'is_current' => $fiscalYear->is_active,
            ] : null,
            'user_role' => $userRole,
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'is_active' => $user->pivot->is_active,
                    'joined_at' => $user->pivot->created_at,
                ];
            }),
            'invitations' => $invitations->map(function ($invitation) {
                return [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                    'status' => $invitation->status,
                    'expires_at' => $invitation->expires_at,
                    'created_at' => $invitation->created_at,
                ];
            }),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $companyData]);
        }

        return Inertia::render('Companies/Show', ['company' => $companyData]);
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
            'name' => now()->year.' Fiscal Year',
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
            'name' => $name.' Group',
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
            'description' => 'Default '.$name.' account',
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

        if (! $company) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        if (! $company->is_active) {
            return response()->json([
                'message' => 'Company is inactive',
            ], 400);
        }

        if (! $this->authService->canAccessCompany($user, $company)) {
            return response()->json([
                'message' => 'Access denied to this company',
            ], 403);
        }

        $success = $this->contextService->setCurrentCompany($user, $company);

        if (! $success) {
            \Log::error('Failed to set current company', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'company_name' => $company->name
            ]);
            
            return response()->json([
                'message' => 'Failed to switch company',
            ], 500);
        }

        // Verify the context was actually set
        $currentCompany = $this->contextService->getCurrentCompany($user);
        
        \Log::info('Company switched successfully', [
            'user_id' => $user->id,
            'target_company_id' => $company->id,
            'target_company_name' => $company->name,
            'current_company_id' => $currentCompany?->id,
            'current_company_name' => $currentCompany?->name,
            'context_matches' => $currentCompany?->id === $company->id
        ]);

        return response()->json([
            'message' => 'Company switched successfully',
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'role' => $this->authService->getUserRole($user, $company),
            ],
            'debug' => [
                'current_company_id' => $currentCompany?->id,
                'context_matches' => $currentCompany?->id === $company->id
            ]
        ]);
    }

    /**
     * Switch the active company for the current user using URL parameter.
     */
    public function switchByUrl(Request $request, string $company): JsonResponse
    {
        $user = $request->user();

        try {
            $companyModel = Company::find($company);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'Invalid company ID format',
            ], 422);
        }

        if (! $companyModel) {
            return response()->json([
                'message' => 'Company not found',
            ], 404);
        }

        if (! $companyModel->is_active) {
            return response()->json([
                'message' => 'Company is inactive',
            ], 400);
        }

        if (! $this->authService->canAccessCompany($user, $companyModel)) {
            return response()->json([
                'message' => 'Access denied to this company',
            ], 403);
        }

        $success = $this->contextService->setCurrentCompany($user, $companyModel);

        if (! $success) {
            return response()->json([
                'message' => 'Failed to switch company',
            ], 500);
        }

        return response()->json([
            'message' => 'Company switched successfully',
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'role' => $this->authService->getUserRole($user, $companyModel),
            ],
        ]);
    }

    /**
     * Get list of currencies for the create form.
     */
    private function getCurrencies(): array
    {
        return [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$'],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'Fr'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥'],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹'],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => '₨'],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'د.إ'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => '﷼'],
        ];
    }

    /**
     * Get list of countries for the create form.
     */
    private function getCountries(): array
    {
        return [
            ['code' => 'US', 'name' => 'United States'],
            ['code' => 'GB', 'name' => 'United Kingdom'],
            ['code' => 'CA', 'name' => 'Canada'],
            ['code' => 'AU', 'name' => 'Australia'],
            ['code' => 'DE', 'name' => 'Germany'],
            ['code' => 'FR', 'name' => 'France'],
            ['code' => 'JP', 'name' => 'Japan'],
            ['code' => 'CN', 'name' => 'China'],
            ['code' => 'IN', 'name' => 'India'],
            ['code' => 'PK', 'name' => 'Pakistan'],
            ['code' => 'AE', 'name' => 'United Arab Emirates'],
            ['code' => 'SA', 'name' => 'Saudi Arabia'],
            ['code' => 'EG', 'name' => 'Egypt'],
            ['code' => 'JO', 'name' => 'Jordan'],
            ['code' => 'LB', 'name' => 'Lebanon'],
        ];
    }

    /**
     * Get list of timezones for the create form.
     */
    private function getTimezones(): array
    {
        return [
            ['value' => 'UTC', 'label' => 'UTC'],
            ['value' => 'America/New_York', 'label' => 'Eastern Time (US & Canada)'],
            ['value' => 'America/Chicago', 'label' => 'Central Time (US & Canada)'],
            ['value' => 'America/Denver', 'label' => 'Mountain Time (US & Canada)'],
            ['value' => 'America/Los_Angeles', 'label' => 'Pacific Time (US & Canada)'],
            ['value' => 'Europe/London', 'label' => 'London'],
            ['value' => 'Europe/Paris', 'label' => 'Paris'],
            ['value' => 'Europe/Berlin', 'label' => 'Berlin'],
            ['value' => 'Asia/Tokyo', 'label' => 'Tokyo'],
            ['value' => 'Asia/Shanghai', 'label' => 'Shanghai'],
            ['value' => 'Asia/Karachi', 'label' => 'Karachi'],
            ['value' => 'Asia/Dubai', 'label' => 'Dubai'],
            ['value' => 'Asia/Riyadh', 'label' => 'Riyadh'],
            ['value' => 'Africa/Cairo', 'label' => 'Cairo'],
            ['value' => 'Asia/Amman', 'label' => 'Amman'],
        ];
    }

    /**
     * Get list of industries for the create form.
     */
    private function getIndustries(): array
    {
        return [
            ['value' => 'technology', 'label' => 'Technology'],
            ['value' => 'healthcare', 'label' => 'Healthcare'],
            ['value' => 'finance', 'label' => 'Finance & Banking'],
            ['value' => 'retail', 'label' => 'Retail & E-commerce'],
            ['value' => 'manufacturing', 'label' => 'Manufacturing'],
            ['value' => 'consulting', 'label' => 'Consulting'],
            ['value' => 'education', 'label' => 'Education'],
            ['value' => 'real_estate', 'label' => 'Real Estate'],
            ['value' => 'construction', 'label' => 'Construction'],
            ['value' => 'transportation', 'label' => 'Transportation & Logistics'],
            ['value' => 'food_beverage', 'label' => 'Food & Beverage'],
            ['value' => 'media_entertainment', 'label' => 'Media & Entertainment'],
            ['value' => 'energy', 'label' => 'Energy'],
            ['value' => 'agriculture', 'label' => 'Agriculture'],
            ['value' => 'government', 'label' => 'Government'],
            ['value' => 'non_profit', 'label' => 'Non-Profit'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }

    /**
     * Handle bulk operations on companies.
     */
    public function bulk(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|in:delete,export,activate,deactivate',
            'company_ids' => 'required|array',
            'company_ids.*' => 'required|string|exists:companies,id',
        ]);

        $user = $request->user();
        $action = $request->input('action');
        $companyIds = $request->input('company_ids');

        // Get companies that the user has access to and proper permissions for
        $companies = Company::whereIn('id', $companyIds)
            ->whereHas('users', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('role', 'owner')
                    ->where('is_active', true);
            })
            ->get();

        if ($companies->isEmpty()) {
            return response()->json([
                'message' => 'No valid companies found for the requested action',
                'processed' => 0,
            ], 403);
        }

        $processedCount = 0;

        switch ($action) {
            case 'delete':
                foreach ($companies as $company) {
                    // Additional validation before deletion
                    if ($company->users()->count() > 1) {
                        continue; // Skip companies with multiple users
                    }

                    // Soft delete by marking as inactive
                    $company->is_active = false;
                    $company->save();
                    $processedCount++;
                }
                break;

            case 'activate':
                foreach ($companies as $company) {
                    $company->is_active = true;
                    $company->save();
                    $processedCount++;
                }
                break;

            case 'deactivate':
                foreach ($companies as $company) {
                    $company->is_active = false;
                    $company->save();
                    $processedCount++;
                }
                break;

            case 'export':
                // For export, we'll just return the data
                $exportData = $companies->map(function ($company) use ($user) {
                    $companyUser = $company->users()->where('user_id', $user->id)->first();

                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'slug' => $company->slug,
                        'industry' => $company->industry,
                        'country' => $company->country,
                        'currency' => $company->currency,
                        'is_active' => $company->is_active,
                        'created_at' => $company->created_at->format('Y-m-d H:i:s'),
                        'user_role' => $companyUser?->role ?? 'member',
                    ];
                });

                return response()->json([
                    'data' => $exportData,
                    'count' => $exportData->count(),
                    'message' => "Successfully prepared {$exportData->count()} companies for export",
                ]);
        }

        return response()->json([
            'message' => "Successfully processed {$processedCount} companies",
            'processed' => $processedCount,
            'requested' => count($companyIds),
        ]);
    }
}
