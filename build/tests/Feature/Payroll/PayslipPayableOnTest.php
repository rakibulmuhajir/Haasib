<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayrollPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Which wages a daily close may pay: approved, unpaid, and belonging to a period whose pay day is
 * the close's business date.
 *
 * The close and its form each carried their own copy of this rule, keyed on approved_at - stamped
 * whenever someone clicks Approve. A month's wages could only be paid in the close for the day
 * they happened to be approved, so a station entering past months from its register could never
 * pay them at all. It is now one scope, keyed on the period's payment date.
 */
function payableCompany(): Company
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payable '.str()->random(6),
        'slug' => 'payable-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['payroll' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    Auth::login($user);

    return $company;
}

function payslipFor(Company $company, string $paymentDate, string $status = 'approved', ?string $approvedAt = '2026-09-24 10:00'): Payslip
{
    $employee = Employee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-'.str()->upper(str()->random(5)),
        'first_name' => 'Station',
        'last_name' => 'Attendant',
        'hire_date' => '2026-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 35000,
        'currency' => 'PKR',
        'is_active' => true,
    ]);

    $period = PayrollPeriod::firstOrCreate(
        ['company_id' => $company->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31'],
        ['payment_date' => $paymentDate, 'status' => 'open'],
    );

    $service = app(PayrollPostingService::class);

    return Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => $service->nextPayslipNumber($company->id),
        // Same currency as the company, so the table requires no exchange rate at all, and base
        // amounts equal to the amounts.
        'currency' => 'PKR',
        'exchange_rate' => null,
        'base_currency' => 'PKR',
        'gross_pay' => 35000,
        'net_pay' => 35000,
        'base_gross_pay' => 35000,
        'base_net_pay' => 35000,
        'status' => $status,
        'approved_at' => $approvedAt,
    ]);
}

test('wages are payable on their pay day, not the day they were approved', function () {
    $company = payableCompany();

    // August wages, approved on 24 September while back-filling, paid on 3 September.
    $payslip = payslipFor($company, '2026-09-03');

    expect(Payslip::payableOn('2026-09-03')->pluck('id')->all())->toContain($payslip->id)
        ->and(Payslip::payableOn('2026-09-24')->pluck('id')->all())->not->toContain($payslip->id);
});

test('wages already paid are not offered again', function () {
    $company = payableCompany();
    $payslip = payslipFor($company, '2026-09-03', 'paid');

    expect(Payslip::payableOn('2026-09-03')->pluck('id')->all())->not->toContain($payslip->id);
});

test('wages not yet approved are not payable', function () {
    $company = payableCompany();
    $payslip = payslipFor($company, '2026-09-03', 'draft', null);

    expect(Payslip::payableOn('2026-09-03')->pluck('id')->all())->not->toContain($payslip->id);
});
