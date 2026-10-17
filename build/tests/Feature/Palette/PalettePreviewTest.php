<?php

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Transaction;
use App\Support\Palette\PalettePreview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/../Accounting/CashSaleFixtures.php';

function previewAs($test, array $f, string $who, string $key, array $params)
{
    return $test->actingAs($f[$who])
        ->withHeader('X-Company-Slug', $f['company']->slug)
        ->postJson('/api/palette/preview', ['key' => $key, 'params' => $params]);
}

/** Row counts of everything a posting command touches, so "nothing remains" is checked widely. */
function previewFootprint(string $companyId): array
{
    $count = fn (string $table) => DB::table($table)->where('company_id', $companyId)->count();

    return [
        'transactions' => $count('acct.transactions'),
        'journal_entries' => $count('acct.journal_entries'),
        'invoices' => $count('acct.invoices'),
        'payments' => $count('acct.payments'),
        'customers' => $count('acct.customers'),
        'next_journal' => Transaction::generateJournalNumber($companyId),
    ];
}

function depositParams(array $f, array $over = []): array
{
    return $over + [
        'kind' => 'deposit', 'date' => $f['today'], 'amount' => 5000,
        'bank_account_id' => $f['bank']->id, 'cash_account_id' => $f['cash']->id,
    ];
}

test('a deposit preview shows debit bank and credit cash, and leaves nothing behind', function () {
    $f = cashSaleFixture();
    $before = previewFootprint($f['company']->id);
    $level = DB::transactionLevel();

    $res = previewAs($this, $f, 'owner', 'bank_transaction.create', depositParams($f))->assertOk();

    expect($res->json('ok'))->toBeTrue();
    expect($res->json('lines'))->toBe([
        ['side' => 'debit', 'account_code' => '1060', 'account_name' => 'Meezan Bank', 'amount' => 5000],
        ['side' => 'credit', 'account_code' => '1050', 'account_name' => 'Cash on Hand', 'amount' => 5000],
    ]);
    expect(collect($res->json('balances'))->firstWhere('account_name', 'Meezan Bank'))->toMatchArray(['before' => 0, 'after' => 5000]);
    expect(collect($res->json('balances'))->firstWhere('account_name', 'Cash on Hand'))->toMatchArray(['before' => 0, 'after' => -5000]);
    expect(previewFootprint($f['company']->id))->toBe($before);
    expect(DB::transactionLevel())->toBe($level); // the dry run left no open savepoint behind
});

test('previewing twice leaves the counts and the next document number unchanged', function () {
    $f = cashSaleFixture();
    $before = previewFootprint($f['company']->id);

    $first = previewAs($this, $f, 'owner', 'bank_transaction.create', depositParams($f))->assertOk()->json();
    $second = previewAs($this, $f, 'owner', 'bank_transaction.create', depositParams($f))->assertOk()->json();

    expect($second['lines'])->toBe($first['lines']);
    expect(previewFootprint($f['company']->id))->toBe($before);

    // The real posting afterwards still gets the first number: the preview consumed none.
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CommandBus::class)->dispatch('bank_transaction.create', depositParams($f), $f['owner']));
    expect(DB::table('acct.transactions')->where('company_id', $f['company']->id)->value('transaction_number'))->toBe($before['next_journal']);
});

test('a preview sends nothing outside the database', function () {
    $f = cashSaleFixture();
    Mail::fake();
    Notification::fake();
    Queue::fake();
    Event::fake([\App\Modules\Accounting\Events\InvoiceVoided::class]);

    previewAs($this, $f, 'owner', 'sale.create', ['amount' => 900, 'deposit_account_id' => $f['cash']->id])->assertOk();
    previewAs($this, $f, 'owner', 'bank_transaction.create', depositParams($f))->assertOk();

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Queue::assertNothingPushed();
    Event::assertNotDispatched(\App\Modules\Accounting\Events\InvoiceVoided::class);
    expect(PalettePreview::active())->toBeFalse();
});

test('a user without the permission is told so instead of getting a preview', function () {
    $f = cashSaleFixture();
    $before = previewFootprint($f['company']->id);

    // Even with an incomplete entry: the permission is checked first.
    $res = previewAs($this, $f, 'limited', 'bill_payment.create', [])->assertForbidden();

    expect($res->json('ok'))->toBeFalse();
    expect($res->json('message'))->toBe("You can't record vendor payments");
    expect(previewFootprint($f['company']->id))->toBe($before);
});

test('the second permission a sale needs is also reported at preview', function () {
    $f = cashSaleFixture();

    $res = previewAs($this, $f, 'limited', 'sale.create', ['amount' => 100])->assertForbidden();

    expect($res->json('message'))->toBe("You can't record sales");
});

test('a date in a closed period is refused with the period message', function () {
    $f = cashSaleFixture();
    AccountingPeriod::where('company_id', $f['company']->id)->update(['is_closed' => true]);
    $before = previewFootprint($f['company']->id);

    $res = previewAs($this, $f, 'owner', 'bank_transaction.create', depositParams($f));

    expect($res->json('ok'))->toBeFalse();
    expect(strtolower($res->json('message')))->toContain('period');
    expect(previewFootprint($f['company']->id))->toBe($before);
});

test('a sale into a closed period shows the document lock message', function () {
    $f = cashSaleFixture();
    AccountingPeriod::where('company_id', $f['company']->id)->update(['is_closed' => true]);

    $res = previewAs($this, $f, 'owner', 'sale.create', ['amount' => 100, 'deposit_account_id' => $f['cash']->id]);

    expect($res->json('ok'))->toBeFalse();
    expect(strtolower($res->json('message')))->toContain('closed accounting period');
});

test('a customer payment preview credits receivables and rolls back', function () {
    $f = cashSaleFixture();
    $customer = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-1', 'name' => 'Al Noor Traders', 'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true]);
    $before = previewFootprint($f['company']->id);

    $res = previewAs($this, $f, 'owner', 'payment.create', [
        'customer_id' => $customer->id, 'amount' => 1000, 'method' => 'cash',
        'date' => $f['today'], 'deposit_account_id' => $f['cash']->id,
    ])->assertOk();

    expect($res->json('lines'))->toContain(['side' => 'credit', 'account_code' => '1100', 'account_name' => 'Accounts Receivable', 'amount' => 1000]);
    expect($res->json('lines'))->toContain(['side' => 'debit', 'account_code' => '1050', 'account_name' => 'Cash on Hand', 'amount' => 1000]);
    expect(previewFootprint($f['company']->id))->toBe($before);
});

test('a sale preview works and does not create the Cash Sales customer', function () {
    $f = cashSaleFixture();
    $before = previewFootprint($f['company']->id);

    $res = previewAs($this, $f, 'owner', 'sale.create', ['amount' => 1200, 'deposit_account_id' => $f['cash']->id])->assertOk();

    expect($res->json('summary.transactions'))->toBe(2);
    expect($res->json('lines'))->toContain(['side' => 'debit', 'account_code' => '1050', 'account_name' => 'Cash on Hand', 'amount' => 1200]);
    expect($res->json('lines'))->toContain(['side' => 'credit', 'account_code' => '4100', 'account_name' => 'Sales Revenue', 'amount' => 1200]);
    // Receivables go in and out in the same sale: not worth showing as a balance change.
    expect(collect($res->json('balances'))->pluck('account_name')->all())->not->toContain('Accounts Receivable');
    expect(previewFootprint($f['company']->id))->toBe($before);
});

test('an expense preview shows the expense and the account it is paid from', function () {
    $f = cashSaleFixture();

    $res = previewAs($this, $f, 'owner', 'expense.create', [
        'date' => $f['today'], 'account_id' => $f['tea']->id, 'amount' => 250,
        'paid_from_account_id' => $f['cash']->id, 'description' => 'tea',
    ])->assertOk();

    expect($res->json('lines'))->toBe([
        ['side' => 'debit', 'account_code' => '6200', 'account_name' => 'Tea and Refreshments', 'amount' => 250],
        ['side' => 'credit', 'account_code' => '1050', 'account_name' => 'Cash on Hand', 'amount' => 250],
    ]);
});

test('validation problems come back as the action\'s own messages', function () {
    $f = cashSaleFixture();

    $res = previewAs($this, $f, 'owner', 'bank_transaction.create', depositParams($f, ['to_bank_account_id' => null, 'amount' => 0]));

    expect($res->status())->toBe(422);
    expect($res->json('ok'))->toBeFalse();
    expect($res->json('errors'))->toHaveKey('amount');
});

test('only posting commands can be previewed', function () {
    $f = cashSaleFixture();

    $res = previewAs($this, $f, 'owner', 'company.create', ['name' => 'X']);

    expect($res->status())->toBe(422);
    expect($res->json('ok'))->toBeFalse();
    expect(DB::table('auth.companies')->where('name', 'X')->exists())->toBeFalse();
});
