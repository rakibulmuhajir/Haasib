<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Models\DeductionType;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayslipLine;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\MonthEndPayrollDraft;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * An owner takes pay off a draft payslip for leave, absence, damage or a fine; advance recovery
 * is then worked out from what is left. Self-contained helpers, distinctly named.
 */
function deductionTestCompany(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Deduction '.str()->random(6),
        'slug' => 'deduction-'.str()->lower(str()->random(10)),
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
        'company_id' => $company->id, 'employee_number' => 'EMP-DD-1', 'first_name' => 'Deduct', 'last_name' => 'Me',
        'hire_date' => '2026-01-01', 'employment_type' => 'full_time', 'employment_status' => 'active',
        'pay_frequency' => 'monthly', 'base_salary' => 20000, 'currency' => 'PKR', 'is_active' => true,
    ]);

    return [$user, $company, $employee];
}

function deductionTestAdvance(Company $company, Employee $employee, float $amount): SalaryAdvance
{
    return SalaryAdvance::create([
        'company_id' => $company->id, 'employee_id' => $employee->id, 'advance_date' => '2026-09-10',
        'amount' => $amount, 'amount_outstanding' => $amount, 'reason' => 'Advance',
        'status' => 'pending', 'payment_method' => 'cash',
    ]);
}

function deductionTestDraft(Company $company): Payslip
{
    app(MonthEndPayrollDraft::class)->prepare($company, '2026-09-01');

    return Payslip::where('company_id', $company->id)->sole();
}

function deductionTestType(Company $company, string $code): DeductionType
{
    return app(PayrollPostingService::class)->ensureStandardDeductionTypes($company->id)->firstWhere('code', $code);
}

function deductionTestAdvanceRecovery(Payslip $payslip): float
{
    return (float) PayslipLine::where('payslip_id', $payslip->id)
        ->where('line_type', 'deduction')->whereNotNull('salary_advance_id')->sum('amount');
}

test('an absence deduction comes off a draft payslip net pay', function () {
    [$user, $company] = deductionTestCompany();
    $payslip = deductionTestDraft($company);
    $absence = deductionTestType($company, 'ABSENCE');

    $this->actingAs($user)
        ->post("/{$company->slug}/payslips/{$payslip->id}/deductions", [
            'deduction_type_id' => $absence->id, 'amount' => 2000, 'description' => 'Two days',
        ])
        ->assertSessionHasNoErrors();

    $payslip->refresh();
    expect((float) $payslip->total_deductions)->toBe(2000.0)
        ->and((float) $payslip->net_pay)->toBe(18000.0);
});

test('advances are recovered only from what a manual deduction leaves', function () {
    [$user, $company, $employee] = deductionTestCompany();
    deductionTestAdvance($company, $employee, 20000);
    $payslip = deductionTestDraft($company);
    expect(deductionTestAdvanceRecovery($payslip))->toBe(20000.0);

    $this->actingAs($user)
        ->post("/{$company->slug}/payslips/{$payslip->id}/deductions", [
            'deduction_type_id' => deductionTestType($company, 'DAMAGE')->id, 'amount' => 5000,
        ])
        ->assertSessionHasNoErrors();

    $payslip->refresh();
    expect(deductionTestAdvanceRecovery($payslip))->toBe(15000.0)
        ->and((float) $payslip->total_deductions)->toBe(20000.0)
        ->and((float) $payslip->net_pay)->toBe(0.0)
        ->and((float) SalaryAdvance::where('company_id', $company->id)->sole()->amount_outstanding)->toBe(20000.0);
});

test('removing the manual deduction restores the advance recovery', function () {
    [$user, $company, $employee] = deductionTestCompany();
    deductionTestAdvance($company, $employee, 20000);
    $payslip = deductionTestDraft($company);

    $this->actingAs($user)
        ->post("/{$company->slug}/payslips/{$payslip->id}/deductions", [
            'deduction_type_id' => deductionTestType($company, 'DAMAGE')->id, 'amount' => 5000,
        ]);
    $manual = PayslipLine::where('payslip_id', $payslip->id)->where('line_type', 'deduction')->whereNull('salary_advance_id')->sole();

    $this->actingAs($user)
        ->delete("/{$company->slug}/payslip-lines/{$manual->id}")
        ->assertSessionHasNoErrors();

    expect(deductionTestAdvanceRecovery($payslip->refresh()))->toBe(20000.0)
        ->and(PayslipLine::where('payslip_id', $payslip->id)->whereNull('salary_advance_id')->where('line_type', 'deduction')->count())->toBe(0);
});

test('an approved payslip refuses a deduction', function () {
    [$user, $company] = deductionTestCompany();
    $payslip = deductionTestDraft($company);
    Payslip::where('id', $payslip->id)->update(['status' => 'approved']);

    $this->actingAs($user)
        ->post("/{$company->slug}/payslips/{$payslip->id}/deductions", [
            'deduction_type_id' => deductionTestType($company, 'ABSENCE')->id, 'amount' => 1000,
        ])
        ->assertSessionHasErrors('payslip');

    expect(PayslipLine::where('payslip_id', $payslip->id)->where('line_type', 'deduction')->count())->toBe(0);
});

test('the salary advance type cannot be picked by hand', function () {
    [$user, $company, $employee] = deductionTestCompany();
    deductionTestAdvance($company, $employee, 3000);
    $payslip = deductionTestDraft($company);
    $advanceType = DeductionType::where('company_id', $company->id)->where('code', 'SALARY_ADVANCE')->sole();

    $this->actingAs($user)
        ->post("/{$company->slug}/payslips/{$payslip->id}/deductions", [
            'deduction_type_id' => $advanceType->id, 'amount' => 1000,
        ])
        ->assertSessionHasErrors('deduction_type_id');
});

test('the standard deduction types are made once, and damage points at other income', function () {
    [, $company] = deductionTestCompany();
    $service = app(PayrollPostingService::class);

    $first = $service->ensureStandardDeductionTypes($company->id);
    $second = $service->ensureStandardDeductionTypes($company->id);

    expect($first->pluck('code')->all())->toBe(['UNPAID_LEAVE', 'ABSENCE', 'DAMAGE', 'NEGLIGENCE', 'OTHER_DEDUCTION'])
        ->and($second->pluck('id')->all())->toBe($first->pluck('id')->all())
        ->and(DeductionType::where('company_id', $company->id)->where('code', 'DAMAGE')->count())->toBe(1);

    $damage = $first->firstWhere('code', 'DAMAGE');
    expect(Account::find($damage->gl_account_id)->type)->toBe('other_income')
        ->and(Account::find($first->firstWhere('code', 'ABSENCE')->gl_account_id)->type)->toBe('expense');
});
