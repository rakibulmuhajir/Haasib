<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\MonthEndPayrollDraft;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Approving payroll: a payslip with nothing left to pay is paid on the spot; one with money left
 * stays owed (setting on_entry) or is paid from the chosen account (setting on_approval).
 * Self-contained helpers, distinctly named, as each test file runs on its own.
 */
function payRecCompany(): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Pay Rec '.str()->random(6),
        'slug' => 'pay-rec-'.str()->lower(str()->random(10)),
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
        'company_id' => $company->id, 'employee_number' => 'EMP-PR-1', 'first_name' => 'Pay', 'last_name' => 'Rec',
        'hire_date' => '2026-01-01', 'employment_type' => 'full_time', 'employment_status' => 'active',
        'pay_frequency' => 'monthly', 'base_salary' => 20000, 'currency' => 'PKR', 'is_active' => true,
    ]);

    return [$user, $company, $employee];
}

function payRecAccount(Company $company, string $code, string $subtype = 'cash'): Account
{
    return Account::create([
        'company_id' => $company->id, 'code' => $code, 'name' => ($subtype === 'cash' ? 'Cash ' : 'Bank ').$code,
        'type' => 'asset', 'subtype' => $subtype, 'normal_balance' => 'debit', 'is_active' => true,
    ]);
}

function payRecDraft(Company $company): Payslip
{
    app(MonthEndPayrollDraft::class)->prepare($company, '2026-09-01');

    return Payslip::where('company_id', $company->id)->sole();
}

function payRecSetting(Company $company, string $mode, ?string $accountId = null): void
{
    $company->refresh();
    $company->settings = array_merge((array) $company->settings, ['payroll' => ['payment_recording' => $mode, 'payment_account_id' => $accountId]]);
    $company->save();
}

test('a payslip whose advances cover it is paid on approval, with no payment journal', function () {
    [$user, $company, $employee] = payRecCompany();
    SalaryAdvance::create([
        'company_id' => $company->id, 'employee_id' => $employee->id, 'advance_date' => '2026-09-10',
        'amount' => 20000, 'amount_outstanding' => 20000, 'reason' => 'Advance', 'status' => 'pending', 'payment_method' => 'cash',
    ]);
    payRecSetting($company, 'on_entry');
    $payslip = payRecDraft($company);

    app(PayrollPostingService::class)->approve($payslip, $user->id);

    $payslip->refresh();
    expect((float) $payslip->net_pay)->toBe(0.0)
        ->and($payslip->status)->toBe('paid')
        ->and($payslip->paid_at)->not->toBeNull()
        ->and($payslip->payment_gl_transaction_id)->toBeNull();
});

test('on_entry leaves an approved payslip with money left owed', function () {
    [$user, $company] = payRecCompany();
    payRecAccount($company, '1011');
    payRecSetting($company, 'on_entry');
    $payslip = payRecDraft($company);

    app(PayrollPostingService::class)->approve($payslip, $user->id);

    $payslip->refresh();
    expect($payslip->status)->toBe('approved')
        ->and($payslip->payment_gl_transaction_id)->toBeNull();
});

test('on_approval pays the net from the chosen cash account when payroll is approved', function () {
    [$user, $company] = payRecCompany();
    payRecAccount($company, '1011');
    $chosen = payRecAccount($company, '1012');
    payRecSetting($company, 'on_approval', $chosen->id);
    $payslip = payRecDraft($company);

    app(PayrollPostingService::class)->approve($payslip, $user->id);

    $payslip->refresh();
    expect($payslip->status)->toBe('paid')
        ->and($payslip->payment_method)->toBe('cash')
        ->and($payslip->payment_gl_transaction_id)->not->toBeNull();

    $payment = Transaction::where('company_id', $company->id)->findOrFail($payslip->payment_gl_transaction_id);
    $credit = DB::table('acct.journal_entries')
        ->where('transaction_id', $payment->id)->where('account_id', $chosen->id)->where('type', 'credit')->sum('amount');
    expect((float) $credit)->toBe(20000.0);
});

test('the settings endpoint saves, keeps other keys, and rejects a non-cash account', function () {
    [$user, $company] = payRecCompany();
    $cash = payRecAccount($company, '1011');
    $expense = Account::create([
        'company_id' => $company->id, 'code' => '6999', 'name' => 'Misc expense',
        'type' => 'expense', 'subtype' => 'operating_expenses', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    $this->actingAs($user)
        ->post("/{$company->slug}/payroll/settings", ['payment_recording' => 'on_approval', 'payment_account_id' => $expense->id])
        ->assertSessionHasErrors('payment_account_id');

    $this->actingAs($user)
        ->post("/{$company->slug}/payroll/settings", ['payment_recording' => 'on_approval', 'payment_account_id' => $cash->id])
        ->assertSessionHasNoErrors();

    $settings = (array) $company->refresh()->settings;
    expect($settings['payroll'])->toBe(['payment_recording' => 'on_approval', 'payment_account_id' => $cash->id])
        ->and($settings['modules']['payroll'])->toBeTrue()
        ->and(app(PayrollPostingService::class)->paymentRecording($company->id))->toBe(['mode' => 'on_approval', 'account_id' => $cash->id]);
});
