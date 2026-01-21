<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use App\Constants\Permissions;
use App\Http\Requests\CompanyStoreRequest;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Services\CompanyBootstrapService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use App\Services\RolePermissionSynchronizer;
use App\Modules\Accounting\Services\DashboardService;
use App\Modules\FuelStation\Services\FuelDashboardService;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    /**
     * Show the company creation form.
     */
    public function create(Request $request): Response
    {
        // Get available currencies
        $currencies = DB::table('public.currencies')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        $industries = DB::table('acct.industry_coa_packs')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'description']);

        // Get countries with currency/timezone mappings
        $countries = collect(config('countries', []))
            ->map(fn ($country) => [
                'code' => $country['code'],
                'name' => $country['name'],
                'currency' => $country['currency'],
                'timezone' => $country['timezone'],
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return Inertia::render('companies/Create', [
            'currencies' => $currencies,
            'countries' => $countries,
            'industries' => $industries,
        ]);
    }

    public function store(CompanyStoreRequest $request, CompanyContextService $companyContext): RedirectResponse
    {
        $data = $request->validated();
        $data['industry_code'] = strtolower($data['industry_code']);
        $data['base_currency'] = strtoupper($data['base_currency']);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $company = DB::transaction(function () use ($data, $companyContext) {
            $company = Company::create([
                'name' => $data['name'],
                'industry_code' => $data['industry_code'] ?? null,
                'industry' => null,
                'slug' => $data['slug'],
                'country' => $data['country'],
                'country_id' => $data['country_id'] ?? null,
                'base_currency' => $data['base_currency'],
                'timezone' => $data['timezone'] ?? null,
                'language' => $data['language'] ?? 'en',
                'locale' => $data['locale'] ?? 'en_US',
                'settings' => $data['settings'] ?? null,
                'created_by_user_id' => Auth::id(),
                'is_active' => true,
            ]);

            CompanyCurrency::updateOrCreate(
                ['company_id' => $company->id, 'currency_code' => $data['base_currency']],
                ['is_base' => true, 'enabled_at' => now()]
            );

            // Create roles for the new company BEFORE assigning owner role
            $roleMatrix = config('role-permissions', []);
            if (!empty($roleMatrix)) {
                $syncer = app(RolePermissionSynchronizer::class);
                $syncer->syncForCompany($company, $roleMatrix);
            }

            if (Auth::check()) {
                DB::table('auth.company_user')->updateOrInsert(
                    [
                        'company_id' => $company->id,
                        'user_id' => Auth::id(),
                    ],
                    [
                        'role' => 'owner',
                        'invited_by_user_id' => Auth::id(),
                        'joined_at' => now(),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                // Assign the spatie role so permission checks succeed
                $companyContext->withContext($company, function () use ($companyContext) {
                    $user = Auth::user();
                    if ($user) {
                        $companyContext->assignRole($user, 'owner');
                    }
                });
            }

            return $company;
        });

        $bootstrapFailed = false;
        try {
            app(CompanyBootstrapService::class)->bootstrap($company, $data['industry_code'], Auth::id());
        } catch (\Throwable $e) {
            $bootstrapFailed = true;
            Log::error('Company bootstrap failed', [
                'company_id' => $company->id,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        $redirect = redirect("/{$company->slug}")
            ->with('success', 'Company created! You can start working right away.');

        if ($bootstrapFailed) {
            $redirect->with('error', 'Company created, but some defaults could not be prepared. Visit Settings to review setup.');
        }

        return $redirect;
    }

    /**
     * Show the company settings page.
     */
    public function settings(Request $request): Response
    {
        $company = CompanyContext::getCompany();
        $user = $request->user();

        // Get current user's role
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        $settings = $company->settings ?? [];
        $industryName = null;
        if ($company->industry_code) {
            $industryName = DB::table('acct.industry_coa_packs')
                ->where('code', $company->industry_code)
                ->value('name');
        }

        return Inertia::render('company/Settings', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'industry' => $company->industry,
                'industry_code' => $company->industry_code ?? null,
                'industry_name' => $industryName,
                'country' => $company->country,
                'base_currency' => $company->base_currency,
                'is_active' => $company->is_active,
                'created_at' => optional($company->created_at)->toISOString(),
                'settings' => $settings,
                'current_user_role' => $currentUserRole,
                'can_manage_company' => $user?->hasCompanyPermission(Permissions::COMPANY_UPDATE) ?? false,
                'can_manage_users' => $user?->hasCompanyPermission(Permissions::COMPANY_MANAGE_USERS) ?? false,
            ],
            'currentUserRole' => $currentUserRole,
        ]);
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        // Guard: only company members (or god-mode) may view
        $isGodMode = str_starts_with(Auth::id() ?? '', '00000000-0000-0000-0000-');
        $isMember = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->exists();

        if (! $isGodMode && ! $isMember) {
            abort(403, 'You are not a member of this company.');
        }

        // Get user statistics
        $userStats = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->selectRaw('
                COUNT(*) as total_users,
                SUM(CASE WHEN is_active = true THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) as admins
            ', ['admin'])
            ->first();

        // Get all users with details
        $users = DB::table('auth.company_user as cu')
            ->join('auth.users as u', 'cu.user_id', '=', 'u.id')
            ->where('cu.company_id', $company->id)
            ->select(
                'u.id',
                'u.name',
                'u.email',
                'cu.role',
                'cu.is_active',
                'cu.joined_at'
            )
            ->orderBy('cu.joined_at', 'desc')
            ->get();

        // Get current user's role
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        // Get pending invitations for this company (for inviter view)
        $pendingInvitations = [];
        if ($currentUserRole === 'owner') {
            $pendingInvitations = DB::table('auth.company_invitations as ci')
                ->leftJoin('auth.users as inviter', 'ci.invited_by_user_id', '=', 'inviter.id')
                ->where('ci.company_id', $company->id)
                ->where('ci.status', 'pending')
                ->where('ci.expires_at', '>', now())
                ->select(
                    'ci.id',
                    'ci.token',
                    'ci.email',
                    'ci.role',
                    'ci.expires_at',
                    'ci.created_at',
                    'inviter.name as inviter_name',
                    'inviter.email as inviter_email'
                )
                ->orderBy('ci.created_at', 'desc')
                ->get();
        }

        // Financial snapshot (AR only; AP/expenses placeholder)
        $openInvoices = DB::table('acct.invoices')
            ->where('company_id', $company->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['paid', 'void', 'cancelled']);

        $arOutstanding = (clone $openInvoices)->sum('balance');
        $arOutstandingCount = (clone $openInvoices)->count();

        $arOverdueQuery = (clone $openInvoices)->where('due_date', '<', now()->toDateString());
        $arOverdue = (clone $arOverdueQuery)->sum('balance');
        $arOverdueCount = (clone $arOverdueQuery)->count();

        $paymentsMTD = DB::table('acct.payments')
            ->where('company_id', $company->id)
            ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum(DB::raw('COALESCE(base_amount, amount)'));

        // Aging buckets
        $agingBuckets = DB::table('acct.invoices')
            ->selectRaw("
                SUM(CASE WHEN due_date >= current_date THEN balance ELSE 0 END) as current_bucket,
                SUM(CASE WHEN due_date < current_date AND due_date >= current_date - interval '30 days' THEN balance ELSE 0 END) as bucket_1_30,
                SUM(CASE WHEN due_date < current_date - interval '30 days' AND due_date >= current_date - interval '60 days' THEN balance ELSE 0 END) as bucket_31_60,
                SUM(CASE WHEN due_date < current_date - interval '60 days' AND due_date >= current_date - interval '90 days' THEN balance ELSE 0 END) as bucket_61_90,
                SUM(CASE WHEN due_date < current_date - interval '90 days' THEN balance ELSE 0 END) as bucket_90_plus
            ")
            ->where('company_id', $company->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->first();

        $quickStats = [
            'invoices_sent_this_month' => DB::table('acct.invoices')
                ->where('company_id', $company->id)
                ->whereBetween('sent_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'payments_received_this_month' => DB::table('acct.payments')
                ->where('company_id', $company->id)
                ->whereBetween('payment_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'new_customers_this_month' => DB::table('acct.customers')
                ->where('company_id', $company->id)
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
        ];

        // Recent activity (payments, invoices, customers)
        $recentPayments = DB::table('acct.payments as p')
            ->join('acct.customers as c', 'c.id', '=', 'p.customer_id')
            ->where('p.company_id', $company->id)
            ->orderByDesc('p.payment_date')
            ->limit(5)
            ->get([
                'p.payment_date as occurred_at',
                'p.amount',
                'p.currency',
                'c.name as customer_name',
                'p.payment_number',
            ])
            ->map(function ($p) {
                return [
                    'type' => 'payment',
                    'label' => "Payment {$p->payment_number} from {$p->customer_name}",
                    'amount' => (float) $p->amount,
                    'currency' => $p->currency,
                    'occurred_at' => $p->occurred_at,
                ];
            });

        $recentInvoices = DB::table('acct.invoices as i')
            ->join('acct.customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.company_id', $company->id)
            ->orderByDesc('i.invoice_date')
            ->limit(5)
            ->get([
                'i.invoice_date as occurred_at',
                'i.invoice_number',
                'c.name as customer_name',
                'i.status',
            ])
            ->map(function ($i) {
                return [
                    'type' => 'invoice',
                    'label' => "Invoice {$i->invoice_number} for {$i->customer_name}",
                    'status' => $i->status,
                    'occurred_at' => $i->occurred_at,
                ];
            });

        $recentCustomers = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['name', 'created_at'])
            ->map(function ($c) {
                return [
                    'type' => 'customer',
                    'label' => "New customer: {$c->name}",
                    'occurred_at' => $c->created_at,
                ];
            });

        $recentActivity = $recentPayments
            ->concat($recentInvoices)
            ->concat($recentCustomers)
            ->sortByDesc('occurred_at')
            ->take(7)
            ->values();

        $settings = $company->settings ?? [];
        $industryName = null;
        if ($company->industry_code) {
            $industryName = DB::table('acct.industry_coa_packs')
                ->where('code', $company->industry_code)
                ->value('name');
        }

        // Fetch new Dashboard Data
        $cashPosition = $this->dashboardService->getCashPosition($company->id);
        $moneyInOut = $this->dashboardService->getMoneyInOut($company->id);
        $needsAttention = $this->dashboardService->getNeedsAttention($company->id);
        $profitLoss = $this->dashboardService->getProfitLossSummary($company->id);

        $isFuelStation = $company->isModuleEnabled('fuel_station')
            || ($company->industry_code ?? null) === 'fuel_station'
            || ($company->industry ?? null) === 'fuel_station';

        $fuelDashboard = null;
        if ($isFuelStation) {
            try {
                $fuelDashboard = app(FuelDashboardService::class)->getHomeCards($company->id);
            } catch (\Throwable $e) {
                Log::warning('Fuel dashboard home cards failed', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
                $fuelDashboard = null;
            }
        }

        $fuelTanks = [];
        if ($isFuelStation) {
            try {
                $fuelTanks = Warehouse::where('company_id', $company->id)
                    ->where('warehouse_type', 'tank')
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'capacity', 'linked_item_id'])
                    ->toArray();
            } catch (\Throwable $e) {
                Log::warning('Fuel tanks lookup failed', [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);
                $fuelTanks = [];
            }
        }

        return Inertia::render('company/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'is_active' => $company->is_active,
                'created_at' => $company->created_at,
                'industry' => $company->industry,
                'industry_code' => $company->industry_code,
                'industry_name' => $industryName,
                'country' => $company->country,
                'language' => $company->language ?? 'en',
                'locale' => $company->locale ?? 'en_US',
                'fiscal_year_start_month' => $company->fiscal_year_start_month ?? 1,
            ],
            'stats' => [
                'total_users' => $userStats->total_users ?? 0,
                'active_users' => $userStats->active_users ?? 0,
                'admins' => $userStats->admins ?? 0,
            ],
            'users' => $users,
            'currentUserRole' => $currentUserRole,
            'pendingInvitations' => $pendingInvitations,
            'financials' => [
                'ar_outstanding' => (float) $arOutstanding,
                'ar_outstanding_count' => $arOutstandingCount,
                'ar_overdue' => (float) $arOverdue,
                'ar_overdue_count' => $arOverdueCount,
                'payments_mtd' => (float) $paymentsMTD,
                'expenses_mtd_placeholder' => 'Not implemented yet',
                'aging' => [
                    'current' => (float) ($agingBuckets->current_bucket ?? 0),
                    'bucket_1_30' => (float) ($agingBuckets->bucket_1_30 ?? 0),
                    'bucket_31_60' => (float) ($agingBuckets->bucket_31_60 ?? 0),
                    'bucket_61_90' => (float) ($agingBuckets->bucket_61_90 ?? 0),
                    'bucket_90_plus' => (float) ($agingBuckets->bucket_90_plus ?? 0),
                ],
                'quick_stats' => $quickStats,
                'recent_activity' => $recentActivity,
            ],
            // New Dashboard Data Structure
            'dashboard' => [
                'cash_position' => $cashPosition,
                'money_in_out' => $moneyInOut,
                'needs_attention' => $needsAttention,
                'profit_loss' => $profitLoss,
            ],
            'isFuelStation' => $isFuelStation,
            'fuelDashboard' => $fuelDashboard,
            'fuelTanks' => $fuelTanks,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        // Company metadata (name/industry/base currency) is immutable after creation
        abort(403, 'Company details cannot be edited after creation.');
    }

    /**
     * Update company settings (partial update for editable fields).
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        // Check if user is owner or admin
        $currentUserRole = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->value('role');

        if (! in_array($currentUserRole, ['owner', 'admin'])) {
            abort(403, 'Only company owners and admins can update settings.');
        }

        // Validate the request - only allow specific fields to be updated
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'language' => 'sometimes|string|max:10',
            'locale' => 'sometimes|string|max:10',
            'fiscal_year_start_month' => 'sometimes|integer|min:1|max:12',
            'auto_create_fiscal_year' => 'sometimes|boolean',
            'default_period_type' => 'sometimes|string|in:monthly,quarterly,yearly',
        ]);

        // Update allowed direct fields
        $directUpdates = [];
        if (isset($validated['name'])) {
            $directUpdates['name'] = $validated['name'];
        }
        if (isset($validated['language'])) {
            $directUpdates['language'] = $validated['language'];
        }
        if (isset($validated['locale'])) {
            $directUpdates['locale'] = $validated['locale'];
        }

        // Handle fiscal year start month as direct column
        if (isset($validated['fiscal_year_start_month'])) {
            $directUpdates['fiscal_year_start_month'] = $validated['fiscal_year_start_month'];
        }

        // Handle other fiscal year settings in settings JSON
        $settings = $company->settings ?? [];
        $settingsUpdated = false;

        if (isset($validated['auto_create_fiscal_year'])) {
            $settings['auto_create_fiscal_year'] = $validated['auto_create_fiscal_year'];
            $settingsUpdated = true;
        }

        if (isset($validated['default_period_type'])) {
            $settings['default_period_type'] = $validated['default_period_type'];
            $settingsUpdated = true;
        }

        if ($settingsUpdated) {
            $directUpdates['settings'] = $settings;
        }

        if (! empty($directUpdates)) {
            $company->update($directUpdates);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function destroy(string $companyId): JsonResponse
    {
        $company = Company::findOrFail($companyId);

        // Check if user is owner
        $membership = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $membership || $membership->role !== 'owner') {
            abort(403, 'Only company owners can delete companies.');
        }

        $commandBus = app(CommandBus::class);
        $result = $commandBus->dispatch('company.delete', ['id' => $companyId], $request->user());

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Company deleted successfully.',
        ]);
    }

    /**
     * Get company's default tax code (JSON API for TaxToggle fallback)
     */
    public function taxDefault(): JsonResponse
    {
        $company = CompanyContext::getCompany();

        $defaultTaxRate = \App\Modules\Accounting\Models\TaxRate::where('company_id', $company->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first(['id', 'name', 'code', 'rate']);

        return response()->json([
            'tax_code' => $defaultTaxRate ? [
                'id' => $defaultTaxRate->id,
                'name' => $defaultTaxRate->name,
                'code' => $defaultTaxRate->code,
                'rate' => (float) $defaultTaxRate->rate,
            ] : null,
        ]);
    }
}
