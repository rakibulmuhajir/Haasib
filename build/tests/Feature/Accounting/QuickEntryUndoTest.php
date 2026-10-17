<?php

use App\Models\User;
use App\Modules\Accounting\Events\InvoiceVoided;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DocumentDateLock;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/CashSaleFixtures.php';
require_once __DIR__.'/WithholdingFixtures.php';

function undoDispatch(array $f, string $key, array $params, ?User $as = null): array
{
    $user = $as ?? $f['owner'];
    test()->actingAs($user);

    return app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch($key, $params, $user));
}

function undoExpense(array $f, ?string $date = null): array
{
    return undoDispatch($f, 'expense.create', [
        'date' => $date ?? $f['today'], 'account_id' => $f['tea']->id, 'amount' => 250,
        'paid_from_account_id' => $f['cash']->id, 'description' => 'Tea',
    ]);
}

function undoDeposit(array $f, array $over = []): array
{
    return undoDispatch($f, 'bank_transaction.create', $over + [
        'kind' => 'deposit', 'date' => $f['today'], 'amount' => 5000,
        'bank_account_id' => $f['bank']->id, 'cash_account_id' => $f['cash']->id,
    ]);
}

/** Net balance (debit minus credit) of one account across the whole company ledger. */
function undoNet(array $f, Account $account): float
{
    return round((float) DB::table('acct.journal_entries as je')
        ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
        ->where('t.company_id', $f['company']->id)->where('je.account_id', $account->id)
        ->selectRaw('COALESCE(SUM(je.debit_amount) - SUM(je.credit_amount), 0) as net')->value('net'), 2);
}

/** Splits the fixture's one-year period into "before this month" and "this month on", so the past can be closed alone. */
function undoSplitPeriods(array $f): array
{
    $company = $f['company'];
    $monthStart = Carbon::parse($f['today'])->startOfMonth();
    $year = $monthStart->year;
    $fy = FiscalYear::where('company_id', $company->id)->first();
    AccountingPeriod::where('company_id', $company->id)->delete();
    $past = AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'Before', 'period_number' => 1, 'start_date' => "$year-01-01", 'end_date' => $monthStart->copy()->subDay()->toDateString()]);
    $now = AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'Now', 'period_number' => 2, 'start_date' => $monthStart->toDateString(), 'end_date' => "$year-12-31"]);

    return [$past, $now, $monthStart->copy()->subDay()->toDateString()];
}

// ---------------------------------------------------------------------------
// expense.reverse
// ---------------------------------------------------------------------------

test('reversing an expense posts a visible reversal dated today and nets the ledger to zero', function () {
    $f = cashSaleFixture();
    $made = undoExpense($f, Carbon::parse($f['today'])->subDays(3)->toDateString());

    $done = undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]);

    $original = Transaction::findOrFail($made['data']['id']);
    $reversal = Transaction::findOrFail($done['data']['id']);
    expect($reversal->reversal_of_id)->toBe($original->id)
        ->and($original->fresh()->reversed_by_id)->toBe($reversal->id)
        ->and($reversal->transaction_date->toDateString())->toBe($f['today'])
        ->and($done['data']['reversed_id'])->toBe($original->id)
        ->and($done['message'])->toContain($original->transaction_number)->toContain($reversal->transaction_number);
    // The mirror image of the original, line for line.
    expect($reversal->journalEntries->where('account_id', $f['tea']->id)->sum('credit_amount'))->toEqual(250)
        ->and($reversal->journalEntries->where('account_id', $f['cash']->id)->sum('debit_amount'))->toEqual(250);
    expect(undoNet($f, $f['tea']))->toBe(0.0)->and(undoNet($f, $f['cash']))->toBe(0.0);
});

test('a reason and a date can be given', function () {
    $f = cashSaleFixture();
    $made = undoExpense($f);
    $date = Carbon::parse($f['today'])->subDay()->toDateString();

    $done = undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id'], 'date' => $date, 'reason' => 'Wrong amount']);

    $reversal = Transaction::findOrFail($done['data']['id']);
    expect($reversal->transaction_date->toDateString())->toBe($date)->and($reversal->description)->toContain('Wrong amount');
});

test('an expense cannot be reversed twice', function () {
    $f = cashSaleFixture();
    $made = undoExpense($f);
    undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]);
    $count = Transaction::where('company_id', $f['company']->id)->count();

    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]))
        ->toThrow(ValidationException::class, 'already reversed');
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe($count);
});

test('a reversal cannot itself be reversed', function () {
    $f = cashSaleFixture();
    $made = undoExpense($f);
    $done = undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]);

    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $done['data']['id']]))
        ->toThrow(ValidationException::class, 'already a reversal');
});

test('an expense dated in a closed period is refused and left as it was', function () {
    $f = cashSaleFixture();
    [$past, , $lastDayOfPast] = undoSplitPeriods($f);
    $made = undoExpense($f, $lastDayOfPast);
    $past->update(['is_closed' => true]);

    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]))
        ->toThrow(ValidationException::class, 'closed accounting period');
    expect(Transaction::findOrFail($made['data']['id'])->reversed_by_id)->toBeNull();
    expect(Transaction::where('reversal_of_id', $made['data']['id'])->count())->toBe(0);
});

test('a reversal date in a closed period is refused', function () {
    $f = cashSaleFixture();
    [, $now] = undoSplitPeriods($f);
    $made = undoExpense($f);
    $now->update(['is_closed' => true]);

    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]))
        ->toThrow(ValidationException::class, 'closed accounting period');
    expect(Transaction::where('reversal_of_id', $made['data']['id'])->count())->toBe(0);
});

test('a day locked by a module is refused whichever side it falls on', function () {
    static $registered = false;
    $GLOBALS['undo_locked_day'] = null;
    if (! $registered) {
        $registered = true;
        DocumentDateLock::extend(fn (string $company, string $date) => ($GLOBALS['undo_locked_day'] ?? null) === $date ? 'That day is closed.' : null);
    }

    $f = cashSaleFixture();
    $yesterday = Carbon::parse($f['today'])->subDay()->toDateString();
    $made = undoExpense($f, $yesterday);

    $GLOBALS['undo_locked_day'] = $yesterday; // the original's day
    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]))->toThrow(ValidationException::class, 'That day is closed.');

    $GLOBALS['undo_locked_day'] = $f['today']; // the day the reversal would land on
    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]))->toThrow(ValidationException::class, 'That day is closed.');
    expect(Transaction::where('reversal_of_id', $made['data']['id'])->count())->toBe(0);

    $GLOBALS['undo_locked_day'] = null;
    undoDispatch($f, 'expense.reverse', ['id' => $made['data']['id']]);
    expect(Transaction::where('reversal_of_id', $made['data']['id'])->count())->toBe(1);
});

test('only an expense entered here can be reversed as one', function () {
    $f = cashSaleFixture();
    $other = cashSaleFixture();
    $deposit = undoDeposit($f);
    $theirs = undoExpense($other);
    enterCompany($f['company']);

    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $deposit['data']['id']]))->toThrow(ValidationException::class, 'not a expense');
    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $theirs['data']['id']]))->toThrow(ValidationException::class);
    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => (string) str()->uuid()]))->toThrow(ValidationException::class);
    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => 'tea']))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// bank_transaction.reverse
// ---------------------------------------------------------------------------

test('a deposit, withdrawal, transfer and bank charge are each taken back to zero', function () {
    $f = cashSaleFixture();
    $made = [
        undoDeposit($f),
        undoDeposit($f, ['kind' => 'withdrawal', 'amount' => 700]),
        undoDispatch($f, 'bank_transaction.create', ['kind' => 'transfer', 'date' => $f['today'], 'amount' => 1500, 'from_bank_account_id' => $f['bank']->id, 'to_bank_account_id' => $f['bank2']->id]),
        undoDispatch($f, 'bank_transaction.create', ['kind' => 'charge', 'date' => $f['today'], 'amount' => 90, 'bank_account_id' => $f['bank']->id, 'expense_account_id' => $f['charges']->id]),
    ];
    expect(undoNet($f, $f['bank']))->not->toBe(0.0);

    foreach ($made as $m) {
        $done = undoDispatch($f, 'bank_transaction.reverse', ['id' => $m['data']['id']]);
        expect(Transaction::findOrFail($done['data']['id'])->reversal_of_id)->toBe($m['data']['id']);
    }

    foreach (['cash', 'bank', 'bank2', 'charges'] as $name) {
        expect(undoNet($f, $f[$name]))->toBe(0.0);
    }
});

test('a bank transaction cannot be reversed twice, and a locked day is refused', function () {
    $f = cashSaleFixture();
    [$past, , $lastDayOfPast] = undoSplitPeriods($f);
    $old = undoDeposit($f, ['date' => $lastDayOfPast]);
    $recent = undoDeposit($f);

    undoDispatch($f, 'bank_transaction.reverse', ['id' => $recent['data']['id']]);
    expect(fn () => undoDispatch($f, 'bank_transaction.reverse', ['id' => $recent['data']['id']]))->toThrow(ValidationException::class, 'already reversed');

    $past->update(['is_closed' => true]);
    expect(fn () => undoDispatch($f, 'bank_transaction.reverse', ['id' => $old['data']['id']]))->toThrow(ValidationException::class, 'closed accounting period');
    expect(Transaction::where('reversal_of_id', $old['data']['id'])->count())->toBe(0);
});

test('a bank movement that came from a bank feed or another kind of entry is not reversed here', function () {
    $f = cashSaleFixture();
    $expense = undoExpense($f);
    $deposit = undoDeposit($f);
    // A feed match references its statement line; this one now looks like one.
    $matched = undoDeposit($f);
    Transaction::where('id', $matched['data']['id'])->update(['reference_id' => (string) str()->uuid()]);

    expect(fn () => undoDispatch($f, 'bank_transaction.reverse', ['id' => $expense['data']['id']]))->toThrow(ValidationException::class);
    expect(fn () => undoDispatch($f, 'bank_transaction.reverse', ['id' => $matched['data']['id']]))->toThrow(ValidationException::class);
    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $deposit['data']['id']]))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------------
// sale.void
// ---------------------------------------------------------------------------

test('undoing a sale voids its payment and then the invoice, and nets the ledger to zero', function () {
    $f = cashSaleFixture();
    $sale = undoDispatch($f, 'sale.create', ['amount' => 1200, 'deposit_account_id' => $f['cash']->id]);
    $invoiceId = $sale['data']['invoice_id'];

    $done = undoDispatch($f, 'sale.void', ['id' => $invoiceId]);

    expect($done['data']['payment_ids'])->toBe([$sale['data']['payment_id']]);
    expect(Invoice::findOrFail($invoiceId)->status)->toBe('void');
    expect(Payment::withTrashed()->findOrFail($sale['data']['payment_id'])->trashed())->toBeTrue();
    expect(undoNet($f, $f['cash']))->toBe(0.0)->and(undoNet($f, $f['ar']))->toBe(0.0)->and(undoNet($f, $f['revenue']))->toBe(0.0);
    expect($done['redirect'])->toBe("/{$f['company']->slug}/invoices/{$invoiceId}");
});

test('a sale cannot be undone twice', function () {
    $f = cashSaleFixture();
    $sale = undoDispatch($f, 'sale.create', ['amount' => 1200, 'deposit_account_id' => $f['cash']->id]);
    undoDispatch($f, 'sale.void', ['id' => $sale['data']['invoice_id']]);
    $count = Transaction::where('company_id', $f['company']->id)->count();

    expect(fn () => undoDispatch($f, 'sale.void', ['id' => $sale['data']['invoice_id']]))->toThrow(ValidationException::class, 'already undone');
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe($count);
});

test('if the invoice cannot be voided the payment is not either', function () {
    $f = cashSaleFixture();
    $sale = undoDispatch($f, 'sale.create', ['amount' => 1200, 'deposit_account_id' => $f['cash']->id]);
    $count = Transaction::where('company_id', $f['company']->id)->count();

    Event::listen(InvoiceVoided::class, fn () => throw new \RuntimeException('boom'));

    expect(fn () => undoDispatch($f, 'sale.void', ['id' => $sale['data']['invoice_id']]))->toThrow(\RuntimeException::class, 'boom');

    expect(Payment::find($sale['data']['payment_id']))->not->toBeNull();
    expect(Invoice::findOrFail($sale['data']['invoice_id'])->status)->not->toBe('void');
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe($count);
    expect(undoNet($f, $f['cash']))->toBe(1200.0);
});

test('only a cash sale can be undone this way', function () {
    $f = cashSaleFixture();
    $buyer = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-9', 'name' => 'Al Noor', 'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true]);
    $invoice = undoDispatch($f, 'invoice.create', [
        'customer' => $buyer->id, 'currency' => 'PKR', 'date' => $f['today'], 'due' => $f['today'], 'draft' => false, 'send_immediately' => true,
        'line_items' => [['description' => 'Goods', 'quantity' => 1, 'unit_price' => 900, 'tax_rate' => 0, 'discount_rate' => 0]],
    ]);

    expect(fn () => undoDispatch($f, 'sale.void', ['id' => $invoice['data']['id']]))->toThrow(ValidationException::class, 'not a cash sale');
    expect(Invoice::findOrFail($invoice['data']['id'])->status)->not->toBe('void');
});

test('a sale whose payment also settled other invoices is not undone', function () {
    $f = cashSaleFixture();
    $sale = undoDispatch($f, 'sale.create', ['amount' => 1200, 'deposit_account_id' => $f['cash']->id]);
    $second = undoDispatch($f, 'sale.create', ['amount' => 300, 'deposit_account_id' => $f['cash']->id]);
    // Make the first payment also cover the second invoice.
    DB::table('acct.payment_allocations')->where('payment_id', $sale['data']['payment_id'])
        ->update(['invoice_id' => $second['data']['invoice_id']]);
    DB::table('acct.payment_allocations')->insert(array_merge(
        (array) DB::table('acct.payment_allocations')->where('payment_id', $sale['data']['payment_id'])->first(),
        ['id' => (string) str()->uuid(), 'invoice_id' => $sale['data']['invoice_id']],
    ));

    expect(fn () => undoDispatch($f, 'sale.void', ['id' => $sale['data']['invoice_id']]))->toThrow(ValidationException::class, 'other invoices');
});

// ---------------------------------------------------------------------------
// Permissions, over the palette's own endpoint
// ---------------------------------------------------------------------------

test('each undo needs its own permission', function () {
    $f = cashSaleFixture();
    $stranger = User::factory()->withoutTwoFactor()->create();
    $expense = undoExpense($f);
    $deposit = undoDeposit($f);
    $sale = undoDispatch($f, 'sale.create', ['amount' => 100, 'deposit_account_id' => $f['cash']->id]);

    // Undoing an expense needs expense.create (who may record it may undo it); "operations" may not void invoices; a stranger may do nothing here.
    expect(fn () => undoDispatch($f, 'expense.reverse', ['id' => $expense['data']['id']], $stranger))->toThrow(AuthorizationException::class, 'expense.create');
    expect(fn () => undoDispatch($f, 'sale.void', ['id' => $sale['data']['invoice_id']], $f['limited']))->toThrow(AuthorizationException::class);
    expect(fn () => undoDispatch($f, 'bank_transaction.reverse', ['id' => $deposit['data']['id']], $stranger))->toThrow(AuthorizationException::class, 'bank_account.update');

    expect(Transaction::where('company_id', $f['company']->id)->whereNotNull('reversal_of_id')->count())->toBe(0);
    expect(Invoice::findOrFail($sale['data']['invoice_id'])->status)->not->toBe('void');
});

test('the palette endpoint runs the undo and answers with the reversal to open', function () {
    $f = cashSaleFixture();
    $made = undoExpense($f);
    $execute = fn ($user, array $body) => $this->actingAs($user)->withHeader('X-Company-Slug', $f['company']->slug)->postJson('/api/palette/execute', $body);

    $execute($f['limited'], ['entity' => 'expense', 'verb' => 'reverse', 'params' => ['id' => $made['data']['id']]])->assertForbidden();

    $res = $execute($f['owner'], ['entity' => 'expense', 'verb' => 'reverse', 'params' => ['id' => $made['data']['id']]])->assertSuccessful();
    expect($res->json('ok'))->toBeTrue();
    $reversal = Transaction::findOrFail($res->json('data.id'));
    expect($reversal->reversal_of_id)->toBe($made['data']['id']);

    $again = $execute($f['owner'], ['entity' => 'expense', 'verb' => 'reverse', 'params' => ['id' => $made['data']['id']]]);
    $again->assertStatus(422);
    expect(json_encode($again->json('errors')))->toContain('already reversed');
});

test('undoing a received payment that had tax withheld reverses the tax line too', function () {
    $f = cashSaleFixture();
    whtTax($f['company'], true);
    $buyer = Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'C-7', 'name' => 'Big Buyer', 'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true]);
    $invoice = undoDispatch($f, 'invoice.create', [
        'customer' => $buyer->id, 'currency' => 'PKR', 'date' => $f['today'], 'due' => $f['today'], 'draft' => false, 'send_immediately' => true,
        'line_items' => [['description' => 'Services', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate' => 0, 'discount_rate' => 0]],
    ]);
    $paid = undoDispatch($f, 'payment.create', [
        'invoice' => $invoice['data']['id'], 'amount' => 10000, 'method' => 'bank_transfer', 'currency' => 'PKR', 'date' => $f['today'],
        'deposit_account_id' => $f['bank']->id, 'wht_amount' => 400,
    ]);
    $advance = Account::where('company_id', $f['company']->id)->where('name', 'like', 'Advance Income Tax%')->firstOrFail();
    expect(undoNet($f, $advance))->toBe(400.0)->and(undoNet($f, $f['bank']))->toBe(9600.0);

    // What the Undo button sends.
    $this->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->postJson('/api/palette/execute', ['entity' => 'payment', 'verb' => 'void', 'params' => ['id' => $paid['data']['id']]])
        ->assertSuccessful();

    expect(undoNet($f, $advance))->toBe(0.0)->and(undoNet($f, $f['bank']))->toBe(0.0)->and(undoNet($f, $f['ar']))->toBe(10000.0);
});
