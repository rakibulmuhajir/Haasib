<?php

use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Payroll\Models\EarningType;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Services\PayrollPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Testing\AssertableInertia as Assert;

function createPayrollTestCompany(array $settings = []): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payroll Test '.str()->random(8),
        'slug' => 'payroll-test-'.str()->lower(str()->random(10)),
        'base_currency' => 'SAR',
        'settings' => $settings,
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
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
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    Auth::login($user);

    return [$user, $company];
}

test('disabled payroll routes redirect to the company dashboard', function () {
    [$user, $company] = createPayrollTestCompany([
        'modules' => ['payroll' => false],
    ]);

    $this->actingAs($user)
        ->get(route('payroll.index', ['company' => $company->slug]))
        ->assertRedirect("/{$company->slug}")
        ->assertSessionHas('error', 'This module is not enabled for the selected company.');
});

test('enabled payroll state is shared with the sidebar after refresh', function () {
    [$user, $company] = createPayrollTestCompany([
        'modules' => ['payroll' => true],
    ]);

    $this->actingAs($user)
        ->get(route('payroll.index', ['company' => $company->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.currentCompany.settings.modules.payroll', true));
});

test('salary advance may exceed several months of salary', function () {
    [$user, $company] = createPayrollTestCompany([
        'modules' => ['payroll' => true],
    ]);

    $paymentAccount = Account::create([
        'company_id' => $company->id,
        'code' => '1015',
        'name' => 'Advance Cash',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'currency' => 'SAR',
        'is_active' => true,
    ]);
    $employee = Employee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-ADV-1',
        'first_name' => 'Advance',
        'last_name' => 'Employee',
        'hire_date' => '2026-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 1000,
        'currency' => 'SAR',
        'is_active' => true,
    ]);

    $employeeValidation = Validator::make(
        ['employee_id' => $employee->id],
        ['employee_id' => [
            'required',
            'uuid',
            Rule::exists(Employee::class, 'id')
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->where('employment_status', 'active'),
        ]]
    );
    expect($employeeValidation->passes())->toBeTrue();

    $advance = app(PayrollPostingService::class)->createSalaryAdvance([
        'employee_id' => $employee->id,
        'advance_date' => '2026-07-19',
        'amount' => 6000,
        'payment_method' => 'cash',
        'bank_account_id' => $paymentAccount->id,
        'reason' => 'Six-month salary advance',
    ], $company->id, $user->id, 'SAR');

    $transaction = \App\Modules\Accounting\Models\Transaction::where('company_id', $company->id)
        ->where('reference_type', 'pay.salary_advances')
        ->where('reference_id', $advance->id)
        ->firstOrFail();

    expect((float) $advance->amount)->toBe(6000.0)
        ->and((float) $advance->amount_outstanding)->toBe(6000.0)
        ->and((float) $transaction->total_debit)->toBe(6000.0)
        ->and((float) $transaction->total_credit)->toBe(6000.0);
});

test('foreign payroll snapshots its exchange rate and posts in base currency', function () {
    [$user, $company] = createPayrollTestCompany([
        'modules' => ['payroll' => true],
    ]);

    CompanyCurrency::create([
        'company_id' => $company->id,
        'currency_code' => 'USD',
        'exchange_rate' => 3.75,
        'enabled_at' => now(),
    ]);

    $salaryExpense = Account::create([
        'company_id' => $company->id,
        'code' => '6200',
        'name' => 'Salaries & Wages',
        'type' => 'expense',
        'subtype' => 'expense',
        'normal_balance' => 'debit',
        'is_active' => true,
    ]);
    $paymentAccount = Account::create([
        'company_id' => $company->id,
        'code' => '1010',
        'name' => 'Payroll Cash',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'currency' => 'SAR',
        'is_active' => true,
    ]);
    $employee = Employee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-USD-1',
        'first_name' => 'Foreign',
        'last_name' => 'Employee',
        'hire_date' => '2026-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 100,
        'currency' => 'USD',
        'is_active' => true,
    ]);
    $period = PayrollPeriod::create([
        'company_id' => $company->id,
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'payment_date' => '2026-07-31',
        'status' => 'open',
    ]);
    $earningType = EarningType::create([
        'company_id' => $company->id,
        'code' => 'BASE-USD',
        'name' => 'Base Salary USD',
        'gl_account_id' => $salaryExpense->id,
        'is_taxable' => true,
        'affects_overtime' => false,
        'is_recurring' => true,
        'is_system' => false,
        'is_active' => true,
    ]);

    $service = app(PayrollPostingService::class);
    $payslip = Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => $service->nextPayslipNumber($company->id),
        'currency' => 'USD',
        'exchange_rate' => $service->resolveExchangeRate($company->id, 'USD', 'SAR'),
        'base_currency' => 'SAR',
    ]);
    $payslip->lines()->create([
        'line_type' => 'earning',
        'earning_type_id' => $earningType->id,
        'description' => 'Base salary',
        'quantity' => 1,
        'rate' => 100,
        'amount' => 100,
        'sort_order' => 1,
    ]);

    $payslip->refresh();
    expect((float) $payslip->net_pay)->toBe(100.0)
        ->and((float) $payslip->base_net_pay)->toBe(375.0);

    CompanyCurrency::where('company_id', $company->id)
        ->where('currency_code', 'USD')
        ->update(['exchange_rate' => 4]);

    $accrual = $service->approve($payslip, $user->id);
    $accrualEntries = $accrual->journalEntries()->get();
    expect((float) $accrual->exchange_rate)->toBe(3.75)
        ->and((float) $accrual->total_debit)->toBe(375.0)
        ->and((float) $accrual->total_credit)->toBe(375.0)
        ->and((float) $accrualEntries->sum('currency_debit'))->toBe(100.0)
        ->and((float) $accrualEntries->sum('currency_credit'))->toBe(100.0);

    $payment = $service->markPaid($payslip->fresh(), [
        'payment_method' => 'cash',
        'payment_account_id' => $paymentAccount->id,
    ], $user->id);
    $paymentEntries = $payment->journalEntries()->get();
    expect((float) $payment->exchange_rate)->toBe(3.75)
        ->and((float) $payment->total_debit)->toBe(375.0)
        ->and((float) $payment->total_credit)->toBe(375.0)
        ->and((float) $paymentEntries->sum('currency_debit'))->toBe(100.0)
        ->and((float) $paymentEntries->sum('currency_credit'))->toBe(100.0)
        ->and($payslip->fresh()->status)->toBe('paid');
});
