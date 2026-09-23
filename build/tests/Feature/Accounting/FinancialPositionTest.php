<?php

use App\Modules\Accounting\Services\DashboardService;
use App\Services\CompanyContextService;
use App\Services\CommandBus;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/OpeningBalanceFixtures.php';

/**
 * The dashboard's money figures come from the general ledger.
 *
 * getCashPosition used to sum acct.company_bank_accounts.current_balance, which is the
 * statement side: acct.update_account_balance() maintains it from imported bank feed rows and
 * nothing else writes it. Haasib's money does not arrive that way - a daily close posts
 * journals, and so do invoice payments, bill payments and transfers, none of which creates a
 * feed row.
 *
 * On the manual E2E company, which had traded five days and held 3,527,862 in the ledger,
 * the dashboard reported its cash as 0.00. A figure that is only correct for companies
 * importing bank statements is not a cash position; it is a reconciliation input that was
 * being read as one.
 *
 * Fixtures come from OpeningBalanceFixtures.php, required above.
 */
function positionOf(array $f): array
{
    return app(CompanyContextService::class)->withContext(
        $f['company'],
        fn () => app(DashboardService::class)->getFinancialPosition($f['company']->id)
    );
}

test('cash comes from the ledger, not the bank feed column', function () {
    $f = openingBalanceHttpFixture();

    // A journal, with no bank feed row behind it - the shape every daily close has.
    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'cash' => ['amount' => 462170],
    ]);

    // The statement column is deliberately left at zero: this is exactly the state that made
    // the old dashboard report nothing while the ledger held the money.
    expect(positionOf($f)['cash'])->toBe(462170.0);
});

test('a stale statement column cannot contradict the ledger', function () {
    $f = openingBalanceHttpFixture();

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'banks' => [['account_id' => $f['accounts']['bank']->id, 'amount' => 800000]],
    ]);

    // Whatever the feed side says, the ledger is what the company holds.
    DB::table('acct.company_bank_accounts')
        ->where('company_id', $f['company']->id)
        ->update(['current_balance' => 1]);

    expect(positionOf($f)['bank'])->toBe(800000.0);
});

test('it separates what is countable tonight from what has to clear', function () {
    $f = openingBalanceHttpFixture();

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'cash' => ['amount' => 50000],
        'banks' => [['account_id' => $f['accounts']['bank']->id, 'amount' => 200000]],
    ]);

    $position = positionOf($f);

    expect($position['cash'])->toBe(50000.0)
        ->and($position['bank'])->toBe(200000.0);
});

test('the net position is what is held plus what is owed to us, less what we owe', function () {
    $f = openingBalanceHttpFixture();
    $customer = openingCustomer($f, 'Al-Habib Transport');

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'cash' => ['amount' => 50000],
        'banks' => [['account_id' => $f['accounts']['bank']->id, 'amount' => 200000]],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 120000]],
    ]);

    $position = positionOf($f);

    expect($position['receivable'])->toBe(120000.0)
        ->and($position['net'])->toBe(370000.0);
});

test('every figure can be opened, and the parts add up to the whole', function () {
    $f = openingBalanceHttpFixture();
    $customer = openingCustomer($f, 'Al-Habib Transport');

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'cash' => ['amount' => 50000],
        'banks' => [
            ['account_id' => $f['accounts']['bank']->id, 'amount' => 200000],
            ['account_id' => $f['accounts']['bank2']->id, 'amount' => 90000],
        ],
        'credit_customers' => [['customer_id' => $customer->id, 'amount' => 120000]],
    ]);

    $position = positionOf($f);

    // A drill-down that does not reconcile to the figure it was opened from is worse than no
    // drill-down: the user is left with two numbers and no way to choose between them.
    foreach (['cash', 'bank', 'receivable', 'payable'] as $key) {
        $sum = round(collect($position['breakdown'][$key])->sum('amount'), 2);
        expect($sum)->toBe(round($position[$key], 2), "breakdown for [{$key}] does not sum to its headline");
    }

    expect($position['breakdown']['bank'])->toHaveCount(2)
        ->and($position['breakdown']['receivable'][0]['label'])->toBe('Al-Habib Transport');
});

test('a ledger entry with no document behind it still shows, as a remainder', function () {
    $f = openingBalanceHttpFixture();

    // A manual journal straight to the receivables control account, with no invoice behind
    // it. The breakdown is built from documents, so without a remainder row this money would
    // vanish from the drill-down while still counting in the headline. Posted through the
    // core journal action rather than inserted by hand, so the entry is shaped the way a real
    // one is.
    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-01', 'cash' => ['amount' => 1000]]);

    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('journal.create', [
        'transaction_date' => '2026-08-15',
        'description' => 'Receivable with no invoice',
        'post' => true,
        'entries' => [
            ['account_id' => $f['accounts']['ar']->id, 'type' => 'debit', 'amount' => 5000],
            ['account_id' => $f['accounts']['cash']->id, 'type' => 'credit', 'amount' => 5000],
        ],
    ], $f['user'], true));

    $position = positionOf($f);
    $labels = collect($position['breakdown']['receivable'])->pluck('label');

    expect($position['receivable'])->toBe(5000.0)
        ->and($labels)->toContain('Other ledger entries')
        ->and(round(collect($position['breakdown']['receivable'])->sum('amount'), 2))->toBe(5000.0);
});

test('bank accounts are listed largest first', function () {
    $f = openingBalanceHttpFixture();

    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-01',
        'banks' => [
            ['account_id' => $f['accounts']['bank']->id, 'amount' => 90000],
            ['account_id' => $f['accounts']['bank2']->id, 'amount' => 800000],
        ],
    ]);

    // The question behind the click is almost always "who is most of it".
    $amounts = collect(positionOf($f)['breakdown']['bank'])->pluck('amount')->all();

    expect($amounts)->toBe([800000.0, 90000.0]);
});
