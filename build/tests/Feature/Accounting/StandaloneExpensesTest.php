<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function standaloneExpenseFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Expense Co', 'slug' => 'expense-co-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $rent = Account::create(['company_id' => $company->id, 'code' => '6100', 'name' => 'Rent Expense', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'currency' => null]);
    return compact('user', 'company', 'cash', 'rent');
}

function dispatchExpense(array $f, array $params)
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('expense.create', $params, $f['user'], true));
}

test('a standalone expense posts Dr Expense Cr the account paid from', function () {
    $f = standaloneExpenseFixture();
    $result = dispatchExpense($f, [
        'date' => '2026-09-15', 'account_id' => $f['rent']->id, 'amount' => 15000,
        'paid_from_account_id' => $f['cash']->id, 'description' => 'September rent',
    ]);
    $transaction = Transaction::findOrFail($result['data']['id']);
    expect($transaction->transaction_type)->toBe('expense');
    $entries = $transaction->journalEntries;
    expect((float) $entries->where('account_id', $f['rent']->id)->sum('debit_amount'))->toBe(15000.0);
    expect((float) $entries->where('account_id', $f['cash']->id)->sum('credit_amount'))->toBe(15000.0);
});

test('an other-company account is rejected', function () {
    $f = standaloneExpenseFixture();
    $other = standaloneExpenseFixture();
    expect(fn () => dispatchExpense($f, [
        'date' => '2026-09-15', 'account_id' => $other['rent']->id, 'amount' => 100,
        'paid_from_account_id' => $f['cash']->id, 'description' => 'Wrong company',
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('permission is enforced for recording a standalone expense', function () {
    $f = standaloneExpenseFixture();
    $stranger = User::factory()->create();
    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('expense.create', [
        'date' => '2026-09-15', 'account_id' => $f['rent']->id, 'amount' => 100,
        'paid_from_account_id' => $f['cash']->id, 'description' => 'No permission',
    ], $stranger)))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

test('a standalone expense appears in the daily close for its date exactly once', function () {
    $f = standaloneExpenseFixture();
    $result = dispatchExpense($f, [
        'date' => '2026-09-15', 'account_id' => $f['rent']->id, 'amount' => 15000,
        'paid_from_account_id' => $f['cash']->id, 'description' => 'September rent',
    ]);

    $sources = app(DailyCloseReconciliationService::class)->sources($f['company']->id, '2026-09-15', null, $f['cash']->id);
    $matches = collect($sources)->filter(fn ($s) => $s['id'] === $result['data']['id']);
    expect($matches)->toHaveCount(1);
    expect((float) $matches->first()['cash_effect'])->toBe(-15000.0);
});
