<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\StorePayrollPeriodRequest;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPeriodController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $periods = PayrollPeriod::where('company_id', $company->id)
            ->withCount('payslips')
            ->orderByDesc('period_start')
            ->paginate(20);

        return Inertia::render('Payroll/Periods/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'periods' => $periods,
            'filters' => request()->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Payroll/Periods/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ]);
    }

    public function store(StorePayrollPeriodRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $period = PayrollPeriod::create([
            ...$request->validated(),
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('payroll-periods.show', ['company' => $company->slug, 'payroll_period' => $period->id])
            ->with('success', 'Payroll period created successfully.');
    }

    public function show(string $companySlug, string $periodId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $period = PayrollPeriod::where('company_id', $company->id)
            ->with(['payslips' => function ($q) {
                $q->with('employee:id,first_name,last_name,employee_number')
                    ->orderBy('payslip_number');
            }])
            ->findOrFail($periodId);

        $period->setAttribute('total_gross', round((float) $period->payslips->sum('base_gross_pay'), 2));
        $period->setAttribute('total_net', round((float) $period->payslips->sum('base_net_pay'), 2));
        $period->setAttribute('currency', $company->base_currency);

        return Inertia::render('Payroll/Periods/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'period' => $period,
        ]);
    }

    public function close(string $companySlug, string $periodId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $period = PayrollPeriod::where('company_id', $company->id)->findOrFail($periodId);

        if ($period->status !== 'open' && $period->status !== 'processing') {
            return back()->with('error', 'Period is already closed.');
        }

        // Check all payslips are approved or paid
        $pendingPayslips = $period->payslips()->whereIn('status', ['draft'])->count();
        if ($pendingPayslips > 0) {
            return back()->with('error', "Cannot close period with {$pendingPayslips} draft payslips.");
        }

        $period->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Payroll period closed.');
    }

    public function destroy(string $companySlug, string $periodId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $period = PayrollPeriod::where('company_id', $company->id)->findOrFail($periodId);

        if ($period->status !== 'open') {
            return back()->with('error', 'Cannot delete non-open periods.');
        }

        if ($period->payslips()->exists()) {
            return back()->with('error', 'Cannot delete period with payslips.');
        }

        $period->delete();

        return redirect()
            ->route('payroll-periods.index', ['company' => $company->slug])
            ->with('success', 'Payroll period deleted successfully.');
    }
}
