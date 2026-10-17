<?php

use App\Facades\CompanyContext;
use App\Modules\Accounting\Actions\Account\DeleteAction as AccountDelete;
use App\Modules\Accounting\Actions\Customer\DeleteAction as CustomerDelete;
use App\Modules\Accounting\Actions\Customer\IndexAction as CustomerIndex;
use App\Modules\Accounting\Actions\Vendor\DeleteAction as VendorDelete;
use App\Modules\Accounting\Actions\Vendor\IndexAction as VendorIndex;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\VendorCredit;
use App\Modules\Accounting\Services\AccountStatementService;
use App\Modules\Accounting\Services\DashboardService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/BillPaymentEditFixtures.php';

/**
 * A reopened daily close soft-deletes what it created, and a re-save leaves another trashed
 * copy. Anything reading those tables with DB::table() sees the trashed rows unless it says
 * otherwise -- these pin the readers that decide or total money.
 */
function trashedFixture(): array
{
    $f = billPaymentEditFixture();
    CompanyContext::setContext($f['company']);
    $f['ar'] = Account::create(['company_id' => $f['company']->id, 'code' => '1100', 'name' => 'AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $f['customer'] = Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-1', 'name' => 'Haulage Ltd',
        'base_currency' => 'PKR', 'ar_account_id' => $f['ar']->id, 'is_active' => true,
    ]);

    return $f;
}

function trashedInvoice(array $f, float $balance, string $due = '2026-01-01'): Invoice
{
    return Invoice::create([
        'company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'invoice_number' => 'INV-'.str()->random(8),
        'invoice_date' => '2025-12-01', 'due_date' => $due, 'status' => 'sent', 'currency' => 'PKR', 'base_currency' => 'PKR',
        'subtotal' => $balance, 'total_amount' => $balance, 'paid_amount' => 0, 'balance' => $balance,
    ]);
}

function trashedBill(array $f, float $amount, string $due): Bill
{
    return Bill::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'bill_number' => 'BILL-GHOST-'.str()->random(5), 'bill_date' => '2026-08-07',
        'due_date' => $due, 'status' => 'received', 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1,
        'subtotal' => $amount, 'total_amount' => $amount, 'paid_amount' => 0, 'balance' => $amount, 'base_amount' => $amount,
    ]);
}

test('the customer list balance leaves out a trashed invoice', function () {
    $f = trashedFixture();
    trashedInvoice($f, 16000);
    trashedInvoice($f, 29000)->delete();

    $row = app(CustomerIndex::class)->handle([])['data']['rows'][0];

    expect($row[3])->toContain('16,000')->not->toContain('45,000');
});

test('the vendor list owed figure leaves out a trashed bill', function () {
    $f = trashedFixture(); // two live bills: 20,000 and 5,000
    trashedBill($f, 9000, '2026-09-07')->delete();

    $row = app(VendorIndex::class)->handle([])['data']['rows'][0];

    expect($row[3])->toContain('25,000')->not->toContain('34,000');
});

test('the dashboard attention counts leave out trashed invoices and bills', function () {
    $f = trashedFixture();
    $soon = now()->addDays(2)->toDateString();
    trashedInvoice($f, 1000, '2000-01-01');
    trashedInvoice($f, 2000, '2000-01-01')->delete();
    DB::table('acct.bills')->where('company_id', $f['company']->id)->update(['due_date' => $soon]);
    trashedBill($f, 9000, $soon)->delete();

    $figures = app(DashboardService::class)->getNeedsAttention($f['company']->id);

    expect($figures['overdue_invoices'])->toBe(1)
        ->and($figures['bills_due_soon'])->toBe(2)
        ->and($figures['bills_due_soon_amount'])->toBe(25000.0);
});

test('an account statement ignores a trashed transaction whose lines are still there', function () {
    $f = trashedFixture();
    $entry = function (string $number, float $amount, bool $trashed) use ($f) {
        $id = (string) str()->uuid();
        DB::table('acct.transactions')->insert([
            'id' => $id, 'company_id' => $f['company']->id, 'transaction_number' => $number,
            'fiscal_year_id' => $f['sepPeriod']->fiscal_year_id, 'period_id' => $f['sepPeriod']->id,
            'transaction_type' => 'journal', 'transaction_date' => '2026-09-05', 'posting_date' => '2026-09-05', 'description' => $number,
            'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => $amount, 'total_credit' => $amount, 'status' => 'posted',
            'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $trashed ? now() : null,
        ]);
        foreach ([[$f['cash']->id, $amount, 0], [$f['bank']->id, 0, $amount]] as $i => [$acc, $dr, $cr]) {
            DB::table('acct.journal_entries')->insert([
                'id' => (string) str()->uuid(), 'company_id' => $f['company']->id, 'transaction_id' => $id, 'account_id' => $acc,
                'line_number' => $i + 1, 'debit_amount' => $dr, 'credit_amount' => $cr, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    };
    $entry('J-LIVE', 3000, false);
    $entry('J-GHOST', 7000, true);

    $statement = app(AccountStatementService::class)->statement($f['cash'], '2026-09-01', '2026-09-30');

    expect(round((float) $statement['closing_balance'], 2))->toBe(3000.0);
});

test('an account used only by a trashed customer can still be deleted, a live one cannot', function () {
    $f = trashedFixture();
    $acct = Account::create(['company_id' => $f['company']->id, 'code' => '1190', 'name' => 'Spare AR', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'normal_balance' => 'debit', 'currency' => 'PKR', 'is_active' => true]);
    $other = Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-2', 'name' => 'Spare', 'base_currency' => 'PKR', 'ar_account_id' => $acct->id, 'is_active' => true,
    ]);

    expect(fn () => app(AccountDelete::class)->handle(['id' => $acct->id]))->toThrow(InvalidArgumentException::class, 'customer AR account');

    $other->delete();
    app(AccountDelete::class)->handle(['id' => $acct->id]);

    expect(Account::find($acct->id))->toBeNull();
});

test('a trashed credit note does not stop a customer being deleted', function () {
    $f = trashedFixture();
    $note = CreditNote::create([
        'company_id' => $f['company']->id, 'customer_id' => $f['customer']->id, 'credit_note_number' => 'CN-1', 'credit_date' => '2026-09-01',
        'amount' => 500, 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => null, 'status' => 'issued', 'reason' => 'return', 'base_amount' => 500,
    ]);

    expect(fn () => app(CustomerDelete::class)->handle(['id' => $f['customer']->id]))->toThrow(Exception::class, 'credit note');

    $note->delete();
    app(CustomerDelete::class)->handle(['id' => $f['customer']->id]);

    expect($f['customer']->fresh()->is_active)->toBeFalse();
});

test('a trashed vendor credit does not stop a vendor being deleted', function () {
    $f = trashedFixture();
    Bill::query()->where('company_id', $f['company']->id)->update(['balance' => 0, 'status' => 'paid']);
    $credit = VendorCredit::create([
        'company_id' => $f['company']->id, 'vendor_id' => $f['vendor']->id, 'credit_number' => 'VC-1', 'vendor_credit_number' => 'VC-1',
        'credit_date' => '2026-09-01', 'amount' => 500, 'currency' => 'PKR', 'base_currency' => 'PKR', 'exchange_rate' => 1, 'status' => 'open', 'reason' => 'return', 'base_amount' => 500,
    ]);

    expect(fn () => app(VendorDelete::class)->handle(['id' => $f['vendor']->id]))->toThrow(Exception::class, 'vendor credit');

    $credit->delete();
    app(VendorDelete::class)->handle(['id' => $f['vendor']->id]);

    expect($f['vendor']->fresh()->is_active)->toBeFalse();
});
