<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankAccount;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/OpeningBalanceFixtures.php';

/**
 * The bank account page used to write acct.company_bank_accounts.opening_balance directly and
 * post nothing. The figure showed on screen while the ledger stayed at zero, and the user had
 * to enter it a second time on the Opening Balances page for it to mean anything.
 *
 * The fix is not to send them to that page. It is that both screens drive the same command:
 * opening_balance.set_account merges one account into the current opening set and hands the
 * whole thing to SaveAction, which remains the only thing that posts an opening balance. That
 * is what keeps the guards - the lock, the date bound, the advisory lock - applying no matter
 * which screen the user happens to be standing on.
 *
 * The column itself is NOT a duplicate of the ledger figure, and is deliberately kept. It is
 * the statement-side baseline that acct.update_account_balance() adds imported feed rows to,
 * and that BankReconciliationController starts a reconciliation from. Reconciliation compares
 * that side against the ledger, so the two have to be separate numbers that begin equal -
 * which is what SaveAction::syncStatementOpenings now guarantees from a single write.
 *
 * Fixtures come from OpeningBalanceFixtures.php, required above so this file also runs on
 * its own rather than only as part of a whole-directory run.
 */
function bankAccountFor(array $fixture, Account $glAccount, float $opening = 0.0): BankAccount
{
    return BankAccount::create([
        'company_id' => $fixture['company']->id,
        'gl_account_id' => $glAccount->id,
        'account_name' => $glAccount->name,
        'account_number' => '0001'.substr($glAccount->code, -3),
        'account_type' => $glAccount->subtype === 'cash' ? 'cash' : 'checking',
        'currency' => 'PKR',
        'opening_balance' => $opening,
        'is_active' => true,
    ]);
}

function setAccountOpening(array $fixture, array $params): array
{
    test()->actingAs($fixture['user']);

    return app(CompanyContextService::class)->withContext(
        $fixture['company'],
        fn () => app(CommandBus::class)->dispatch('opening_balance.set_account', $params, $fixture['user'], true)
    );
}

test('setting one account posts it to the ledger, not just onto the column', function () {
    $f = openingBalanceHttpFixture();
    $bank = bankAccountFor($f, $f['accounts']['bank']);

    setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['bank']->id,
        'amount' => 500000,
        'as_of_date' => '2026-08-01',
    ]);

    // The ledger is the part that was missing entirely before.
    expect(ledgerBalance($f['accounts']['bank']))->toBe(500000.0);

    // And the statement side agrees, from the same single write.
    expect((float) $bank->refresh()->opening_balance)->toBe(500000.0);
});

test('the statement baseline carries into current_balance', function () {
    $f = openingBalanceHttpFixture();
    $bank = bankAccountFor($f, $f['accounts']['bank']);

    setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['bank']->id,
        'amount' => 250000,
        'as_of_date' => '2026-08-01',
    ]);

    // acct.update_account_balance() only fires on feed rows, so moving the baseline has to
    // recompute current_balance itself, or the dashboard reads the old figure until the next
    // statement import.
    expect((float) $bank->refresh()->current_balance)->toBe(250000.0);
});

test('setting one account leaves the rest of the opening set alone', function () {
    $f = openingBalanceHttpFixture();
    bankAccountFor($f, $f['accounts']['bank']);
    bankAccountFor($f, $f['accounts']['bank2']);

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'cash' => ['amount' => 40000],
        'banks' => [['account_id' => $f['accounts']['bank2']->id, 'amount' => 90000]],
    ]);

    setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['bank']->id,
        'amount' => 500000,
    ]);

    // A one-account edit reposts the whole generation, so the merge is the thing that has to
    // be right: everything it did not touch must come back unchanged.
    expect(ledgerBalance($f['accounts']['bank']))->toBe(500000.0)
        ->and(ledgerBalance($f['accounts']['bank2']))->toBe(90000.0)
        ->and(ledgerBalance($f['accounts']['cash']))->toBe(40000.0);
});

test('it keeps the existing as-of date when the caller does not name one', function () {
    $f = openingBalanceHttpFixture();
    bankAccountFor($f, $f['accounts']['bank']);

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'cash' => ['amount' => 10000],
    ]);

    setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['bank']->id,
        'amount' => 500000,
    ]);

    // Changing one account's figure must not silently re-date the company's whole opening
    // position, which would move the bound the date guard enforces.
    $view = app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true)
    );

    expect($view['as_of_date'])->toBe('2026-08-01');
});

test('dropping an account to zero clears its statement baseline too', function () {
    $f = openingBalanceHttpFixture();
    $bank = bankAccountFor($f, $f['accounts']['bank']);

    setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['bank']->id,
        'amount' => 500000,
        'as_of_date' => '2026-08-01',
    ]);
    setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['bank']->id,
        'amount' => 0,
    ]);

    // A generation replaces the whole set. An account left out of the new one opens at zero;
    // if the column kept the old figure, current_balance would stay overstated for good.
    expect(ledgerBalance($f['accounts']['bank']))->toBe(0.0)
        ->and((float) $bank->refresh()->opening_balance)->toBe(0.0);
});

test('a locked opening position refuses the bank page write', function () {
    $f = openingBalanceHttpFixture();
    bankAccountFor($f, $f['accounts']['bank']);

    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-01', 'cash' => ['amount' => 10000]]);

    app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(CommandBus::class)->dispatch('opening_balance.lock', [], $f['user'], true)
    );

    // The page disables the field once it knows, but that is a courtesy. This is the line
    // that actually holds, and it holds for any caller that did not bother to look.
    expect(fn () => setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['bank']->id,
        'amount' => 500000,
    ]))->toThrow(ValidationException::class);
});

test('it refuses an account that is not a bank or cash account of this company', function () {
    $f = openingBalanceHttpFixture();

    expect(fn () => setAccountOpening($f, [
        'gl_account_id' => $f['accounts']['ap']->id,
        'amount' => 5000,
    ]))->toThrow(ValidationException::class);
});

test('saving from the Opening Balances page also refreshes the statement baseline', function () {
    $f = openingBalanceHttpFixture();
    $bank = bankAccountFor($f, $f['accounts']['bank']);

    // This direction was stale before: the page posted the journal and never touched the
    // column, so the bank screen kept showing whatever had been typed into it.
    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'banks' => [['account_id' => $f['accounts']['bank']->id, 'amount' => 777000]],
    ]);

    expect((float) $bank->refresh()->opening_balance)->toBe(777000.0);
});

test('the bank account form no longer writes the column directly', function () {
    $f = openingBalanceHttpFixture();

    $response = test()->actingAs($f['user'])->post("/{$f['company']->slug}/banking/accounts", [
        'account_name' => 'Meezan Operating',
        'account_number' => '00112233',
        'account_type' => 'checking',
        'currency' => 'PKR',
        'gl_account_id' => $f['accounts']['bank']->id,
        'opening_balance' => 350000,
        'opening_balance_date' => '2026-08-01',
    ]);

    $response->assertRedirect();

    $created = BankAccount::where('company_id', $f['company']->id)
        ->where('account_name', 'Meezan Operating')
        ->firstOrFail();

    // The figure reaches the column only by way of the command that also posts the journal.
    // Both, or neither - never the column on its own, which was the whole defect.
    expect(ledgerBalance($f['accounts']['bank']))->toBe(350000.0)
        ->and((float) $created->opening_balance)->toBe(350000.0);
});
