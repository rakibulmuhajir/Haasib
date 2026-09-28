<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Services\BankReconciliationService;
use App\Modules\Accounting\Services\GlPostingService;
use Illuminate\Support\Facades\DB;

/**
 * Reconciling against the books: the lines are the journal entries on the bank's ledger
 * account. An imported statement ticks what matches; a bank charge missing from the books is
 * booked from the statement line; completing needs the difference at zero.
 */
test('a statement is matched to the books, a missing charge is booked, and the bank reconciles', function () {
    $company = Company::create(['name' => 'Recon Co', 'slug' => 'recon-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $account = fn (string $code, string $type, string $subtype, string $normal) => Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code,
        'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $type === 'asset' ? 'PKR' : null, 'is_active' => true]);
    $ubl = $account('1000', 'asset', 'bank', 'debit');
    $cash = $account('1050', 'asset', 'cash', 'debit');
    $charges = $account('6160', 'expense', 'operating_expense', 'debit');
    $bank = BankAccount::create(['company_id' => $company->id, 'account_name' => 'UBL', 'account_number' => '0123', 'currency' => 'PKR', 'gl_account_id' => $ubl->id]);

    $post = fn (string $date, float $amount, string $text) => app(GlPostingService::class)->postBalancedTransaction(
        ['company_id' => $company->id, 'transaction_type' => 'journal', 'date' => $date, 'currency' => 'PKR', 'description' => $text], [
            ['account_id' => $amount > 0 ? $ubl->id : $cash->id, 'type' => 'debit', 'amount' => abs($amount), 'description' => $text],
            ['account_id' => $amount > 0 ? $cash->id : $ubl->id, 'type' => 'credit', 'amount' => abs($amount), 'description' => $text],
        ]);
    $post('2026-09-01', 500000, 'Cash deposited');
    $post('2026-09-03', -200000, 'Cash withdrawn');
    $post('2026-09-05', 300000, 'Cash deposited');   // not on this statement yet

    $recon = BankReconciliation::create(['company_id' => $company->id, 'bank_account_id' => $bank->id, 'statement_date' => '2026-09-04',
        'statement_ending_balance' => 299850, 'book_balance' => 0, 'status' => 'in_progress']);
    $csv = tempnam(sys_get_temp_dir(), 'stmt');
    file_put_contents($csv, "Account statement,,,,\nDate,Narration,Withdrawal,Deposit,Balance\n"
        ."02/09/2026,Cash deposit,,\"500,000.00\",\"500,000.00\"\n"
        ."03/09/2026,Cash withdrawal,\"200,000.00\",,\"300,000.00\"\n"
        ."04/09/2026,Bank charges,150.00,,\"299,850.00\"\n");

    $service = app(BankReconciliationService::class);
    $recon->load('bankAccount');
    expect($service->importStatement($recon, $csv))->toBe(2);

    $view = $service->view($recon->fresh('bankAccount'));
    expect($view['summary']['cleared_balance'])->toBe(300000.0)
        ->and($view['summary']['difference'])->toBe(-150.0)
        ->and($view['summary']['unmatched_statement'])->toBe(1);

    $charge = collect($view['statement'])->firstWhere('journal_entry_id', null);
    $service->addEntry($recon->fresh('bankAccount'), $charge['id'], $charges->id, null);
    $service->complete($recon->fresh('bankAccount'), null);

    $recon->refresh();
    expect($recon->status)->toBe('completed')
        ->and((float) $recon->reconciled_balance)->toBe(299850.0)
        ->and((float) $bank->fresh()->last_reconciled_balance)->toBe(299850.0)
        // The 5 Sep deposit was not on the statement: still open for the next one.
        ->and(DB::table('acct.bank_reconciliation_items')->where('reconciliation_id', $recon->id)->count())->toBe(3);
});
