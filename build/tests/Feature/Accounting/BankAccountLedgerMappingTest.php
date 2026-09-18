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
