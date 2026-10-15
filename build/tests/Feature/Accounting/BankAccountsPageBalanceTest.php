<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/BillPaymentEditFixtures.php';

/**
 * The bank accounts page showed acct.company_bank_accounts.current_balance, which is only the
 * opening baseline. It has to read the posted ledger balance of the linked GL account.
 */
function bankPageEntry(array $f, string $status, float $debit, Account $account, Account $other): void
{
    $id = (string) str()->uuid();
    DB::table('acct.transactions')->insert([
        'id' => $id, 'company_id' => $f['company']->id, 'transaction_number' => 'T-'.str()->random(6),
        'fiscal_year_id' => $f['sepPeriod']->fiscal_year_id, 'period_id' => $f['sepPeriod']->id,
        'transaction_type' => 'manual', 'transaction_date' => '2026-09-10', 'posting_date' => '2026-09-10', 'description' => 'x',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => $debit, 'total_credit' => $debit, 'status' => $status,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([[$account, $debit, 0], [$other, 0, $debit]] as $i => [$a, $d, $c]) {
        DB::table('acct.journal_entries')->insert([
            'id' => (string) str()->uuid(), 'company_id' => $f['company']->id, 'transaction_id' => $id, 'account_id' => $a->id,
            'line_number' => $i + 1, 'debit_amount' => $d, 'credit_amount' => $c, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

test('the bank accounts page reads each balance from the ledger, not the opening figure', function () {
    $f = billPaymentEditFixture();
    BankAccount::create([
        'company_id' => $f['company']->id, 'gl_account_id' => $f['bank']->id, 'account_name' => 'Main', 'account_number' => '0001',
        'account_type' => 'checking', 'currency' => 'PKR', 'opening_balance' => 200000, 'current_balance' => 200000, 'is_active' => true,
    ]);
    bankPageEntry($f, 'posted', 200000, $f['bank'], $f['ap']);
    bankPageEntry($f, 'posted', 67000, $f['bank'], $f['ap']);
    bankPageEntry($f, 'draft', 5000, $f['bank'], $f['ap']);

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/banking/accounts")
        ->assertInertia(fn (Assert $page) => $page
            ->where('bankAccounts.data.0.ledger_balance', 267000));
});
