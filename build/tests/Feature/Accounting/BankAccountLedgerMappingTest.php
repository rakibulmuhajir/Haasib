<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;

/**
 * A bank record owns exactly one ledger account. Postings hit the ledger account, not the
 * record, so two records sharing one make their balances indistinguishable -- a transfer
 * between them would debit and credit the same account, and reconcile to nothing.
 *
 * Found in production data: a company had Meezan, MCB and UBL all pointing at the single
 * "Operating Bank Account" (1000), one of them typed cash against a bank-subtype account.
 * No money had posted yet. gl_account_id was nullable with an unscoped exists rule, so
 * nothing stopped it.
 *
 * Reuses httpAllocationFixture() from PaymentAllocationTest.php, which Pest loads when the
 * Accounting directory is run together (module scope), per project convention.
 */
function bankMappingFixture(): array
{
    $f = httpAllocationFixture();
    test()->actingAs($f['owner']);

    return $f;
}

function postBankAccount(array $f, array $overrides = []): \Illuminate\Testing\TestResponse
{
    return test()->post("/{$f['company']->slug}/banking/accounts", array_merge([
        'account_name' => 'Meezan',
        'account_number' => 'ACC-'.str()->random(8),
        'account_type' => 'checking',
        'currency' => 'PKR',
    ], $overrides));
}

test('a bank record created without a ledger account is given its own', function () {
    $f = bankMappingFixture();

    postBankAccount($f)->assertSessionHasNoErrors();

    $record = BankAccount::where('company_id', $f['company']->id)->where('account_name', 'Meezan')->sole();
    expect($record->gl_account_id)->not->toBeNull();

    $ledger = Account::whereKey($record->gl_account_id)->sole();
    expect($ledger->subtype)->toBe('bank')
        ->and($ledger->company_id)->toBe($f['company']->id)
        ->and((int) $ledger->code)->toBeGreaterThanOrEqual(1000)
        ->and((int) $ledger->code)->toBeLessThanOrEqual(1049);
});

test('two bank records never share a ledger account', function () {
    $f = bankMappingFixture();

    postBankAccount($f, ['account_name' => 'Meezan'])->assertSessionHasNoErrors();
    postBankAccount($f, ['account_name' => 'UBL'])->assertSessionHasNoErrors();

    $glIds = BankAccount::where('company_id', $f['company']->id)->pluck('gl_account_id');
    expect($glIds->filter())->toHaveCount($glIds->count())
        ->and($glIds->unique())->toHaveCount($glIds->count());
});

test('pointing a second record at a ledger account another record already owns is refused', function () {
    $f = bankMappingFixture();

    postBankAccount($f, ['account_name' => 'Meezan'])->assertSessionHasNoErrors();
    $taken = BankAccount::where('company_id', $f['company']->id)->where('account_name', 'Meezan')->value('gl_account_id');

    postBankAccount($f, ['account_name' => 'UBL', 'gl_account_id' => $taken])
        ->assertSessionHasErrors('gl_account_id');

    expect(BankAccount::where('company_id', $f['company']->id)->where('account_name', 'UBL')->exists())->toBeFalse();
});

test('a cash record cannot be pointed at a bank-subtype ledger account', function () {
    $f = bankMappingFixture();

    postBankAccount($f, ['account_name' => 'Meezan'])->assertSessionHasNoErrors();
    $bankLedger = BankAccount::where('company_id', $f['company']->id)->where('account_name', 'Meezan')->value('gl_account_id');
    BankAccount::where('company_id', $f['company']->id)->where('account_name', 'Meezan')->delete();

    // This is the MCB row from the production data: account_type cash, ledger subtype bank.
    postBankAccount($f, ['account_name' => 'MCB', 'account_type' => 'cash', 'gl_account_id' => $bankLedger])
        ->assertSessionHasErrors('gl_account_id');
});

test('a ledger account belonging to another company is refused', function () {
    $f = bankMappingFixture();
    $other = bankMappingFixture();
    $otherLedger = Account::where('company_id', $other['company']->id)->where('subtype', 'cash')->value('id');

    test()->actingAs($f['owner']);
    postBankAccount($f, ['account_name' => 'Borrowed', 'gl_account_id' => $otherLedger])
        ->assertSessionHasErrors('gl_account_id');
});

test('a cash drawer record gets a cash ledger account, not a bank one', function () {
    $f = bankMappingFixture();

    postBankAccount($f, ['account_name' => 'Front Drawer', 'account_type' => 'cash'])->assertSessionHasNoErrors();

    $record = BankAccount::where('company_id', $f['company']->id)->where('account_name', 'Front Drawer')->sole();
    expect(Account::whereKey($record->gl_account_id)->value('subtype'))->toBe('cash');
});

test('bank ledger repair creates a separate bank account and posts the withdrawal and Amanat to it', function () {
    $f = bankMappingFixture();
    $record = BankAccount::create([
        'company_id' => $f['company']->id, 'account_name' => 'Main Bank',
        'account_number' => 'MAIN-REPAIR', 'account_type' => 'checking',
        'currency' => 'PKR', 'gl_account_id' => $f['cash']->id, 'is_active' => true,
    ]);

    $this->put("/{$f['company']->slug}/banking/accounts/{$record->id}", [
        'account_name' => 'Main Bank', 'account_number' => 'MAIN-REPAIR',
        'account_type' => 'checking', 'currency' => 'PKR', 'gl_account_id' => null,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $bankId = $record->fresh()->gl_account_id;
    expect($bankId)->not->toBe($f['cash']->id)
        ->and(Account::findOrFail($bankId)->subtype)->toBe('bank')
        ->and($f['cash']->fresh()->subtype)->toBe('cash');

    $this->post("/{$f['company']->slug}/banking/transactions", [
        'kind' => 'withdrawal', 'date' => '2026-09-18', 'amount' => 25000,
        'cash_account_id' => $f['cash']->id, 'bank_account_id' => $bankId,
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $transaction = \App\Modules\Accounting\Models\Transaction::where('company_id', $f['company']->id)
        ->where('reference_type', 'acct.bank_transactions')->sole();
    expect((float) $transaction->journalEntries->where('account_id', $bankId)->sum('credit_amount'))->toBe(25000.0)
        ->and((float) $transaction->journalEntries->where('account_id', $f['cash']->id)->sum('debit_amount'))->toBe(25000.0);

    $amanat = app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Modules\FuelStation\Services\AmanatService::class)->deposit($f['customer'], [
        'amount' => 60000, 'payment_account_id' => $bankId, 'business_date' => '2026-09-18',
    ])
    );
    expect($amanat->payment_account_id)->toBe($bankId);
    $entry = \Illuminate\Support\Facades\DB::table('acct.journal_entries')->where('id', $amanat->journal_entry_id)->first();
    expect($entry->account_id)->toBe($bankId)->and((float) $entry->debit_amount)->toBe(60000.0);

    // Manual movements post directly to the ledger, without an imported bank-feed row.
    // They must still prevent remapping the account and losing its displayed history.
    $this->put("/{$f['company']->slug}/banking/accounts/{$record->id}", [
        'account_name' => 'Main Bank', 'account_number' => 'MAIN-REPAIR',
        'account_type' => 'checking', 'currency' => 'PKR', 'gl_account_id' => null,
    ])->assertSessionHasErrors('gl_account_id');
    expect($record->fresh()->gl_account_id)->toBe($bankId);
});

test('bank ledger update rejects cash and shared ledger mappings', function () {
    $f = bankMappingFixture();
    postBankAccount($f)->assertSessionHasNoErrors();
    postBankAccount($f, ['account_name' => 'Other Bank'])->assertSessionHasNoErrors();
    $record = BankAccount::where('company_id', $f['company']->id)->where('account_name', 'Meezan')->sole();
    $other = BankAccount::where('company_id', $f['company']->id)->where('account_name', 'Other Bank')->sole();
    foreach ([$f['cash']->id, $other->gl_account_id] as $invalidId) {
        $this->put("/{$f['company']->slug}/banking/accounts/{$record->id}", [
            'account_name' => $record->account_name, 'account_number' => $record->account_number,
            'account_type' => 'checking', 'currency' => 'PKR', 'gl_account_id' => $invalidId,
        ])->assertSessionHasErrors('gl_account_id');
    }
    expect($record->fresh()->gl_account_id)->toBe($record->gl_account_id);
});

test('a legacy bank linked to posted cash can be deactivated without rewriting the ledger', function () {
    $f = bankMappingFixture();
    $record = BankAccount::create([
        'company_id' => $f['company']->id, 'account_name' => 'Legacy Bank',
        'account_number' => 'LEGACY-CASH', 'account_type' => 'checking',
        'currency' => 'PKR', 'gl_account_id' => $f['cash']->id, 'is_active' => true,
    ]);
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Modules\FuelStation\Services\AmanatService::class)->deposit($f['customer'], [
        'amount' => 15000, 'payment_account_id' => $f['cash']->id, 'business_date' => '2026-09-18',
    ])
    );
    $this->put("/{$f['company']->slug}/banking/accounts/{$record->id}", [
        'account_name' => $record->account_name, 'account_number' => $record->account_number,
        'account_type' => 'checking', 'gl_account_id' => $f['cash']->id, 'is_active' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->is_active)->toBeFalse()
        ->and($record->fresh()->gl_account_id)->toBe($f['cash']->id)
        ->and($f['cash']->fresh()->subtype)->toBe('cash');
});
