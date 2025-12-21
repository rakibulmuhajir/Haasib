<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Constants\Permissions;
use App\Modules\Accounting\Http\Requests\CloseAccountingPeriodRequest;
use App\Modules\Accounting\Http\Requests\CreateFiscalYearPeriodsRequest;
use App\Modules\Accounting\Http\Requests\StoreFiscalYearRequest;
use App\Modules\Accounting\Http\Requests\UpdateFiscalYearRequest;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FiscalYearController extends Controller
{
    public function __construct(
        private CompanyContextService $companyContext
    ) {}

    public function index(Request $request): Response
    {
        $company = $this->companyContext->requireCompany();
        if (! $request->user()?->hasCompanyPermission(Permissions::JOURNAL_VIEW)) {
            abort(403);
        }

        $fiscalYears = FiscalYear::where('company_id', $company->id)
            ->with(['periods' => function ($query) {
                $query->orderBy('period_number');
            }])
            ->orderBy('start_date', 'desc')
            ->get();

        return Inertia::render('accounting/fiscal-years/Index', [
            'fiscalYears' => $fiscalYears,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]
        ]);
    }

    public function create(Request $request): Response
    {
        $company = $this->companyContext->requireCompany();
        if (! $request->user()?->hasCompanyPermission(Permissions::JOURNAL_CREATE)) {
            abort(403);
        }

        return Inertia::render('accounting/fiscal-years/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'existingFiscalYears' => FiscalYear::where('company_id', $company->id)
                ->orderBy('start_date', 'desc')
                ->get(['id', 'name', 'start_date', 'end_date']),
        ]);
    }

    public function store(StoreFiscalYearRequest $request): RedirectResponse
    {
        $company = $this->companyContext->requireCompany();

        try {
            $result = app(CommandBus::class)->dispatch('fiscal_year.create', [
                ...$request->validated(),
                'company_id' => $company->id,
            ], $request->user());

            $id = $result['data']['id'] ?? null;
            return redirect()
                ->route('fiscal-years.show', ['company' => $company->slug, 'fiscalYear' => $id])
                ->with('success', $result['message'] ?? 'Fiscal year created');
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

    public function show(Request $request, string $fiscalYear): Response
    {
        $company = $this->companyContext->requireCompany();
        if (! $request->user()?->hasCompanyPermission(Permissions::JOURNAL_VIEW)) {
            abort(403);
        }

        $fiscalYearModel = FiscalYear::where('company_id', $company->id)
            ->with(['periods' => fn ($q) => $q->orderBy('period_number')])
            ->findOrFail($fiscalYear);

        return Inertia::render('accounting/fiscal-years/Show', [
            'fiscalYear' => $fiscalYearModel,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]
        ]);
    }

    public function edit(Request $request, string $fiscalYear): Response
    {
        $company = $this->companyContext->requireCompany();
        if (! $request->user()?->hasCompanyPermission(Permissions::JOURNAL_CREATE)) {
            abort(403);
        }

        $fiscalYearModel = FiscalYear::where('company_id', $company->id)
            ->withCount('periods')
            ->findOrFail($fiscalYear);

        return Inertia::render('accounting/fiscal-years/Edit', [
            'fiscalYear' => $fiscalYearModel,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ]
        ]);
    }

    public function update(UpdateFiscalYearRequest $request, string $fiscalYear): RedirectResponse
    {
        $company = $this->companyContext->requireCompany();
        try {
            $result = app(CommandBus::class)->dispatch('fiscal_year.update', [
                'id' => $fiscalYear,
                ...$request->validated(),
                'company_id' => $company->id,
            ], $request->user());

            return back()->with('success', $result['message'] ?? 'Fiscal year updated');
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

    public function destroy(Request $request, string $fiscalYear): RedirectResponse
    {
        $company = $this->companyContext->requireCompany();
        try {
            $result = app(CommandBus::class)->dispatch('fiscal_year.delete', [
                'id' => $fiscalYear,
                'company_id' => $company->id,
            ], $request->user());

            return redirect()
                ->route('fiscal-years.index', ['company' => $company->slug])
                ->with('success', $result['message'] ?? 'Fiscal year deleted');
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

    public function createPeriods(CreateFiscalYearPeriodsRequest $request, string $fiscalYear): RedirectResponse
    {
        $company = $this->companyContext->requireCompany();
        try {
            $result = app(CommandBus::class)->dispatch('fiscal_year.periods.create', [
                'id' => $fiscalYear,
                ...$request->validated(),
                'company_id' => $company->id,
            ], $request->user());

            return back()->with('success', $result['message'] ?? 'Accounting periods created');
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

    public function closePeriod(CloseAccountingPeriodRequest $request, string $period): RedirectResponse
    {
        $company = $this->companyContext->requireCompany();
        try {
            $result = app(CommandBus::class)->dispatch('accounting_period.close', [
                'id' => $period,
                'company_id' => $company->id,
            ], $request->user());

            return back()->with('success', $result['message'] ?? 'Period closed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function reopenPeriod(CloseAccountingPeriodRequest $request, string $period): RedirectResponse
    {
        $company = $this->companyContext->requireCompany();
        try {
            $result = app(CommandBus::class)->dispatch('accounting_period.reopen', [
                'id' => $period,
                'company_id' => $company->id,
            ], $request->user());

            return back()->with('success', $result['message'] ?? 'Period reopened');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
    }
}
