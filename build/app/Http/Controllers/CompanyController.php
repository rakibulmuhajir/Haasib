<?php

namespace App\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Requests\Company\UpdateCompanySettingsRequest;
use App\Http\Requests\CompanyStoreRequest;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\User;
use App\Modules\Accounting\Services\DashboardService;
use App\Modules\FuelStation\Services\FuelDashboardService;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CommandBus;
use App\Services\CompanyBootstrapService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        $canAssignOwner = $request->user()?->isGodMode() ?? false;

        // Get available currencies
        $currencies = DB::table('public.currencies')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        $industries = config('company-industries', []);

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

        $users = $canAssignOwner
            ? User::query()
                ->orderBy('name')
                ->orderBy('email')
                ->get(['id', 'name', 'email'])
            : collect();

        return Inertia::render('companies/Create', [
            'currencies' => $currencies,
            'countries' => $countries,
            'industries' => $industries,
            'users' => $users,
            'canAssignOwner' => $canAssignOwner,
        ]);
    }

    public function store(CompanyStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $owner = User::findOrFail($data['owner_user_id']);
        $data['industry_code'] = strtolower($data['industry_code']);
        $data['base_currency'] = strtoupper($data['base_currency']);
        $data['slug'] = $this->uniqueSlug(Str::slug($data['name']));

        $company = DB::transaction(function () use ($data, $owner) {
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

            DB::table('auth.company_user')->updateOrInsert(
                [
                    'company_id' => $company->id,
                    'user_id' => $owner->id,
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

            app(CompanyRbacBootstrapper::class)->bootstrap($company, $owner);

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

        $target = in_array($data['industry_code'], ['umrah', 'travel'], true)
            ? route('umrah.dashboard', ['company' => $company->slug])
            : "/{$company->slug}";

        $redirect = redirect($target)
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
                'logo_url' => $company->logo_url,
                'language' => $company->language,
                'locale' => $company->locale,
                'is_active' => $company->is_active,
                'created_at' => optional($company->created_at)->toISOString(),
                'settings' => $settings,
                'current_user_role' => $currentUserRole,
                'can_manage_company' => $user?->hasCompanyPermission(Permissions::COMPANY_UPDATE) ?? false,
                'can_manage_users' => $user?->hasCompanyPermission(Permissions::COMPANY_MANAGE_USERS) ?? false,
            ],
            'companyCurrencies' => CompanyCurrency::where('company_id', $company->id)->orderBy('currency_code')->get(),
            'availableCurrencies' => DB::table('public.currencies')->where('is_active', true)->orderBy('code')->get(['code', 'name', 'symbol']),
            'currentUserRole' => $currentUserRole,
        ]);
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $company = CompanyContext::getCompany();

        // Guard: only company members (or god-mode) may view
        $isGodMode = str_starts_with(Auth::id() ?? '', '00000000-0000-0000-0000-');
        $isMember = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->exists();

        if (! $isGodMode && ! $isMember) {
            abort(403, 'You are not a member of this company.');
        }

        $isUmrah = $company->isModuleEnabled('umrah')
            || in_array(($company->industry_code ?? null), ['umrah', 'travel'], true)
            || in_array(($company->industry ?? null), ['umrah', 'travel'], true);

        if ($isUmrah) {
            return redirect()->route('umrah.dashboard', ['company' => $company->slug]);
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
        $productDashboardDate = Carbon::today()->toDateString();
        if ($request->filled('product_date')) {
            try {
                $productDashboardDate = Carbon::parse((string) $request->input('product_date'))->toDateString();
            } catch (\Throwable) {
                $productDashboardDate = Carbon::today()->toDateString();
            }
        }
        if ($isFuelStation) {
            try {
                $fuelDashboard = app(FuelDashboardService::class)->getHomeCards($company->id, Carbon::parse($productDashboardDate));
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
            'isUmrah' => $isUmrah,
            'fuelDashboard' => $fuelDashboard,
            'productDashboardDate' => $productDashboardDate,
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
    public function updateSettings(UpdateCompanySettingsRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $validated = $request->validated();

        // Update allowed direct fields
        $directUpdates = [];
        if (isset($validated['name'])) {
            $directUpdates['name'] = $validated['name'];
        }
        if (array_key_exists('logo_url', $validated)) {
            $directUpdates['logo_url'] = $validated['logo_url'];
        }
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store("company-logos/{$company->id}", 'public');
            if (str_starts_with((string) $company->logo_url, '/storage/company-logos/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $company->logo_url));
            }
            $directUpdates['logo_url'] = Storage::url($path);
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

        foreach (['contact_email', 'contact_phone', 'website'] as $field) {
            if (array_key_exists($field, $validated)) {
                $settings[$field] = $validated[$field];
                $settingsUpdated = true;
            }
        }

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

    public function destroy(Request $request, string $companyId): RedirectResponse
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
        $result = $commandBus->dispatch('company.delete', ['slug' => $company->slug], $request->user());

        return redirect()
            ->route('companies.index')
            ->with('success', $result['message'] ?? 'Company deleted successfully.');
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

    private function uniqueSlug(string $base): string
    {
        $base = $base !== '' ? $base : 'company';
        $slug = $base;
        $counter = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
