<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\MonthEndPayrollDraft;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * A month's payroll is drafted -- never approved -- once its last day is closed, with each
 * person's advances taken off, and only the advances given by that month's end. Self-contained
 * helpers, distinctly named, as each test file runs on its own (see PayslipPayableAnyDayTest).
 */
function monthEndPayrollCompany(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Month End '.str()->random(6),
        'slug' => 'month-end-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['payroll' => true]],
    ]);
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id, 'user_id' => $user->id, 'role' => 'owner',
        'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($user, 'owner'));
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    Auth::login($user);

    $employee = Employee::create([
        'company_id' => $company->id, 'employee_number' => 'EMP-ME-1', 'first_name' => 'Month', 'last_name' => 'End',
        'hire_date' => '2026-01-01', 'employment_type' => 'full_time', 'employment_status' => 'active',
        'pay_frequency' => 'monthly', 'base_salary' => 20000, 'currency' => 'PKR', 'is_active' => true,
    ]);

    return [$user, $company, $employee];
}

function monthEndAdvance(Company $company, Employee $employee, string $date, float $amount): SalaryAdvance
{
    return SalaryAdvance::create([
        'company_id' => $company->id, 'employee_id' => $employee->id, 'advance_date' => $date,
        'amount' => $amount, 'amount_outstanding' => $amount, 'reason' => 'Daily advance',
        'status' => 'pending', 'payment_method' => 'cash',
    ]);
}

test('only the last day of a month drafts its payroll', function () {
    [, $company] = monthEndPayrollCompany();
    $draft = app(MonthEndPayrollDraft::class);

    expect($draft->prepareAfterClose($company, '2026-09-29'))->toBe(0)
        ->and(PayrollPeriod::where('company_id', $company->id)->count())->toBe(0);

    expect($draft->prepareAfterClose($company, '2026-09-30'))->toBe(1);
    $payslip = Payslip::where('company_id', $company->id)->sole();
    expect($payslip->status)->toBe('draft');
});

test('a draft takes off the month\'s advances, and a re-run brings an older draft up to date', function () {
    [, $company, $employee] = monthEndPayrollCompany();
    $draft = app(MonthEndPayrollDraft::class);

    // Drafted before any advance: nothing to take off yet.
    $draft->prepare($company, '2026-09-01');
    expect((float) Payslip::where('company_id', $company->id)->sole()->total_deductions)->toBe(0.0);

    // An advance in September, one in October.
    monthEndAdvance($company, $employee, '2026-09-10', 3000);
    monthEndAdvance($company, $employee, '2026-10-02', 4000);

    // Re-running refreshes the draft: only September's advance comes off September's pay.
    expect($draft->prepare($company, '2026-09-01'))->toBe(0);
    $payslip = Payslip::where('company_id', $company->id)->sole()->refresh();
    expect((float) $payslip->total_deductions)->toBe(3000.0)
        ->and((float) $payslip->net_pay)->toBe(17000.0);
});

test('the reminder names the latest closed month whose payroll is not approved', function () {
    [, $company] = monthEndPayrollCompany();
    $draft = app(MonthEndPayrollDraft::class);

    // No month-end close yet: nothing to remind about.
    expect($draft->reminder($company))->toBeNull();

    \App\Modules\Accounting\Models\Transaction::create([
        'company_id' => $company->id, 'transaction_number' => 'DC-2026-09-30-'.str()->random(4),
        'transaction_type' => 'fuel_daily_close', 'transaction_date' => '2026-09-30', 'posting_date' => '2026-09-30',
        'description' => 'Daily close 2026-09-30', 'currency' => 'PKR', 'total_debit' => 0, 'total_credit' => 0,
        'status' => 'posted', 'metadata' => [],
    ]);
    expect($draft->reminder($company))->toBe(['month' => '2026-09', 'label' => 'September 2026', 'drafts' => 0]);

    $draft->prepare($company, '2026-09-01');
    expect($draft->reminder($company)['drafts'])->toBe(1);

    Payslip::where('company_id', $company->id)->update(['status' => 'approved']);
    expect($draft->reminder($company))->toBeNull();
});

test('advances come off in full, up to the whole net pay', function () {
    [, $company, $employee] = monthEndPayrollCompany();
    // The whole salary taken as advances through the month.
    monthEndAdvance($company, $employee, '2026-09-05', 12000);
    monthEndAdvance($company, $employee, '2026-09-20', 8000);

    app(MonthEndPayrollDraft::class)->prepare($company, '2026-09-01');

    $payslip = Payslip::where('company_id', $company->id)->sole()->refresh();
    expect((float) $payslip->total_deductions)->toBe(20000.0)
        ->and((float) $payslip->net_pay)->toBe(0.0);
});

test('a cancelled payslip does not stop the month being run again', function () {
    [, $company] = monthEndPayrollCompany();
    $draft = app(MonthEndPayrollDraft::class);

    $draft->prepare($company, '2026-09-01');
    Payslip::where('company_id', $company->id)->update(['status' => 'cancelled']);

    expect($draft->prepare($company, '2026-09-01'))->toBe(1)
        ->and(Payslip::where('company_id', $company->id)->where('status', 'draft')->count())->toBe(1);
});
