<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Vendor;
use App\Support\Palette\Commands\Money\Accounts;
use Carbon\Carbon;

/*
 * The eight money commands against /api/palette/resolve, ported case by case from
 * tests/js/palette/quick-entry.spec.ts (resolveQuickEntry). The company's today is
 * pinned to 2026-10-16, as the spec's defaults were.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-16 12:00:00', 'UTC'));
});

/** A company with the spec's accounts: Meezan, two HBL banks, one cash drawer and four expense accounts. */
function moneySeed(): array
{
    $f = paletteFixture(false);
    $make = fn (string $code, string $name, string $type, string $subtype) => Account::create([
        'company_id' => $f['company']->id, 'code' => $code, 'name' => $name, 'type' => $type, 'subtype' => $subtype,
        'normal_balance' => $type === 'asset' || $type === 'expense' ? 'debit' : 'credit', 'currency' => 'PKR', 'is_active' => true,
    ]);

    $f['cash'] = $make('1050', 'Cash on Hand', 'asset', 'cash');
    $f['meezan'] = $make('1060', 'Meezan Bank', 'asset', 'bank');
    $f['hbl'] = $make('1061', 'HBL Current', 'asset', 'bank');
    $f['hbl2'] = $make('1062', 'HBL Savings', 'asset', 'bank');
    $f['tea'] = $make('6200', 'Tea and Refreshments', 'expense', 'operating_expense');
    $f['office'] = $make('6300', 'Office Supplies', 'expense', 'operating_expense');
    $f['office2'] = $make('6310', 'Office Rent', 'expense', 'operating_expense');
    $f['charges'] = $make('6520', 'Bank Charges & Fees', 'expense', 'operating_expense');

    return $f;
}

function moneyCustomer(array $f, string $name): Customer
{
    return Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-'.strtoupper(str()->random(6)),
        'name' => $name, 'base_currency' => 'PKR', 'is_active' => true,
    ]);
}

function moneyVendor(array $f, string $name): Vendor
{
    return Vendor::create(['company_id' => $f['company']->id, 'vendor_number' => 'V-'.strtoupper(str()->random(6)), 'name' => $name, 'base_currency' => 'PKR']);
}

function moneyBill(array $f, Vendor $vendor, string $no, string $date, float $balance): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $vendor->id, 'bill_number' => $no, 'bill_date' => $date,
        'due_date' => $date, 'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $balance, 'total_amount' => $balance, 'paid_amount' => 0, 'balance' => $balance,
    ]);
}

/** @param array<string, string|array> $slots a string is the typed text; an array is sent as is (e.g. a picked value) */
function moneyResolve($test, array $f, string $command, array $slots = []): array
{
    $body = [];
    foreach ($slots as $name => $slot) {
        $body[$name] = is_array($slot) ? $slot : ['text' => $slot];
    }

    return $test->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->postJson('/api/palette/resolve', ['command' => $command, 'slots' => $body])->assertOk()->json();
}

// ---------------------------------------------------------------------------
// resolving
// ---------------------------------------------------------------------------

test('deposit fills the date, the default cash account and the named bank', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '5000', 'bank' => 'meezan']);

    expect($r['status'])->toBe('ready')
        ->and($r['key'])->toBe('bank_transaction.create')
        ->and($r['params'])->toEqual([
            'kind' => 'deposit', 'date' => '2026-10-16', 'amount' => 5000,
            'bank_account_id' => $f['meezan']->id, 'cash_account_id' => $f['cash']->id,
        ])
        ->and($r['summary'])->toBe('Deposit PKR 5,000: Cash on Hand to Meezan Bank')
        ->and($r['notes'])->toBe([])
        ->and($r['card'])->toEqual([
            'from' => 'Cash on Hand', 'to' => 'Meezan Bank', 'amount' => 5000, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);
});

test('deposit without a bank uses the default bank; withdraw flips the kind', function () {
    $f = moneySeed();

    $d = moneyResolve($this, $f, 'deposit', ['amount' => '5000']);
    expect($d['status'])->toBe('ready')->and($d['params']['bank_account_id'])->toBe($f['meezan']->id);

    $w = moneyResolve($this, $f, 'withdraw', ['amount' => '1k']);
    expect($w['status'])->toBe('ready')
        ->and($w['params']['kind'])->toBe('withdrawal')
        ->and($w['params']['amount'])->toEqual(1000)
        ->and($w['summary'])->toBe('Withdraw PKR 1,000: Meezan Bank to Cash on Hand')
        ->and($w['card'])->toEqual([
            'from' => 'Meezan Bank', 'to' => 'Cash on Hand', 'amount' => 1000, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);
});

test('a bank name that matches two banks shows both and does not choose', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '5000', 'bank' => 'hbl']);

    expect($r['status'])->toBe('pending')
        ->and($r['slots']['bank']['state'])->toBe('choices')
        ->and($r['slots']['bank']['prompt'])->toBe('Banks matching "hbl"')
        ->and(array_column($r['slots']['bank']['choices'], 'value'))->toBe([$f['hbl']->id, $f['hbl2']->id])
        ->and($r['slots']['bank']['choices'][0])->toBe(['value' => $f['hbl']->id, 'label' => 'HBL Current', 'meta' => '1061']);

    $picked = moneyResolve($this, $f, 'deposit', ['amount' => '5000', 'bank' => ['text' => 'hbl', 'value' => $f['hbl2']->id]]);
    expect($picked['status'])->toBe('ready')->and($picked['params']['bank_account_id'])->toBe($f['hbl2']->id);
});

test('a bank that matches nothing is not found, not the default bank', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '5000', 'bank' => 'nowhere']);

    expect($r['status'])->toBe('pending')
        ->and($r['slots']['bank'])->toBe(['state' => 'not_found', 'message' => 'No bank matches "nowhere"']);
});

test('transfer needs two different banks', function () {
    $f = moneySeed();

    $ok = moneyResolve($this, $f, 'transfer', ['amount' => '100000', 'from' => 'meezan', 'to' => 'hbl savings']);
    expect($ok['status'])->toBe('ready')
        ->and($ok['key'])->toBe('bank_transaction.create')
        ->and($ok['params'])->toEqual([
            'kind' => 'transfer', 'date' => '2026-10-16', 'amount' => 100000,
            'from_bank_account_id' => $f['meezan']->id, 'to_bank_account_id' => $f['hbl2']->id,
        ])
        ->and($ok['summary'])->toBe('Transfer PKR 100,000: Meezan Bank to HBL Savings')
        ->and($ok['card'])->toEqual([
            'from' => 'Meezan Bank', 'to' => 'HBL Savings', 'amount' => 100000, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);

    $same = moneyResolve($this, $f, 'transfer', ['amount' => '100', 'from' => 'meezan', 'to' => 'meezan']);
    expect($same)->toBe(['status' => 'error', 'message' => 'Choose two different banks']);

    $amb = moneyResolve($this, $f, 'transfer', ['amount' => '100', 'from' => 'meezan', 'to' => 'hbl']);
    expect($amb['status'])->toBe('pending')->and($amb['slots']['to']['state'])->toBe('choices');
});

test('transfer with a bank left out is missing that blank', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'transfer', ['amount' => '100', 'from' => 'meezan']);

    expect($r['status'])->toBe('pending')->and($r['slots']['to'])->toBe(['state' => 'missing']);
});

test('bank charge splits the bank from the note and books to the bank charges account', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'bank charge', ['amount' => '500', 'bank' => 'meezan bank october fee']);
    expect($r['status'])->toBe('ready')
        ->and($r['params'])->toEqual([
            'kind' => 'charge', 'date' => '2026-10-16', 'amount' => 500, 'bank_account_id' => $f['meezan']->id,
            'expense_account_id' => $f['charges']->id, 'notes' => 'october fee',
        ])
        ->and($r['summary'])->toBe('Bank charge PKR 500 on Meezan Bank (Bank Charges & Fees)')
        ->and($r['card'])->toEqual([
            'what' => 'Bank charge', 'from' => 'Meezan Bank', 'note' => 'october fee',
            'amount' => 500, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);

    $bare = moneyResolve($this, $f, 'bank charge', ['amount' => '500']);
    expect($bare['status'])->toBe('ready')
        ->and($bare['params'])->toEqual([
            'kind' => 'charge', 'date' => '2026-10-16', 'amount' => 500, 'bank_account_id' => $f['meezan']->id,
            'expense_account_id' => $f['charges']->id,
        ])
        ->and($bare['card']['note'])->toBeNull();

    $typo = moneyResolve($this, $f, 'bank charge', ['amount' => '500', 'bank' => 'meezam']);
    expect($typo['status'])->toBe('pending')
        ->and($typo['slots']['bank'])->toBe(['state' => 'not_found', 'message' => 'No bank matches "meezam"']);
});

test('bank charge takes the bank and the note already split, or a note alone', function () {
    $f = moneySeed();

    $split = moneyResolve($this, $f, 'bank charge', ['amount' => '500', 'bank' => 'meezan', 'note' => 'sms']);
    expect($split['status'])->toBe('ready')
        ->and($split['params']['bank_account_id'])->toBe($f['meezan']->id)
        ->and($split['params']['notes'])->toBe('sms');

    // `bank charge 500 meezan sms` typed as one blank reads the same.
    $joined = moneyResolve($this, $f, 'bank charge', ['amount' => '500', 'bank' => 'meezan sms']);
    expect($joined['params'])->toEqual($split['params']);

    // `bank charge 500 for monthly fee`: no bank named, the default bank.
    $noteOnly = moneyResolve($this, $f, 'bank charge', ['amount' => '500', 'note' => 'monthly fee']);
    expect($noteOnly['status'])->toBe('ready')
        ->and($noteOnly['params']['bank_account_id'])->toBe($f['meezan']->id)
        ->and($noteOnly['params']['notes'])->toBe('monthly fee');

    // A named bank with a note that is not a bank is looked up as a bank, as given.
    $bad = moneyResolve($this, $f, 'bank charge', ['amount' => '500', 'bank' => 'sms', 'note' => 'fee']);
    expect($bad['slots']['bank']['state'])->toBe('not_found');
});

test('bank charge keeps the note when the bank is picked from choices', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'bank charge', ['amount' => '500', 'bank' => ['text' => 'hbl october fee', 'value' => $f['hbl2']->id]]);

    expect($r['status'])->toBe('ready')
        ->and($r['params']['bank_account_id'])->toBe($f['hbl2']->id)
        ->and($r['params']['notes'])->toBe('october fee');
});

test('bank charge without a bank charges account says so', function () {
    $f = moneySeed();
    $f['charges']->update(['name' => 'Miscellaneous Costs']);

    $r = moneyResolve($this, $f, 'bank charge', ['amount' => '500']);

    expect($r)->toBe(['status' => 'error', 'message' => 'Add a Bank Charges account first']);
});

test('receive resolves the customer and picks the method from the account', function () {
    $f = moneySeed();
    $noor = moneyCustomer($f, 'Al Noor Traders');

    $cash = moneyResolve($this, $f, 'receive', ['amount' => '15000', 'customer' => 'al noor']);
    expect($cash['status'])->toBe('ready')
        ->and($cash['key'])->toBe('payment.create')
        ->and($cash['params'])->toEqual([
            'customer_id' => $noor->id, 'amount' => 15000, 'method' => 'cash',
            'date' => '2026-10-16', 'deposit_account_id' => $f['cash']->id,
        ])
        ->and($cash['summary'])->toBe('Received PKR 15,000 from Al Noor Traders into Cash on Hand')
        ->and($cash['notes'])->toBe([])
        ->and($cash['card'])->toEqual([
            'who' => 'Al Noor Traders', 'to' => 'Cash on Hand', 'amount' => 15000, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);

    $bank = moneyResolve($this, $f, 'receive', ['amount' => '15000', 'customer' => 'al noor', 'account' => 'meezan']);
    expect($bank['status'])->toBe('ready')
        ->and($bank['params']['method'])->toBe('bank_transfer')
        ->and($bank['params']['deposit_account_id'])->toBe($f['meezan']->id);
});

test('receive from an ambiguous customer shows both to pick from', function () {
    $f = moneySeed();
    $noor = moneyCustomer($f, 'Al Noor Traders');
    $hamd = moneyCustomer($f, 'Al Hamd Stores');

    $r = moneyResolve($this, $f, 'receive', ['amount' => '15000', 'customer' => 'al']);

    expect($r['status'])->toBe('pending')
        ->and($r['slots']['customer']['state'])->toBe('choices')
        ->and($r['slots']['customer']['prompt'])->toBe('Customers matching "al"')
        ->and(collect($r['slots']['customer']['choices'])->pluck('value')->sort()->values()->all())
        ->toBe(collect([$noor->id, $hamd->id])->sort()->values()->all());

    $picked = moneyResolve($this, $f, 'receive', ['amount' => '15000', 'customer' => ['text' => 'al', 'value' => $hamd->id]]);
    expect($picked['status'])->toBe('ready')
        ->and($picked['params']['customer_id'])->toBe($hamd->id)
        ->and($picked['summary'])->toBe('Received PKR 15,000 from Al Hamd Stores into Cash on Hand');
});

test('an exact customer name wins over longer ones that contain it', function () {
    $f = moneySeed();
    $exact = moneyCustomer($f, 'Al Noor');
    moneyCustomer($f, 'Al Noor Traders');

    $r = moneyResolve($this, $f, 'receive', ['amount' => '100', 'customer' => 'al noor']);

    expect($r['status'])->toBe('ready')->and($r['params']['customer_id'])->toBe($exact->id);
});

test('an unknown customer is not found', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'receive', ['amount' => '100', 'customer' => 'nobody']);

    expect($r['status'])->toBe('pending')
        ->and($r['slots']['customer'])->toBe(['state' => 'not_found', 'message' => 'No customer matches "nobody"']);
});

test('receive with no customer typed is missing that blank', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'receive', ['amount' => '100']);

    expect($r['status'])->toBe('pending')->and($r['slots']['customer'])->toBe(['state' => 'missing']);
});

test('pay settles open bills oldest first and keeps the rest as an advance', function () {
    $f = moneySeed();
    $vendor = moneyVendor($f, 'Karachi Office Supplies');
    $b1 = moneyBill($f, $vendor, 'BILL-1', '2026-09-01', 5000);
    $b2 = moneyBill($f, $vendor, 'BILL-2', '2026-09-02', 2000);
    $b3 = moneyBill($f, $vendor, 'BILL-3', '2026-09-03', 9000);

    $r = moneyResolve($this, $f, 'pay', ['vendor' => 'karachi office', 'amount' => '8000']);

    expect($r['status'])->toBe('ready')
        ->and($r['key'])->toBe('bill_payment.create')
        ->and($r['params'])->toEqual([
            'vendor_id' => $vendor->id, 'payment_date' => '2026-10-16', 'amount' => 8000, 'currency' => 'PKR', 'base_currency' => 'PKR',
            'payment_method' => 'cash', 'payment_account_id' => $f['cash']->id,
            'allocations' => [
                ['bill_id' => $b1->id, 'amount_allocated' => 5000],
                ['bill_id' => $b2->id, 'amount_allocated' => 2000],
                ['bill_id' => $b3->id, 'amount_allocated' => 1000],
            ],
        ])
        ->and($r['summary'])->toBe('Paid PKR 8,000 to Karachi Office Supplies from Cash on Hand')
        ->and($r['notes'])->toBe(['Settles BILL-1, BILL-2, BILL-3'])
        ->and($r['card'])->toEqual([
            'who' => 'Karachi Office Supplies', 'from' => 'Cash on Hand', 'amount' => 8000, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);

    // Money beyond the open bills is named as an advance.
    $over = moneyResolve($this, $f, 'pay', ['vendor' => 'karachi office', 'amount' => '17000', 'account' => 'meezan']);
    expect($over['notes'])->toBe(['Settles BILL-1, BILL-2, BILL-3', 'PKR 1,000 stays as an advance'])
        ->and($over['params']['payment_method'])->toBe('bank_transfer');
});

test('pay with no open bills holds the whole amount as an advance', function () {
    $f = moneySeed();
    moneyVendor($f, 'Karachi Office Supplies');

    $none = moneyResolve($this, $f, 'pay', ['vendor' => 'karachi office', 'amount' => '800', 'account' => 'meezan']);

    expect($none['status'])->toBe('ready')
        ->and($none['params']['allocations'])->toBe([])
        ->and($none['params']['payment_method'])->toBe('bank_transfer')
        ->and($none['params']['payment_account_id'])->toBe($f['meezan']->id)
        ->and($none['notes'])->toBe(['No open bills: held as an advance']);
});

test('pay with no vendor typed is missing that blank', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'pay', ['amount' => '1000']);

    expect($r['status'])->toBe('pending')->and($r['slots']['vendor'])->toBe(['state' => 'missing']);
});

test('an unknown vendor is not found', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'pay', ['vendor' => 'nobody', 'amount' => '100']);

    expect($r['slots']['vendor'])->toBe(['state' => 'not_found', 'message' => 'No vendor matches "nobody"']);
});

test('a missing or unreadable amount is flagged', function () {
    $f = moneySeed();

    expect(moneyResolve($this, $f, 'deposit')['slots']['amount'])->toBe(['state' => 'missing']);
    expect(moneyResolve($this, $f, 'deposit', ['amount' => '12.345'])['slots']['amount'])
        ->toBe(['state' => 'not_found', 'message' => 'Amount needed']);
});

test('spend resolves the account by what was typed, and asks when several fit', function () {
    $f = moneySeed();

    $one = moneyResolve($this, $f, 'spend', ['amount' => '250', 'what' => 'tea']);
    expect($one['status'])->toBe('ready')
        ->and($one['key'])->toBe('expense.create')
        ->and($one['params'])->toEqual([
            'date' => '2026-10-16', 'account_id' => $f['tea']->id, 'amount' => 250,
            'paid_from_account_id' => $f['cash']->id, 'description' => 'tea',
        ])
        ->and($one['summary'])->toBe('Expense PKR 250: Tea and Refreshments, paid from Cash on Hand')
        ->and($one['card'])->toEqual([
            'what' => 'Tea and Refreshments', 'from' => 'Cash on Hand', 'amount' => 250, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);

    $many = moneyResolve($this, $f, 'spend', ['amount' => '250', 'what' => 'office']);
    expect($many['slots']['what']['state'])->toBe('choices')
        ->and($many['slots']['what']['prompt'])->toBe('Expense accounts matching "office"')
        ->and(array_column($many['slots']['what']['choices'], 'value'))->toBe([$f['office']->id, $f['office2']->id]);

    // A description that names no account is not guessed at: every expense account is offered.
    $none = moneyResolve($this, $f, 'spend', ['amount' => '250', 'what' => 'zebra']);
    expect($none['slots']['what']['state'])->toBe('choices')
        ->and($none['slots']['what']['prompt'])->toBe('No expense account named "zebra". Choose one')
        ->and($none['slots']['what']['choices'])->toHaveCount(4);

    $picked = moneyResolve($this, $f, 'spend', ['amount' => '250', 'what' => ['text' => 'zebra', 'value' => $f['office']->id]]);
    expect($picked['status'])->toBe('ready')
        ->and($picked['params']['account_id'])->toBe($f['office']->id)
        ->and($picked['params']['description'])->toBe('zebra');
});

test('spend with nothing to spend on is missing that blank', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'spend', ['amount' => '250']);

    expect($r['status'])->toBe('pending')->and($r['slots']['what'])->toBe(['state' => 'missing']);
});

test('sale goes into the cash drawer with the note as description', function () {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'sale', ['amount' => '1200', 'note' => 'walk-in']);

    expect($r['status'])->toBe('ready')
        ->and($r['key'])->toBe('sale.create')
        ->and($r['params'])->toEqual([
            'date' => '2026-10-16', 'amount' => 1200, 'description' => 'walk-in', 'deposit_account_id' => $f['cash']->id,
        ])
        ->and($r['summary'])->toBe('Sale PKR 1,200: walk-in, into Cash on Hand')
        ->and($r['card'])->toEqual([
            'what' => 'Sale', 'to' => 'Cash on Hand', 'note' => 'walk-in', 'amount' => 1200, 'currency' => 'PKR', 'date' => '2026-10-16',
        ]);

    $bare = moneyResolve($this, $f, 'sale', ['amount' => '12000']);
    expect($bare['params'])->toEqual(['date' => '2026-10-16', 'amount' => 12000, 'deposit_account_id' => $f['cash']->id])
        ->and($bare['summary'])->toBe('Sale PKR 12,000, into Cash on Hand');
});

test('says so when the company has no cash account to use', function () {
    $f = moneySeed();
    $f['cash']->update(['is_active' => false]);

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '100', 'bank' => 'meezan']);

    expect($r)->toBe(['status' => 'error', 'message' => 'Add a cash account first']);
});

test('says so when the company has no bank, and no account at all', function () {
    $f = moneySeed();
    foreach (['meezan', 'hbl', 'hbl2'] as $bank) {
        $f[$bank]->update(['is_active' => false]);
    }

    expect(moneyResolve($this, $f, 'deposit', ['amount' => '100']))->toBe(['status' => 'error', 'message' => 'Add a bank account first']);

    $f['cash']->update(['is_active' => false]);
    expect(moneyResolve($this, $f, 'pay', ['vendor' => moneyVendor($f, 'Ali')->name, 'amount' => '100']))
        ->toBe(['status' => 'error', 'message' => 'Add a cash or bank account first']);
});

// ---------------------------------------------------------------------------
// dates
// ---------------------------------------------------------------------------

test('a date is booked on the day it names', function (string $typed, string $expected) {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '100', 'date' => $typed]);

    expect($r['status'])->toBe('ready')
        ->and($r['params']['date'])->toBe($expected)
        ->and($r['card']['date'])->toBe($expected);
})->with([
    ['yesterday', '2026-10-15'],
    ['today', '2026-10-16'],
    ['tomorrow', '2026-10-17'],
    ['14 oct', '2026-10-14'],
    ['on 14 oct', '2026-10-14'],
    ['14 Oct 2026', '2026-10-14'],
    ['14 october 2025', '2025-10-14'],
    ['14/10', '2026-10-14'],
    ['14/10/2026', '2026-10-14'],
    ['14-10-2026', '2026-10-14'],
    ['14-10-26', '2026-10-14'],
    ['2026-10-14', '2026-10-14'],
    ['5 sept', '2026-09-05'],
    ['29 feb 2028', '2028-02-29'],
    ['1/2', '2026-02-01'],
]);

test('relative words count from the company date, across month and year ends', function (string $today, string $word, string $expected) {
    $f = moneySeed();
    $this->travelTo(Carbon::parse("{$today} 12:00:00", 'UTC'));

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '100', 'date' => $word]);

    expect($r['params']['date'])->toBe($expected);
})->with([
    ['2026-11-01', 'yesterday', '2026-10-31'],
    ['2027-01-01', 'yesterday', '2026-12-31'],
    ['2026-12-31', 'tomorrow', '2027-01-01'],
    ['2028-02-28', 'tomorrow', '2028-02-29'],
]);

test('a year-less date takes the company year, unless that is months ahead', function (string $today, string $typed, string $expected) {
    $f = moneySeed();
    $this->travelTo(Carbon::parse("{$today} 12:00:00", 'UTC'));

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '100', 'date' => $typed]);

    expect($r['params']['date'])->toBe($expected);
})->with([
    ['2027-01-03', '28 dec', '2026-12-28'],
    ['2026-10-16', '28 dec', '2026-12-28'],
    ['2026-12-30', '5 jan', '2026-01-05'],
]);

test('a day that does not exist is not found', function (string $typed, ?string $message) {
    $f = moneySeed();

    $r = moneyResolve($this, $f, 'deposit', ['amount' => '100', 'date' => $typed]);

    expect($r['status'])->toBe('pending')->and($r['slots']['date']['state'])->toBe('not_found');
    if ($message !== null) {
        expect($r['slots']['date']['message'])->toBe($message);
    }
})->with([
    ['31/02', '31/02 is not a date'],
    ['29 feb 2027', null],
    ['14/13', null],
]);

test('the date reaches every command', function () {
    $f = moneySeed();
    moneyCustomer($f, 'Al Noor');
    moneyVendor($f, 'Karachi Office');
    $date = ['date' => 'yesterday'];

    expect(moneyResolve($this, $f, 'withdraw', ['amount' => '100'] + $date)['params']['date'])->toBe('2026-10-15');
    expect(moneyResolve($this, $f, 'transfer', ['amount' => '100', 'from' => 'meezan', 'to' => 'hbl current'] + $date)['params']['date'])->toBe('2026-10-15');
    expect(moneyResolve($this, $f, 'bank charge', ['amount' => '50', 'bank' => 'meezan'] + $date)['params']['date'])->toBe('2026-10-15');
    expect(moneyResolve($this, $f, 'receive', ['amount' => '100', 'customer' => 'al noor'] + $date)['params']['date'])->toBe('2026-10-15');
    expect(moneyResolve($this, $f, 'spend', ['amount' => '100', 'what' => 'tea'] + $date)['params']['date'])->toBe('2026-10-15');
    expect(moneyResolve($this, $f, 'sale', ['amount' => '100'] + $date)['params']['date'])->toBe('2026-10-15');

    $pay = moneyResolve($this, $f, 'pay', ['vendor' => 'karachi office', 'amount' => '100', 'date' => 'on 14 oct']);
    expect($pay['params']['payment_date'])->toBe('2026-10-14')->and($pay['card']['date'])->toBe('2026-10-14');
});

// ---------------------------------------------------------------------------
// quick entry defaults from settings
// ---------------------------------------------------------------------------

/** Settings, Quick entry: where money received goes and where money paid comes from. */
function moneySettings(array $f, ?string $receive, ?string $pay): void
{
    $f['company']->setQuickEntryDefaults($receive, $pay);
}

test('receive and sale go to the money-received account', function () {
    $f = moneySeed();
    moneyCustomer($f, 'Al Noor');
    moneySettings($f, $f['hbl']->id, null);

    $r = moneyResolve($this, $f, 'receive', ['amount' => '100', 'customer' => 'al noor']);
    expect($r['params']['deposit_account_id'])->toBe($f['hbl']->id)->and($r['params']['method'])->toBe('bank_transfer');

    expect(moneyResolve($this, $f, 'sale', ['amount' => '100'])['params']['deposit_account_id'])->toBe($f['hbl']->id);
});

test('pay and spend come from the money-paid account', function () {
    $f = moneySeed();
    moneyVendor($f, 'Karachi Office');
    moneySettings($f, null, $f['hbl']->id);

    $r = moneyResolve($this, $f, 'pay', ['vendor' => 'karachi office', 'amount' => '100']);
    expect($r['params']['payment_account_id'])->toBe($f['hbl']->id)->and($r['params']['payment_method'])->toBe('bank_transfer');

    expect(moneyResolve($this, $f, 'spend', ['amount' => '100', 'what' => 'tea'])['params']['paid_from_account_id'])->toBe($f['hbl']->id);
});

test('an account named in the line wins over the setting', function () {
    $f = moneySeed();
    moneyCustomer($f, 'Al Noor');
    moneyVendor($f, 'Karachi Office');
    moneySettings($f, $f['hbl']->id, $f['hbl']->id);

    $in = moneyResolve($this, $f, 'receive', ['amount' => '100', 'customer' => 'al noor', 'account' => 'meezan']);
    expect($in['params']['deposit_account_id'])->toBe($f['meezan']->id);

    $out = moneyResolve($this, $f, 'pay', ['vendor' => 'karachi office', 'amount' => '100', 'account' => 'cash on hand']);
    expect($out['params']['payment_account_id'])->toBe($f['cash']->id)->and($out['params']['payment_method'])->toBe('cash');

    $spend = moneyResolve($this, $f, 'spend', ['amount' => '100', 'what' => 'tea', 'account' => 'meezan']);
    expect($spend['params']['paid_from_account_id'])->toBe($f['meezan']->id);
});

test('a cash account as the setting is used like any other', function () {
    $f = moneySeed();
    moneyCustomer($f, 'Al Noor');
    moneySettings($f, $f['cash']->id, null);

    $r = moneyResolve($this, $f, 'receive', ['amount' => '100', 'customer' => 'al noor']);

    expect($r['params']['deposit_account_id'])->toBe($f['cash']->id)->and($r['params']['method'])->toBe('cash');
});

test('deposit uses the money-received account when it is a bank; withdraw and charge the money-paid one', function () {
    $f = moneySeed();
    moneySettings($f, $f['hbl']->id, $f['hbl2']->id);

    expect(moneyResolve($this, $f, 'deposit', ['amount' => '100'])['params']['bank_account_id'])->toBe($f['hbl']->id);
    expect(moneyResolve($this, $f, 'withdraw', ['amount' => '100'])['params']['bank_account_id'])->toBe($f['hbl2']->id);
    expect(moneyResolve($this, $f, 'bank charge', ['amount' => '50'])['params']['bank_account_id'])->toBe($f['hbl2']->id);
    expect(moneyResolve($this, $f, 'deposit', ['amount' => '100', 'bank' => 'meezan'])['params']['bank_account_id'])->toBe($f['meezan']->id);
});

test('deposit falls back to the company default bank when the setting is cash or empty', function () {
    $f = moneySeed();

    moneySettings($f, $f['cash']->id, null);
    expect(moneyResolve($this, $f, 'deposit', ['amount' => '100'])['params']['bank_account_id'])->toBe($f['meezan']->id);

    moneySettings($f, null, null);
    expect(moneyResolve($this, $f, 'deposit', ['amount' => '100'])['params']['bank_account_id'])->toBe($f['meezan']->id);
});

test('empty or stale settings change nothing', function () {
    $f = moneySeed();
    moneyCustomer($f, 'Al Noor');
    moneySettings($f, (string) \Illuminate\Support\Str::uuid(), null);

    $r = moneyResolve($this, $f, 'receive', ['amount' => '100', 'customer' => 'al noor']);
    expect($r['params']['deposit_account_id'])->toBe($f['cash']->id);

    moneySettings($f, null, null);
    expect(moneyResolve($this, $f, 'spend', ['amount' => '100', 'what' => 'tea'])['params']['paid_from_account_id'])->toBe($f['cash']->id);
});

// ---------------------------------------------------------------------------
// names, undo and where Open goes
// ---------------------------------------------------------------------------

test('names match every word typed, exact name first', function () {
    $banks = [
        ['id' => 'bank-meezan', 'code' => '1060', 'name' => 'Meezan Bank'],
        ['id' => 'bank-hbl', 'code' => '1061', 'name' => 'HBL Current'],
        ['id' => 'bank-hbl2', 'code' => '1062', 'name' => 'HBL Savings'],
    ];
    $ids = fn (string $q) => array_column(Accounts::matchAccounts($banks, $q), 'id');

    expect($ids('meezan'))->toBe(['bank-meezan'])
        ->and($ids('hbl'))->toBe(['bank-hbl', 'bank-hbl2'])
        ->and($ids('hbl savings'))->toBe(['bank-hbl2'])
        ->and($ids('1061'))->toBe(['bank-hbl'])
        ->and($ids('nothing'))->toBe([])
        ->and($ids('HBL CURRENT'))->toBe(['bank-hbl']);
});

test('every command names the command that takes it back and where Open goes', function () {
    $f = moneySeed();

    $sentences = collect(paletteCatalogSentences($this, $f))->keyBy('name');

    $expected = [
        'deposit' => ['bank_transaction.create', '/{company}/journals/{id}', 'bank_transaction.reverse'],
        'withdraw' => ['bank_transaction.create', '/{company}/journals/{id}', 'bank_transaction.reverse'],
        'transfer' => ['bank_transaction.create', '/{company}/journals/{id}', 'bank_transaction.reverse'],
        'bank charge' => ['bank_transaction.create', '/{company}/journals/{id}', 'bank_transaction.reverse'],
        'receive' => ['payment.create', '/{company}/payments/{id}', 'payment.void'],
        'pay' => ['bill_payment.create', '/{company}/bill-payments/{id}', 'bill_payment.void'],
        'spend' => ['expense.create', '/{company}/journals/{id}', 'expense.reverse'],
        'sale' => ['sale.create', 'redirect', 'sale.void'],
    ];
    foreach ($expected as $name => [$key, $opens, $undo]) {
        expect($sentences[$name]['key'])->toBe($key)
            ->and($sentences[$name]['opens'])->toBe($opens)
            ->and($sentences[$name]['undo'])->toBe($undo)
            ->and($sentences[$name]['generic'])->toBeFalse();
    }
});

test('the sentences and aliases are as declared', function () {
    $f = moneySeed();

    $sentences = collect(paletteCatalogSentences($this, $f))->keyBy('name');

    expect($sentences['deposit']['sentence'])->toBe('Deposit {amount} into {bank?} on {date?}')
        ->and($sentences['withdraw']['sentence'])->toBe('Withdraw {amount} from {bank?} on {date?}')
        ->and($sentences['transfer']['sentence'])->toBe('Transfer {amount} from {from} to {to} on {date?}')
        ->and($sentences['bank charge']['sentence'])->toBe('Bank charge {amount} on {bank?} for {note?} on {date?}')
        ->and($sentences['bank charge']['aliases'])->toBe(['bank charges'])
        ->and($sentences['receive']['sentence'])->toBe('Receive {amount} from {customer} into {account?} on {date?}')
        ->and($sentences['receive']['aliases'])->toBe(['received', 'rcv'])
        ->and($sentences['pay']['sentence'])->toBe('Pay {vendor} {amount} from {account?} on {date?}')
        ->and($sentences['pay']['aliases'])->toBe(['paid'])
        ->and($sentences['spend']['sentence'])->toBe('Spend {amount} on {what} from {account?} on {date?}')
        ->and($sentences['spend']['aliases'])->toBe(['expense', 'exp'])
        ->and($sentences['sale']['sentence'])->toBe('Sale {amount} for {note?} on {date?}');
});

function paletteCatalogSentences($test, array $f): array
{
    return $test->actingAs($f['owner'])->withHeader('X-Company-Slug', $f['company']->slug)
        ->getJson('/api/palette/catalog')->assertOk()->json('sentences');
}
