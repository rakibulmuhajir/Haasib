<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreAccountRequest;
use App\Modules\Accounting\Http\Requests\UpdateAccountRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountTemplate;
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
        ]);
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
