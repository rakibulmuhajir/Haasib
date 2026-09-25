<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\PayrollPeriod;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\PayslipLine;
use App\Modules\Payroll\Services\PayrollPostingService;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting payslips from the table entirely (owner-only), separate from void() which keeps the
 * record and marks it cancelled:
 *  - draft: no accounting exists yet, so it and its lines simply go.
 *  - approved and unpaid: its accrual journal is reversed through the same path void() uses,
 *    then it and its lines go.
 *  - paid: refused - the payment has to be undone first.
 * Bulk delete runs each payslip in its own transaction so one refusal (a paid one mixed into
 * the selection) never rolls back the rows that were deletable.
 */
function psDeleteCompany(): Company
{
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payslip Delete '.str()->random(6),
        'slug' => 'payslip-delete-'.str()->lower(str()->random(10)),
        'base_currency' => 'PKR',
        'settings' => ['modules' => ['payroll' => true]],
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    Auth::login($user);

    return $company;
}

function psDeleteEmployee(Company $company): Employee
{
    return Employee::create([
        'company_id' => $company->id,
        'employee_number' => 'EMP-'.str()->upper(str()->random(6)),
        'first_name' => 'Delete',
        'last_name' => 'Test',
        'hire_date' => '2026-01-01',
        'employment_type' => 'full_time',
        'employment_status' => 'active',
        'pay_frequency' => 'monthly',
        'base_salary' => 30000,
        'currency' => 'PKR',
        'is_active' => true,
    ]);
}

function psDeletePeriod(Company $company): PayrollPeriod
{
    return PayrollPeriod::firstOrCreate(
        ['company_id' => $company->id, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31'],
        ['payment_date' => '2026-09-01', 'status' => 'open'],
    );
}

function psDeleteDraftPayslip(Company $company, Employee $employee, PayrollPeriod $period): Payslip
{
    $service = app(PayrollPostingService::class);

    $payslip = Payslip::create([
        'company_id' => $company->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'payslip_number' => $service->nextPayslipNumber($company->id),
        'currency' => 'PKR',
        'exchange_rate' => null,
        'base_currency' => 'PKR',
        'status' => 'draft',
    ]);

    $earningType = $service->ensureBaseSalaryEarningType($company->id);
    $payslip->lines()->create([
        'line_type' => 'earning',
        'earning_type_id' => $earningType->id,
        'description' => 'Base salary',
        'quantity' => 1,
        'rate' => 30000,
        'amount' => 30000,
        'sort_order' => 1,
    ]);

    return $payslip->fresh();
}

function psDeletePaymentAccount(Company $company): Account
{
    return Account::create([
        'company_id' => $company->id,
        'code' => '1011',
        'name' => 'Cash on Hand',
        'type' => 'asset',
        'subtype' => 'cash',
        'normal_balance' => 'debit',
        'is_active' => true,
        'is_system' => true,
    ]);
}

test('a draft payslip is deleted along with its lines', function () {
    $company = psDeleteCompany();
    $employee = psDeleteEmployee($company);
    $period = psDeletePeriod($company);
    $payslip = psDeleteDraftPayslip($company, $employee, $period);

    $service = app(PayrollPostingService::class);
    $service->delete($payslip, (string) auth()->id());

    expect(Payslip::find($payslip->id))->toBeNull()
        ->and(PayslipLine::where('payslip_id', $payslip->id)->count())->toBe(0);
});

test('an approved unpaid payslip is deleted and its accrual journal reversed to net zero', function () {
    $company = psDeleteCompany();
    $employee = psDeleteEmployee($company);
    $period = psDeletePeriod($company);
    $payslip = psDeleteDraftPayslip($company, $employee, $period);

    $service = app(PayrollPostingService::class);
    $transaction = $service->approve($payslip->fresh(), (string) auth()->id());
    $payslip = $payslip->fresh();

    expect($payslip->status)->toBe('approved')
        ->and($payslip->gl_transaction_id)->toBe($transaction->id);

    $service->delete($payslip, (string) auth()->id());

    expect(Payslip::find($payslip->id))->toBeNull()
        ->and(PayslipLine::where('payslip_id', $payslip->id)->count())->toBe(0);

    $reversedOriginal = Transaction::find($transaction->id);
    expect($reversedOriginal->reversed_by_id)->not->toBeNull();

    // The accrual and its reversal net to zero on every account either touched.
    $accountIds = DB::table('acct.journal_entries')
        ->where('transaction_id', $transaction->id)
        ->pluck('account_id');

    foreach ($accountIds as $accountId) {
        $rows = DB::table('acct.journal_entries')
            ->whereIn('transaction_id', [$transaction->id, $reversedOriginal->reversed_by_id])
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit_amount),0) as d, COALESCE(SUM(credit_amount),0) as c')
            ->first();

        expect(round((float) $rows->d - (float) $rows->c, 2))->toBe(0.0);
    }
});

test('a paid payslip cannot be deleted', function () {
    $company = psDeleteCompany();
    $employee = psDeleteEmployee($company);
    $period = psDeletePeriod($company);
    $payslip = psDeleteDraftPayslip($company, $employee, $period);
    $account = psDeletePaymentAccount($company);

    $service = app(PayrollPostingService::class);
    $service->approve($payslip->fresh(), (string) auth()->id());
    $service->markPaid($payslip->fresh(), [
        'paid_on' => '2026-09-05',
        'payment_method' => 'cash',
        'payment_account_id' => $account->id,
    ], (string) auth()->id());

    $paid = $payslip->fresh();
    expect($paid->status)->toBe('paid');

    expect(fn () => $service->delete($paid, (string) auth()->id()))
        ->toThrow(ValidationException::class);

    $stillThere = Payslip::find($paid->id);
    expect($stillThere)->not->toBeNull()
        ->and($stillThere->status)->toBe('paid');
});

test('bulk deleting a mix of payslips reports how many were deleted and how many were skipped', function () {
    $user = User::factory()->withoutTwoFactor()->create();
    $company = Company::create([
        'name' => 'Payslip Bulk Delete '.str()->random(6),
        'slug' => 'payslip-bulk-delete-'.str()->lower(str()->random(10)),
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

    $period = psDeletePeriod($company);
    $service = app(PayrollPostingService::class);

    $draft = psDeleteDraftPayslip($company, psDeleteEmployee($company), $period);

    $approvedUnpaid = psDeleteDraftPayslip($company, psDeleteEmployee($company), $period);
    $service->approve($approvedUnpaid->fresh(), (string) $user->id);
    $approvedUnpaid = $approvedUnpaid->fresh();

    $account = psDeletePaymentAccount($company);
    $paid = psDeleteDraftPayslip($company, psDeleteEmployee($company), $period);
    $service->approve($paid->fresh(), (string) $user->id);
    $service->markPaid($paid->fresh(), [
        'paid_on' => '2026-09-05',
        'payment_method' => 'cash',
        'payment_account_id' => $account->id,
    ], (string) $user->id);
    $paid = $paid->fresh();

    $this->actingAs($user)
        ->post("/{$company->slug}/payslips/bulk-delete", [
            'ids' => [$draft->id, $approvedUnpaid->id, $paid->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success', '2 deleted, 1 skipped (paid)');

    expect(Payslip::find($draft->id))->toBeNull()
        ->and(Payslip::find($approvedUnpaid->id))->toBeNull();

    $stillPaid = Payslip::find($paid->id);
    expect($stillPaid)->not->toBeNull()
        ->and($stillPaid->status)->toBe('paid');
});
