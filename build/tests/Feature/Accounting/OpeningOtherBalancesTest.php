<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Validation\ValidationException;

/**
 * "Other balances": any balance-sheet account no other opening section posts to (fixed assets,
 * loans, prepaid, capital). No table of their own -- read back from the opening journal's lines.
 */
require_once __DIR__.'/OpeningBalanceFixtures.php';

function otherBalancesFixture(bool $http = false): array
{
    $f = $http ? openingBalanceHttpFixture() : openingBalanceFixture();
    $mk = fn (string $code, string $name, string $type, string $subtype, string $normal) => Account::create([
        'company_id' => $f['company']->id, 'code' => $code, 'name' => $name, 'type' => $type,
        'subtype' => $subtype, 'normal_balance' => $normal, 'is_active' => true,
    ]);
    $f['accounts']['equipment'] = $mk('1500', 'Furniture & Equipment', 'asset', 'fixed_asset', 'debit');
    $f['accounts']['loan'] = $mk('2500', 'Loans Payable', 'liability', 'long_term_liability', 'credit');

    return $f;
}

function otherBalancesView(array $f): array
{
    test()->actingAs($f['user']);

    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.view', [], $f['user'], true));
}

function otherBalancesJournal(string $journalId): array
{
    return Transaction::find($journalId)->journalEntries->map(fn ($e) => [
        'account' => Account::find($e->account_id)->code,
        'debit' => (float) $e->debit_amount,
        'credit' => (float) $e->credit_amount,
    ])->all();
}

test('other balances post on each account\'s normal side against 3080', function () {
    $f = otherBalancesFixture();

    $result = dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'accounts' => [
            ['account_id' => $f['accounts']['equipment']->id, 'amount' => 500000],
            ['account_id' => $f['accounts']['loan']->id, 'amount' => 200000],
        ],
    ]);

    $lines = otherBalancesJournal($result['data']['journal_id']);
    expect($lines)->toContain(['account' => '1500', 'debit' => 500000.0, 'credit' => 0.0])
        ->and($lines)->toContain(['account' => '2500', 'debit' => 0.0, 'credit' => 200000.0])
        ->and($lines)->toContain(['account' => '3080', 'debit' => 0.0, 'credit' => 300000.0]);
});

test('the view reads other balances back and adds them to assets and liabilities', function () {
    $f = otherBalancesFixture();
    dispatchOpeningBalance($f, [
        'as_of_date' => '2026-08-31',
        'accounts' => [
            ['account_id' => $f['accounts']['equipment']->id, 'amount' => 500000],
            ['account_id' => $f['accounts']['loan']->id, 'amount' => 200000],
        ],
    ]);

    $view = otherBalancesView($f);

    expect(collect($view['rows']['accounts'])->map(fn ($r) => [$r['code'], $r['amount'], $r['normal_balance']])->all())
        ->toBe([['1500', 500000.0, 'debit'], ['2500', 200000.0, 'credit']])
        ->and($view['totals']['assets'])->toBe(500000.0)
        ->and($view['totals']['liabilities'])->toBe(200000.0)
        ->and(collect($view['options']['accounts'])->pluck('code')->all())->toContain('1500', '2500');
});

test('entering other balances again replaces them instead of doubling', function () {
    $f = otherBalancesFixture();
    $payload = ['as_of_date' => '2026-08-31', 'accounts' => [['account_id' => $f['accounts']['equipment']->id, 'amount' => 500000]]];

    dispatchOpeningBalance($f, $payload);
    dispatchOpeningBalance($f, $payload);
    expect(ledgerBalance($f['accounts']['equipment']))->toBe(500000.0)
        ->and(otherBalancesView($f)['rows']['accounts'])->toHaveCount(1);

    $payload['accounts'][0]['amount'] = 450000;
    dispatchOpeningBalance($f, $payload);
    expect(ledgerBalance($f['accounts']['equipment']))->toBe(450000.0);
});

test('accounts that another section posts to cannot be chosen', function () {
    $f = otherBalancesFixture();
    $forbidden = ['ar', 'ap', 'bank', 'cash', 'advances', 'partner'];

    foreach ($forbidden as $key) {
        try {
            dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'accounts' => [['account_id' => $f['accounts'][$key]->id, 'amount' => 100]]]);
            $this->fail("{$key} was accepted");
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey('accounts.0.account_id');
        }
    }

    $equity = Account::where('company_id', $f['company']->id)->where('code', '3080')->first()
        ?? app(\App\Modules\Accounting\Services\OpeningBalanceAccounts::class)->resolve($f['company']->id)['equity'];
    $equityId = is_string($equity) ? $equity : $equity->id;
    expect(fn () => dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'accounts' => [['account_id' => $equityId, 'amount' => 100]]]))
        ->toThrow(ValidationException::class);

    $options = collect(otherBalancesView($f)['options']['accounts'])->pluck('code')->all();
    expect($options)->not->toContain('1100', '2100', '1000', '1050', '1150', '2210', '3080');
});

test('a general company can use 2200 here, since it has no amanat section', function () {
    $f = otherBalancesFixture();

    $result = dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'accounts' => [['account_id' => $f['accounts']['amanat']->id, 'amount' => 1000]]]);

    expect(otherBalancesJournal($result['data']['journal_id']))->toContain(['account' => '2200', 'debit' => 0.0, 'credit' => 1000.0]);
});

test('a one-line setter keeps other balances (OpeningSet carries accounts)', function () {
    $f = otherBalancesFixture(true);
    dispatchOpeningBalance($f, ['as_of_date' => '2026-08-31', 'accounts' => [['account_id' => $f['accounts']['equipment']->id, 'amount' => 500000]]]);

    test()->actingAs($f['user']);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('opening_balance.set_account', [
        'gl_account_id' => $f['accounts']['cash']->id, 'amount' => 7000,
    ], $f['user']));

    $view = otherBalancesView($f);
    expect($view['rows']['accounts'])->toHaveCount(1)
        ->and($view['rows']['cash']['amount'])->toBe(7000.0)
        ->and(ledgerBalance($f['accounts']['equipment']))->toBe(500000.0);
});
