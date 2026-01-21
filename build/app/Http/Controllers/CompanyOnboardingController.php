<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Requests\Onboarding\StoreBankAccountsRequest;
use App\Http\Requests\Onboarding\StoreCompanyIdentityRequest;
use App\Http\Requests\Onboarding\StoreDefaultAccountsRequest;
use App\Http\Requests\Onboarding\StoreFiscalYearRequest;
use App\Http\Requests\Onboarding\StoreNumberingRequest;
use App\Http\Requests\Onboarding\StorePaymentTermsRequest;
use App\Http\Requests\Onboarding\StoreTaxSettingsRequest;
use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IndustryCoaPack;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Modules\Accounting\Services\DefaultAccountProvisioner;
use App\Modules\FuelStation\Services\FuelStationModuleInstaller;
use App\Modules\FuelStation\Services\FuelStationOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CompanyOnboardingController extends Controller
{
    public function __construct(
        private CompanyOnboardingService $onboardingService,
        private FuelStationModuleInstaller $fuelStationInstaller,
        private FuelStationOnboardingService $fuelStationOnboarding,
    )
    {
    }

    /**
     * Show onboarding wizard start page.
     */
    public function index(): Response|RedirectResponse
    {
        $company = CompanyContext::getCompany();

        if ($company->industry_code === 'fuel_station') {
            return redirect("/{$company->slug}/fuel/onboarding");
        }

        $onboarding = $this->onboardingService->initializeOnboarding($company);

        // Redirect to current step
        return redirect("/{$company->slug}/onboarding/{$onboarding->current_step}");
    }

    /**
     * Step 1: Company Identity & Industry Selection
     */
    public function showCompanyIdentity(): Response
    {
        $company = CompanyContext::getCompany();

        $industries = IndustryCoaPack::active()
            ->orderBy('sort_order')
            ->get(['code', 'name', 'description']);

        $timezones = [
            'Asia/Karachi' => 'Pakistan Standard Time (PKT)',
            'UTC' => 'Coordinated Universal Time (UTC)',
            'America/New_York' => 'Eastern Time (ET)',
            'Europe/London' => 'Greenwich Mean Time (GMT)',
            'Asia/Dubai' => 'Gulf Standard Time (GST)',
            'Asia/Singapore' => 'Singapore Time (SGT)',
        ];

        return Inertia::render('onboarding/CompanyIdentity', [
            'company' => $company,
            'industries' => $industries,
            'timezones' => $timezones,
        ]);
    }

    public function storeCompanyIdentity(StoreCompanyIdentityRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $data = $request->validated();
        $industryCode = $company->industry_code ?: ($data['industry_code'] ?? null);

        if ($industryCode === 'fuel_station') {
            try {
                $this->fuelStationInstaller->ensureMigrationsApplied();
            } catch (\Throwable $e) {
                Log::error('FuelStation module migrations failed during onboarding', [
                    'company_id' => $company->id ?? null,
                    'company_slug' => $company->slug ?? null,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                return redirect()->back()
                    ->with('error', 'Failed to prepare Fuel Station module. Please try again.');
            }
        }

        $this->onboardingService->setupCompanyIdentity($company, $data);

        if ($industryCode === 'fuel_station') {
            try {
                $this->fuelStationOnboarding->ensureRequiredAccounts($company->id);
                $this->fuelStationOnboarding->createDefaultFuelItems($company->id);
            } catch (\Throwable $e) {
                Log::error('FuelStation module seed failed during onboarding', [
                    'company_id' => $company->id ?? null,
                    'company_slug' => $company->slug ?? null,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                return redirect("/{$company->slug}/fuel/onboarding")
                    ->with('error', 'Company saved, but Fuel Station defaults could not be created. You can continue onboarding and complete Fuel setup later.');
            }

            // For fuel station industry, redirect to the fuel-specific onboarding flow
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Company identity configured. Let\'s set up your fuel station!');
        }

        return redirect("/{$company->slug}/onboarding/fiscal-year")
            ->with('success', 'Company identity configured successfully.');
    }

    /**
     * Step 2: Fiscal Year Setup
     */
    public function showFiscalYear(): Response
    {
        $company = CompanyContext::getCompany();

        $months = [
            ['value' => 1, 'label' => 'January'],
            ['value' => 2, 'label' => 'February'],
            ['value' => 3, 'label' => 'March'],
            ['value' => 4, 'label' => 'April'],
            ['value' => 5, 'label' => 'May'],
            ['value' => 6, 'label' => 'June'],
            ['value' => 7, 'label' => 'July'],
            ['value' => 8, 'label' => 'August'],
            ['value' => 9, 'label' => 'September'],
            ['value' => 10, 'label' => 'October'],
            ['value' => 11, 'label' => 'November'],
            ['value' => 12, 'label' => 'December'],
        ];

        return Inertia::render('onboarding/FiscalYear', [
            'company' => $company,
            'months' => $months,
        ]);
    }

    public function storeFiscalYear(StoreFiscalYearRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $this->onboardingService->setupFiscalYear($company, $request->validated());

        if ($this->isFuelOnboardingFlow($request, $company)) {
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Fiscal year configured successfully.');
        }

        return redirect("/{$company->slug}/onboarding/bank-accounts")
            ->with('success', 'Fiscal year configured successfully.');
    }

    /**
     * Step 3: Bank/Cash Accounts
     */
    public function showBankAccounts(): Response
    {
        $company = CompanyContext::getCompany();

        $currencies = DB::table('public.currencies')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name', 'symbol']);

        $existingBankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'name', 'currency', 'subtype']);

        return Inertia::render('onboarding/BankAccounts', [
            'company' => $company,
            'currencies' => $currencies,
            'existingBankAccounts' => $existingBankAccounts,
        ]);
    }

    public function storeBankAccounts(StoreBankAccountsRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $this->onboardingService->setupBankAccounts($company, $request->validated()['bank_accounts']);

        if ($this->isFuelOnboardingFlow($request, $company)) {
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Bank accounts created successfully.');
        }

        return redirect("/{$company->slug}/onboarding/default-accounts")
            ->with('success', 'Bank accounts created successfully.');
    }

    /**
     * Step 4: Default Accounts
     */
    public function showDefaultAccounts(): Response
    {
        $company = CompanyContext::getCompany();
        app(DefaultAccountProvisioner::class)->ensureTransitAccounts($company);

        // Get available accounts by category
        $arAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_receivable')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $apAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_payable')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $revenueAccounts = Account::where('company_id', $company->id)
            ->where('type', 'revenue')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $expenseAccounts = Account::where('company_id', $company->id)
            ->whereIn('type', ['expense', 'cogs', 'other_expense'])
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $bankAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $retainedEarningsAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'retained_earnings')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $taxPayableAccounts = Account::where('company_id', $company->id)
            ->where('type', 'liability')
            ->where('name', 'like', '%tax%payable%')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $taxReceivableAccounts = Account::where('company_id', $company->id)
            ->where('type', 'asset')
            ->where('name', 'like', '%tax%receivable%')
            ->where('is_active', true)
            ->get(['id', 'code', 'name']);

        $transitLossAccounts = Account::where('company_id', $company->id)
            ->whereIn('type', ['expense', 'cogs', 'other_expense'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $transitGainAccounts = Account::where('company_id', $company->id)
            ->where('type', 'other_income')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('onboarding/DefaultAccounts', [
            'company' => $company,
            'arAccounts' => $arAccounts,
            'apAccounts' => $apAccounts,
            'revenueAccounts' => $revenueAccounts,
            'expenseAccounts' => $expenseAccounts,
            'bankAccounts' => $bankAccounts,
            'retainedEarningsAccounts' => $retainedEarningsAccounts,
            'taxPayableAccounts' => $taxPayableAccounts,
            'taxReceivableAccounts' => $taxReceivableAccounts,
            'transitLossAccounts' => $transitLossAccounts,
            'transitGainAccounts' => $transitGainAccounts,
        ]);
    }

    public function storeDefaultAccounts(StoreDefaultAccountsRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $this->onboardingService->setupDefaultAccounts($company, $request->validated());

        if ($this->isFuelOnboardingFlow($request, $company)) {
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Default accounts configured successfully.');
        }

        return redirect("/{$company->slug}/onboarding/tax-settings")
            ->with('success', 'Default accounts configured successfully.');
    }

    /**
     * Step 5: Tax Settings
     */
    public function showTaxSettings(): Response
    {
        $company = CompanyContext::getCompany();

        return Inertia::render('onboarding/TaxSettings', [
            'company' => $company,
        ]);
    }

    public function storeTaxSettings(StoreTaxSettingsRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $this->onboardingService->setupTaxSettings($company, $request->validated());

        if ($this->isFuelOnboardingFlow($request, $company)) {
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Tax settings configured successfully.');
        }

        return redirect("/{$company->slug}/onboarding/numbering")
            ->with('success', 'Tax settings configured successfully.');
    }

    /**
     * Step 6: Numbering Preferences
     */
    public function showNumbering(): Response
    {
        $company = CompanyContext::getCompany();

        return Inertia::render('onboarding/Numbering', [
            'company' => $company,
        ]);
    }

    public function storeNumbering(StoreNumberingRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $this->onboardingService->setupNumberingPreferences($company, $request->validated());

        if ($this->isFuelOnboardingFlow($request, $company)) {
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Numbering preferences configured successfully.');
        }

        return redirect("/{$company->slug}/onboarding/payment-terms")
            ->with('success', 'Numbering preferences configured successfully.');
    }

    /**
     * Step 7: Payment Terms
     */
    public function showPaymentTerms(): Response
    {
        $company = CompanyContext::getCompany();

        return Inertia::render('onboarding/PaymentTerms', [
            'company' => $company,
        ]);
    }

    public function storePaymentTerms(StorePaymentTermsRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $this->onboardingService->setupPaymentTerms($company, $request->validated());

        if ($this->isFuelOnboardingFlow($request, $company)) {
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Payment terms configured successfully.');
        }

        return redirect("/{$company->slug}/onboarding/complete")
            ->with('success', 'Payment terms configured successfully.');
    }

    /**
     * Complete onboarding
     */
    public function showComplete(): Response|RedirectResponse
    {
        $company = CompanyContext::getCompany();

        // For fuel station industry, redirect to fuel-specific onboarding
        if ($company->industry_code === 'fuel_station') {
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Basic setup complete! Now let\'s configure your fuel station.');
        }

        // Get summary statistics
        $accountsCreated = Account::where('company_id', $company->id)->count();
        $periodsCreated = DB::table('acct.accounting_periods')
            ->where('company_id', $company->id)
            ->count();
        $bankAccountsCreated = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->count();

        return Inertia::render('onboarding/Complete', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'industry_code' => $company->industry_code,
            ],
            'summary' => [
                'accounts_created' => $accountsCreated,
                'periods_created' => $periodsCreated,
                'bank_accounts_created' => $bankAccountsCreated,
                'defaults_configured' => !empty($company->ar_account_id),
                'tax_configured' => $company->tax_registered ?? false,
            ],
        ]);
    }

    public function complete(): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        // For fuel station industry, redirect to fuel-specific onboarding instead of completing
        if ($company->industry_code === 'fuel_station') {
            // Don't mark as complete yet - fuel onboarding will do that
            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('success', 'Basic setup complete! Now let\'s configure your fuel station.');
        }

        $this->onboardingService->completeOnboarding($company);

        return redirect("/{$company->slug}")
            ->with('success', 'Congratulations! Your company is now ready to use.');
    }

    private function isFuelOnboardingFlow(Request $request, Company $company): bool
    {
        return $company->industry_code === 'fuel_station'
            && $request->input('flow') === 'fuel';
    }
}
