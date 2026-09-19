<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

function bankTransactionFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Bank Txn Co', 'slug' => 'bank-txn-'.str()->random(8), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $cash = Account::create(['company_id' => $company->id, 'code' => '1050', 'name' => 'Cash on Hand', 'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $bank1 = Account::create(['company_id' => $company->id, 'code' => '1020', 'name' => 'Operating Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $bank2 = Account::create(['company_id' => $company->id, 'code' => '1021', 'name' => 'Savings Bank', 'type' => 'asset', 'subtype' => 'bank', 'normal_balance' => 'debit', 'currency' => 'PKR']);
    $charges = Account::create(['company_id' => $company->id, 'code' => '6300', 'name' => 'Bank Charges', 'type' => 'expense', 'subtype' => 'operating_expense', 'normal_balance' => 'debit', 'currency' => null]);

    return compact('user', 'company', 'cash', 'bank1', 'bank2', 'charges');
}

function dispatchBankTxn(array $f, array $params)
{
    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bank_transaction.create', $params, $f['user'], true));
}

test('a deposit posts Dr Bank Cr Cash', function () {
    $f = bankTransactionFixture();
    $result = dispatchBankTxn($f, ['kind' => 'deposit', 'date' => '2026-09-15', 'amount' => 1000, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank1']->id]);
    $entries = Transaction::findOrFail($result['data']['id'])->journalEntries;
    expect((float) $entries->where('account_id', $f['bank1']->id)->sum('debit_amount'))->toBe(1000.0);
    expect((float) $entries->where('account_id', $f['cash']->id)->sum('credit_amount'))->toBe(1000.0);
});

test('a withdrawal posts Dr Cash Cr Bank', function () {
    $f = bankTransactionFixture();
    $result = dispatchBankTxn($f, ['kind' => 'withdrawal', 'date' => '2026-09-15', 'amount' => 500, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank1']->id]);
    $entries = Transaction::findOrFail($result['data']['id'])->journalEntries;
    expect((float) $entries->where('account_id', $f['cash']->id)->sum('debit_amount'))->toBe(500.0);
    expect((float) $entries->where('account_id', $f['bank1']->id)->sum('credit_amount'))->toBe(500.0);
});

test('a bank record linked to cash cannot turn a withdrawal into a same-account journal', function () {
    $f = bankTransactionFixture();
    \App\Modules\Accounting\Models\BankAccount::create([
        'company_id' => $f['company']->id, 'account_name' => 'Misconfigured Main Bank',
        'account_number' => 'LEGACY', 'account_type' => 'checking', 'currency' => 'PKR',
        'gl_account_id' => $f['cash']->id, 'is_active' => true,
    ]);
    expect(fn () => dispatchBankTxn($f, [
        'kind' => 'withdrawal', 'date' => '2026-09-18', 'amount' => 25000,
        'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['cash']->id,
    ]))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe(0);
});

test('a transfer posts Dr destination Cr source', function () {
    $f = bankTransactionFixture();
    $result = dispatchBankTxn($f, ['kind' => 'transfer', 'date' => '2026-09-15', 'amount' => 750, 'from_bank_account_id' => $f['bank1']->id, 'to_bank_account_id' => $f['bank2']->id]);
    $entries = Transaction::findOrFail($result['data']['id'])->journalEntries;
    expect((float) $entries->where('account_id', $f['bank2']->id)->sum('debit_amount'))->toBe(750.0);
    expect((float) $entries->where('account_id', $f['bank1']->id)->sum('credit_amount'))->toBe(750.0);
});

test('a bank charge posts Dr Bank Charges Cr Bank', function () {
    $f = bankTransactionFixture();
    $result = dispatchBankTxn($f, ['kind' => 'charge', 'date' => '2026-09-15', 'amount' => 25, 'bank_account_id' => $f['bank1']->id, 'expense_account_id' => $f['charges']->id]);
    $entries = Transaction::findOrFail($result['data']['id'])->journalEntries;
    expect((float) $entries->where('account_id', $f['charges']->id)->sum('debit_amount'))->toBe(25.0);
    expect((float) $entries->where('account_id', $f['bank1']->id)->sum('credit_amount'))->toBe(25.0);
});

test('every kind produces a balanced transaction', function () {
    $f = bankTransactionFixture();
    foreach ([
        ['kind' => 'deposit', 'amount' => 100, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank1']->id],
        ['kind' => 'withdrawal', 'amount' => 50, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank1']->id],
        ['kind' => 'transfer', 'amount' => 30, 'from_bank_account_id' => $f['bank1']->id, 'to_bank_account_id' => $f['bank2']->id],
        ['kind' => 'charge', 'amount' => 5, 'bank_account_id' => $f['bank1']->id, 'expense_account_id' => $f['charges']->id],
    ] as $params) {
        $result = dispatchBankTxn($f, $params + ['date' => '2026-09-15']);
        $entries = Transaction::findOrFail($result['data']['id'])->journalEntries;
        expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));
    }
});

test('an other-company account is rejected', function () {
    $f = bankTransactionFixture();
    $other = bankTransactionFixture();
    expect(fn () => dispatchBankTxn($f, ['kind' => 'deposit', 'date' => '2026-09-15', 'amount' => 100, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $other['bank1']->id]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('permission is enforced for recording a bank transaction', function () {
    $f = bankTransactionFixture();
    $stranger = User::factory()->create();
    expect(fn () => app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bank_transaction.create', [
        'kind' => 'deposit', 'date' => '2026-09-15', 'amount' => 100, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank1']->id,
    ], $stranger)))->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

test('a withdrawal raises and a deposit lowers the daily close expected cash for that date, and does not double count against a close-entered deposit', function () {
    $f = bankTransactionFixture();
    $result = dispatchBankTxn($f, ['kind' => 'withdrawal', 'date' => '2026-09-15', 'amount' => 500, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank1']->id]);
    $sources = app(\App\Modules\FuelStation\Services\DailyCloseReconciliationService::class)->sources($f['company']->id, '2026-09-15', null, $f['cash']->id);
    $source = collect($sources)->firstWhere('id', $result['data']['id']);
    expect($source)->not->toBeNull()->and((float) $source['cash_effect'])->toBe(500.0);

    $depositResult = dispatchBankTxn($f, ['kind' => 'deposit', 'date' => '2026-09-16', 'amount' => 200, 'cash_account_id' => $f['cash']->id, 'bank_account_id' => $f['bank1']->id]);
    $sources16 = app(\App\Modules\FuelStation\Services\DailyCloseReconciliationService::class)->sources($f['company']->id, '2026-09-16', null, $f['cash']->id);
    $depositSource = collect($sources16)->firstWhere('id', $depositResult['data']['id']);
    expect((float) $depositSource['cash_effect'])->toBe(-200.0);
    // Each date's sources contains exactly one row for its own transaction, not the other date's.
    expect(collect($sources)->firstWhere('id', $depositResult['data']['id']))->toBeNull();
});
