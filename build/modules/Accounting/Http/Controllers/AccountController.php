<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Exceptions\IndustryCoaPackNotSeededException;
use App\Modules\Accounting\Http\Requests\StoreAccountRequest;
use App\Modules\Accounting\Http\Requests\UpdateAccountRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountTemplate;
use App\Modules\Accounting\Services\CompanyOnboardingService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        
        // Fetch all accounts for client-side grouping and tree structure
        $accounts = Account::where('company_id', $company->id)
            ->orderBy('code')
            ->get();

        return Inertia::render('accounting/accounts/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'accounts' => $accounts,
            'canRestoreMissingAccounts' => (bool) $request->user()?->hasCompanyPermission(Permissions::ACCOUNT_CREATE),
        ]);
    }

    /**
     * Create any of this company's industry-standard accounts that are missing
     * (e.g. because onboarding ran against an unseeded COA pack). Never touches
     * an existing account -- see CompanyOnboardingService::applyIndustryCoaTemplates().
     */
    public function restoreMissing(Request $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();

        if (! $request->user()?->hasCompanyPermission(Permissions::ACCOUNT_CREATE)) {
            abort(403);
        }

        if (! $company->industry_code) {
            return back()->with('error', 'This company has no industry set, so there are no standard accounts to restore.');
        }

        try {
            $result = app(CompanyOnboardingService::class)
                ->applyIndustryCoaTemplates($company, $company->industry_code, allowUpdateExisting: false);
        } catch (IndustryCoaPackNotSeededException $e) {
            return back()->with('error', $e->getMessage());
        }

        $createdCount = count($result['created']);
        $conflictCount = count($result['skipped_conflicts']);

        // The templates applied without throwing, so whatever left the company marked
        // incomplete (see CompanyBootstrapService) is resolved -- clear the gate.
        if ($company->bootstrap_incomplete_at !== null) {
            $company->forceFill(['bootstrap_incomplete_at' => null])->saveQuietly();
        }

        if ($createdCount === 0) {
            return back()->with('success', $conflictCount > 0
                ? "Nothing to restore, but {$conflictCount} existing account(s) conflict with the standard chart -- review them manually."
                : 'Nothing missing -- this company already has every standard account for its industry.');
        }

        $message = "Restored {$createdCount} missing standard account".($createdCount === 1 ? '' : 's').'.';
        if ($conflictCount > 0) {
            $message .= " {$conflictCount} existing account(s) conflict with the standard chart and were left unchanged.";
        }

        return back()->with('success', $message);
    }

    public function create(): Response
    {
        $company = app(CompanyContextService::class)->requireCompany();
        return Inertia::render('accounting/accounts/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'parents' => Account::where('company_id', $company->id)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'templates' => AccountTemplate::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'subtype', 'normal_balance', 'is_contra', 'description']),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $company = app(CompanyContextService::class)->requireCompany();
        try {
            app(CommandBus::class)->dispatch('account.create', [
                ...$request->validated(),
                'company_id' => $company->id,
            ], $request->user());

            return redirect()
                ->route('accounts.index', ['company' => $company->slug])
                ->with('success', 'Account created');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function show(string $company, string $account): Response
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $record = Account::with('parent', 'children')
            ->where('company_id', $companyModel->id)
            ->findOrFail($account);

        return Inertia::render('accounting/accounts/Show', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
            ],
            'account' => $record,
        ]);
    }

    public function edit(string $company, string $account): Response
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        $record = Account::where('company_id', $companyModel->id)->findOrFail($account);

        return Inertia::render('accounting/accounts/Edit', [
            'company' => [
                'id' => $companyModel->id,
                'name' => $companyModel->name,
                'slug' => $companyModel->slug,
                'base_currency' => $companyModel->base_currency,
            ],
            'account' => $record,
            'parents' => Account::where('company_id', $companyModel->id)
                ->where('id', '!=', $record->id)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type']),
        ]);
    }

    public function update(UpdateAccountRequest $request, string $company, string $account): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        try {
            app(CommandBus::class)->dispatch('account.update', [
                ...$request->validated(),
                'id' => $account,
                'company_id' => $companyModel->id,
            ], $request->user());

            return back()->with('success', 'Account updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(Request $request, string $company, string $account): RedirectResponse
    {
        $companyModel = app(CompanyContextService::class)->requireCompany();
        try {
            app(CommandBus::class)->dispatch('account.delete', [
                'id' => $account,
                'company_id' => $companyModel->id,
            ], $request->user());

            return back()->with('success', 'Account deleted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
