<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payroll\Http\Requests\ApprovePayslipRequest;
use App\Modules\Payroll\Http\Requests\GeneratePeriodPayslipsRequest;
use App\Modules\Payroll\Http\Requests\MarkPayslipPaidRequest;
use App\Modules\Payroll\Http\Requests\StorePayslipRequest;
use App\Modules\Payroll\Models\DeductionType;
use App\Modules\Payroll\Models\EarningType;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayslipController extends Controller
{
    private function setPayrollContext(string $companyId): void
    {
        DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
    }

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $payslips = Payslip::where('company_id', $company->id)
            ->with([
                'employee:id,first_name,last_name,employee_number',
                'payrollPeriod:id,period_start,period_end',
            ])
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('Payroll/Payslips/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'payslips' => $payslips,
            'filters' => request()->only(['search', 'status', 'period_id']),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $periods = PayrollPeriod::where('company_id', $company->id)
            ->whereIn('status', ['open', 'processing'])
            ->orderByDesc('period_start')
            ->get();

        $employees = Employee::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('employment_status', 'active')
            ->withSum(['salaryAdvances as outstanding_advances' => fn ($query) => $query->whereIn('status', ['pending', 'partially_recovered'])], 'amount_outstanding')
            ->select('id', 'first_name', 'last_name', 'employee_number', 'base_salary', 'currency')
            ->orderBy('last_name')
            ->get();

        $earningTypes = EarningType::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get();

        $deductionTypes = DeductionType::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get();

        return Inertia::render('Payroll/Payslips/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'periods' => $periods,
            'employees' => $employees,
            'earningTypes' => $earningTypes,
            'deductionTypes' => $deductionTypes,
        ]);
    }

    public function store(StorePayslipRequest $request, PayrollPostingService $payrollPostingService): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $validated = $request->validated();

        try {
            $payslip = DB::transaction(function () use ($company, $validated, $payrollPostingService) {
                $payslip = Payslip::create([
                    'company_id' => $company->id,
                    'payroll_period_id' => $validated['payroll_period_id'],
                    'employee_id' => $validated['employee_id'],
                    'payslip_number' => $payrollPostingService->nextPayslipNumber($company->id),
                    'currency' => $validated['currency'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($validated['lines'] ?? [] as $line) {
                    $payslip->lines()->create($line);
                }

                $payrollPostingService->prepareAutomaticAdvanceDeductions($payslip);

                return $payslip->refresh();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Payslip could not be created. Check payroll accounts and try again.');
        }

        return redirect()
            ->route('payslips.show', ['company' => $company->slug, 'payslip' => $payslip->id])
            ->with('success', 'Payslip created successfully.');
    }

    public function show(PayrollPostingService $payrollPostingService, string $companySlug, string $payslipId): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $draftPayslip = Payslip::where('company_id', $company->id)->findOrFail($payslipId);
        if ($draftPayslip->status === 'draft') {
            $payrollPostingService->prepareAutomaticAdvanceDeductions($draftPayslip);
        }

        $payslip = Payslip::where('company_id', $company->id)
            ->with([
                'employee:id,first_name,last_name,employee_number,department,position',
                'payrollPeriod:id,period_start,period_end,payment_date',
                'lines.earningType:id,code,name',
                'lines.deductionType:id,code,name',
                'lines.salaryAdvance:id,advance_date',
                'approvedBy:id,name',
            ])
            ->findOrFail($payslipId);

        return Inertia::render('Payroll/Payslips/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'payslip' => $payslip,
        ]);
    }

    public function edit(string $companySlug, string $payslipId): Response
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $payslip = Payslip::where('company_id', $company->id)
            ->with('lines')
            ->findOrFail($payslipId);

        if ($payslip->status !== 'draft') {
            return redirect()
                ->route('payslips.show', ['company' => $company->slug, 'payslip' => $payslip->id])
                ->with('error', 'Only draft payslips can be edited.');
        }

        $earningTypes = EarningType::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get();

        $deductionTypes = DeductionType::where('company_id', $company->id)
            ->where('is_active', true)
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get();

        return Inertia::render('Payroll/Payslips/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'payslip' => $payslip,
            'earningTypes' => $earningTypes,
            'deductionTypes' => $deductionTypes,
        ]);
    }

    public function approve(ApprovePayslipRequest $request, PayrollPostingService $payrollPostingService, string $companySlug, string $payslipId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $payslip = Payslip::where('company_id', $company->id)->findOrFail($payslipId);

        if ($payslip->status !== 'draft') {
            return back()->with('error', 'Only draft payslips can be approved.');
        }

        try {
            $payrollPostingService->approve($payslip, (string) $request->user()->id);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Payslip could not be approved because payroll posting failed.');
        }

        return back()->with('success', 'Payslip approved and posted to accounting.');
    }

    public function markPaid(MarkPayslipPaidRequest $request, PayrollPostingService $payrollPostingService, string $companySlug, string $payslipId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $payslip = Payslip::where('company_id', $company->id)->findOrFail($payslipId);

        if ($payslip->status !== 'approved') {
            return back()->with('error', 'Only approved payslips can be marked as paid.');
        }

        try {
            $payrollPostingService->markPaid($payslip, $request->validated(), (string) $request->user()->id);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Payslip could not be marked paid because payment posting failed.');
        }

        return back()->with('success', 'Payslip marked paid and posted to accounting.');
    }

    public function generateForPeriod(GeneratePeriodPayslipsRequest $request, PayrollPostingService $payrollPostingService, string $companySlug, string $periodId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $period = PayrollPeriod::where('company_id', $company->id)
            ->whereIn('status', ['open', 'processing'])
            ->findOrFail($periodId);

        $created = 0;

        try {
            $created = $payrollPostingService->generatePayslipsForPeriod($period, $company->base_currency ?? 'PKR');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Payslips could not be generated. Check employee salaries and payroll accounts.');
        }

        return back()->with('success', "{$created} payslips generated for this period.");
    }

    public function approveForPeriod(ApprovePayslipRequest $request, PayrollPostingService $payrollPostingService, string $companySlug, string $periodId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $period = PayrollPeriod::where('company_id', $company->id)->findOrFail($periodId);
        $approved = 0;

        try {
            Payslip::where('company_id', $company->id)
                ->where('payroll_period_id', $period->id)
                ->where('status', 'draft')
                ->orderBy('payslip_number')
                ->get()
                ->each(function (Payslip $payslip) use ($payrollPostingService, $request, &$approved) {
                    $payrollPostingService->approve($payslip, (string) $request->user()->id);
                    $approved++;
                });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Some payslips could not be approved because payroll posting failed.');
        }

        return back()->with('success', "{$approved} payslips approved and posted.");
    }

    public function payForPeriod(MarkPayslipPaidRequest $request, PayrollPostingService $payrollPostingService, string $companySlug, string $periodId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $period = PayrollPeriod::where('company_id', $company->id)->findOrFail($periodId);
        $paid = 0;

        try {
            Payslip::where('company_id', $company->id)
                ->where('payroll_period_id', $period->id)
                ->where('status', 'approved')
                ->orderBy('payslip_number')
                ->get()
                ->each(function (Payslip $payslip) use ($payrollPostingService, $request, &$paid) {
                    $payrollPostingService->markPaid($payslip, $request->validated(), (string) $request->user()->id);
                    $paid++;
                });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Some payslips could not be paid because payment posting failed.');
        }

        return back()->with('success', "{$paid} payslips marked paid and posted.");
    }

    public function destroy(string $companySlug, string $payslipId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $this->setPayrollContext($company->id);

        $payslip = Payslip::where('company_id', $company->id)->findOrFail($payslipId);

        if ($payslip->status !== 'draft') {
            return back()->with('error', 'Only draft payslips can be deleted.');
        }

        $payslip->delete();

        return redirect()
            ->route('payslips.index', ['company' => $company->slug])
            ->with('success', 'Payslip deleted successfully.');
    }
}
