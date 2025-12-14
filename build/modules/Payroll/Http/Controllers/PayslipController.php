<?php

namespace App\Modules\Payroll\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Payroll\Http\Requests\StorePayslipRequest;
use Modules\Payroll\Models\EarningType;
use Modules\Payroll\Models\DeductionType;
use Modules\Payroll\Models\Employee;
use Modules\Payroll\Models\PayrollPeriod;
use Modules\Payroll\Models\Payslip;

class PayslipController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

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

        $periods = PayrollPeriod::where('company_id', $company->id)
            ->whereIn('status', ['open', 'processing'])
            ->orderByDesc('period_start')
            ->get();

        $employees = Employee::where('company_id', $company->id)
            ->where('is_active', true)
            ->where('employment_status', 'active')
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

    public function store(StorePayslipRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validated();

        // Generate payslip number
        $lastPayslip = Payslip::where('company_id', $company->id)
            ->orderByDesc('payslip_number')
            ->first();

        $number = $lastPayslip
            ? 'PS' . str_pad((int)substr($lastPayslip->payslip_number, 2) + 1, 6, '0', STR_PAD_LEFT)
            : 'PS000001';

        $payslip = Payslip::create([
            'company_id' => $company->id,
            'payroll_period_id' => $validated['payroll_period_id'],
            'employee_id' => $validated['employee_id'],
            'payslip_number' => $number,
            'currency' => $validated['currency'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Create lines (trigger will update totals)
        foreach ($validated['lines'] ?? [] as $line) {
            $payslip->lines()->create($line);
        }

        return redirect()
            ->route('payslips.show', ['company' => $company->slug, 'payslip' => $payslip->id])
            ->with('success', 'Payslip created successfully.');
    }

    public function show(string $companySlug, string $payslipId): Response
    {
        $company = app(CurrentCompany::class)->get();

        $payslip = Payslip::where('company_id', $company->id)
            ->with([
                'employee:id,first_name,last_name,employee_number,department,position',
                'payrollPeriod:id,period_start,period_end,payment_date',
                'lines.earningType:id,code,name',
                'lines.deductionType:id,code,name',
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

    public function approve(string $companySlug, string $payslipId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $payslip = Payslip::where('company_id', $company->id)->findOrFail($payslipId);

        if ($payslip->status !== 'draft') {
            return back()->with('error', 'Only draft payslips can be approved.');
        }

        $payslip->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Payslip approved.');
    }

    public function markPaid(string $companySlug, string $payslipId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $payslip = Payslip::where('company_id', $company->id)->findOrFail($payslipId);

        if ($payslip->status !== 'approved') {
            return back()->with('error', 'Only approved payslips can be marked as paid.');
        }

        $payslip->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => request('payment_method', 'bank_transfer'),
            'payment_reference' => request('payment_reference'),
        ]);

        return back()->with('success', 'Payslip marked as paid.');
    }

    public function destroy(string $companySlug, string $payslipId): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

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
