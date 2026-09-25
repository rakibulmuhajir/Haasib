<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * There is no fixed pay day - it is up to employees when they ask, or the company when it pays
 * at its convenience. A September payslip is therefore payable on any day of September, from
 * the 1st through the 25th and beyond, but not before September starts; once paid, it is no
 * longer offered. See Payslip::scopePayableOn.
 *
 * This file is self-contained (its own company/payslip helpers, distinctly named) rather than
 * reusing PayslipPayableOnTest.php's payableCompany()/payslipFor(): CLAUDE.md has each test file
 * run on its own, and Pest only loads the file(s) actually passed to it, so a helper defined in
 * one file is not available when another runs alone.
 */
function anyDayTestCompany(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Any Day '.str()->random(6),
        'slug' => 'any-day-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['payroll' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    DB::table('auth.company_user')->insert([
        'company_id' => $company->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(CompanyContextService::class)->assignRole($user, 'owner'),
    );
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    Auth::login($user);

    return [$user, $company];
}

function anyDayPayslipFor(Company $company, string $periodStart, string $periodEnd, string $status = 'approved'): Payslip
{
    $employee = Employee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-'.str()->upper(str()->random(5)),
        'first_name' => 'Any',
        'last_name' => 'Day',
        'hire_date' => '2026-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 35000,
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    $period = PayrollPeriod::firstOrCreate(
        ['company_id' => $company->id, 'period_start' => $periodStart, 'period_end' => $periodEnd],
        // payment_date only fills the not-null column; it plays no part in payability.
        ['payment_date' => $periodEnd, 'status' => 'open'],
    );

    $service = app(PayrollPostingService::class);

    return Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => $service->nextPayslipNumber($company->id),
        'currency' => 'PKR',
        'exchange_rate' => null,
        'base_currency' => 'PKR',
        'gross_pay' => 35000,
        'net_pay' => 35000,
        'base_gross_pay' => 35000,
        'base_net_pay' => 35000,
        'status' => $status,
        'approved_at' => '2026-09-01 09:00',
    ]);
}

test('an approved unpaid September payslip is payable on 1 September', function () {
    [, $company] = anyDayTestCompany();
    $payslip = anyDayPayslipFor($company, '2026-09-01', '2026-09-30');

    expect(Payslip::payableOn('2026-09-01')->pluck('id')->all())->toContain($payslip->id);
});

test('the same September payslip is still payable on 25 September', function () {
    [, $company] = anyDayTestCompany();
    $payslip = anyDayPayslipFor($company, '2026-09-01', '2026-09-30');

    expect(Payslip::payableOn('2026-09-25')->pluck('id')->all())->toContain($payslip->id);
});

test('a September payslip is not payable on 31 August, before its period starts', function () {
    [, $company] = anyDayTestCompany();
    $payslip = anyDayPayslipFor($company, '2026-09-01', '2026-09-30');

    expect(Payslip::payableOn('2026-08-31')->pluck('id')->all())->not->toContain($payslip->id);
});

test('a paid September payslip is not offered again', function () {
    [, $company] = anyDayTestCompany();
    $payslip = anyDayPayslipFor($company, '2026-09-01', '2026-09-30', 'paid');

    expect(Payslip::payableOn('2026-09-25')->pluck('id')->all())->not->toContain($payslip->id);
});

test('running payroll for a month with no payment date works', function () {
    [$user, $company] = anyDayTestCompany();

    Employee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-ANYDAY-1',
        'first_name' => 'Any',
        'last_name' => 'Day',
        'hire_date' => '2026-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 40000,
        'currency' => $company->base_currency,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post(route('payroll.run-monthly', ['company' => $company->slug]), [
            'month' => '2026-09',
        ])
        ->assertSessionHas('success');

    $period = PayrollPeriod::where('company_id', $company->id)
        ->where('period_start', '2026-09-01')
        ->firstOrFail();

    expect($period->period_start->toDateString())->toBe('2026-09-01')
        ->and(Payslip::where('company_id', $company->id)->where('payroll_period_id', $period->id)->count())->toBe(1);
});
