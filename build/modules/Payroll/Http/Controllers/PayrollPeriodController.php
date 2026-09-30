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
    /** Payroll months live on the Payroll page now. */
    public function index(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('payroll.index', ['company' => app(\App\Services\CurrentCompany::class)->get()->slug]);
    }

    /** A month is started with Run payroll on the Payroll page. */
    public function create(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('payroll.index', ['company' => app(\App\Services\CurrentCompany::class)->get()->slug]);
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

    /** A payroll month opens on the Payroll page at that month. */
    public function show(string $companySlug, string $periodId): \Illuminate\Http\RedirectResponse
    {
        $company = app(\App\Services\CurrentCompany::class)->get();
        $period = PayrollPeriod::where('company_id', $company->id)->findOrFail($periodId);

        return redirect()->route('payroll.index', ['company' => $company->slug, 'month' => $period->period_start->format('Y-m')]);
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
