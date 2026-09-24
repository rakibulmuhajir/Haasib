<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\Pump;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/CreditCloseFixtures.php';

test('cash card and credit meter sales post revenue once and create a collectible native invoice', function () {
    $f = creditCloseFixture();
    $posted = creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    expect($invoice->status)->toBe('sent')->and((float) $invoice->balance)->toBe(6000.0)
        ->and($invoice->transaction_id)->toBe($posted['transaction_id']);
    $totals = $posted['metadata']['posting_snapshot']['totals'];
    expect((float) $totals['money_in'])->toBe(30000.0)->and((float) $totals['money_out'])->toBe(15000.0)
        ->and((float) $totals['expected_closing'])->toBe(25000.0)->and((float) $totals['variance'])->toBe(0.0);
    $entries = Transaction::findOrFail($posted['transaction_id'])->journalEntries;
    foreach (['1050' => 15000, '1020' => 9000, '1100' => 6000, '5100' => 25000] as $code => $amount) {
        expect((float) $entries->where('account_id', $f['accounts'][$code]->id)->sum('debit_amount'))->toBe((float) $amount);
    }
    expect((float) $entries->where('account_id', $f['accounts']['4100']->id)->sum('credit_amount'))->toBe(30000.0);
    expect((float) $entries->where('account_id', $f['accounts']['1200']->id)->sum('credit_amount'))->toBe(25000.0);
    expect((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe(1);
    $snapshot = Transaction::findOrFail($posted['transaction_id'])->metadata;
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('payment.create', [
        'invoice' => $invoice->id, 'amount' => 6000, 'method' => 'cash', 'date' => '2026-09-16',
        'deposit_account_id' => $f['accounts']['1050']->id, 'ar_account_id' => $f['accounts']['1100']->id,
    ], $f['user'], true));
    expect((float) $invoice->fresh()->balance)->toBe(0.0)->and($invoice->fresh()->status)->toBe('paid');
    expect(Transaction::findOrFail($posted['transaction_id'])->metadata)->toBe($snapshot);
    expect((float) DB::table('acct.journal_entries')->where('account_id', $f['accounts']['4100']->id)->sum('credit_amount'))->toBe(30000.0);
    $next = app(DailyCloseReconciliationService::class)->sources($f['company']->id, '2026-09-16');
    expect(array_sum(array_column($next, 'cash_effect')))->toBe(6000.0);
});

test('card-only allocation is gross in both posted and current reconciliation', function () {
    $f = creditCloseFixture(); $f['payload']['credit_sales'] = []; $f['payload']['closing_cash'] = 31000;
    $posted = creditClosePost($f);
    $view = app(DailyCloseReconciliationService::class)->view(Transaction::findOrFail($posted['transaction_id']));
    foreach ([$view['snapshot']['totals'], $view['current']] as $totals) {
        expect((float) $totals['money_in'])->toBe(30000.0)->and((float) $totals['money_out'])->toBe(9000.0)
            ->and((float) $totals['expected_closing'])->toBe(31000.0);
    }
});

test('invalid credit allocations roll back the entire close', function (string $invalid) {
    $f = creditCloseFixture();
    if ($invalid === 'excess') { $f['payload']['credit_sales'][0]['amount'] = 22000; }
    if ($invalid === 'duplicate') { $f['payload']['credit_sales'][] = $f['payload']['credit_sales'][0]; }
    if ($invalid === 'foreign') {
        $other = Company::create(['name' => 'Other', 'slug' => 'other-'.str()->random(10), 'base_currency' => 'PKR']);
        enterCompany($other);
        $outsider = Customer::create(['company_id' => $other->id, 'customer_number' => 'C-2', 'name' => 'Other', 'base_currency' => 'PKR']);
        enterCompany($f['company']);
        $f['payload']['credit_sales'][0]['customer_id'] = $outsider->id;
    }
    expect(fn () => creditClosePost($f))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Invoice::where('company_id', $f['company']->id)->count())->toBe(0);
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe(0);
})->with(['excess', 'duplicate', 'foreign']);

test('parking credit sales creates no invoice and posting a date twice does not duplicate it', function () {
    $f = creditCloseFixture();
    $service = app(DailyCloseReconciliationService::class);
    $service->park($f['company']->id, $f['payload'], $f['user']->id);
    expect(Invoice::where('company_id', $f['company']->id)->count())->toBe(0);
    expect($service->draft($f['company']->id, $f['payload']['date'])['credit_sales'])->toEqual($f['payload']['credit_sales']);
    creditClosePost($f);
    expect(fn () => creditClosePost($f))->toThrow(\RuntimeException::class);
    expect(Invoice::where('company_id', $f['company']->id)->count())->toBe(1);
});

test('posted credit invoice principal and lines cannot be altered', function () {
    $f = creditCloseFixture(); creditClosePost($f);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    expect(fn () => $invoice->update(['total_amount' => 1]))->toThrow(\RuntimeException::class);
    expect(fn () => $invoice->delete())->toThrow(\RuntimeException::class);
    expect(fn () => DB::transaction(fn () => DB::table('acct.invoice_line_items')->where('invoice_id', $invoice->id)->update(['unit_price' => 1])))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('acct.invoices')->where('id', $invoice->id)->update(['total_amount' => 1])))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('version one snapshots normalize frozen card totals without rewriting history', function () {
    $f = creditCloseFixture();
    $snapshot = ['version' => 1, 'posted_by' => $f['user']->id, 'posted_at' => now()->toISOString(), 'sources' => [],
        'cash_account_id' => $f['accounts']['1050']->id, 'channels' => [['amount' => 9000]],
        'totals' => ['total_revenue' => 30000, 'money_in' => 21000, 'money_out' => 0, 'opening_cash' => 10000, 'closing_cash' => 31000, 'expected_closing' => 31000, 'variance' => 0]];
    $period = AccountingPeriod::where('company_id', $f['company']->id)->sole();
    $close = Transaction::create(['company_id' => $f['company']->id, 'fiscal_year_id' => $period->fiscal_year_id, 'period_id' => $period->id, 'transaction_number' => 'OLD-CLOSE', 'transaction_type' => 'fuel_daily_close', 'transaction_date' => '2026-09-15', 'currency' => 'PKR', 'base_currency' => 'PKR', 'status' => 'posted', 'metadata' => ['posting_snapshot' => $snapshot]]);
    $before = $close->metadata;
    $view = app(DailyCloseReconciliationService::class)->view($close);
    expect((float) $view['snapshot']['totals']['money_in'])->toBe(30000.0)->and((float) $view['current']['money_out'])->toBe(9000.0);
    expect($close->fresh()->metadata)->toEqual($before);
});

test('a buyer created inline (quick-add) and used the same close creates exactly one customer and links the sale', function () {
    $f = creditCloseFixture();
    $created = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('customer.create', [
        'name' => 'Walk-in Tanker Buyer', 'company_id' => $f['company']->id, 'base_currency' => $f['company']->base_currency, 'is_active' => true,
    ], $f['user'], true));
    $newCustomerId = $created['data']['id'];

    $f['payload']['credit_sales'] = [
        ['customer_id' => $newCustomerId, 'amount' => 6000, 'reference' => 'Inline buyer sale'],
    ];
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    expect(Customer::where('company_id', $f['company']->id)->where('name', 'Walk-in Tanker Buyer')->count())->toBe(1);
    $invoice = Invoice::where('company_id', $f['company']->id)->where('customer_id', $newCustomerId)->sole();
    expect($invoice->transaction_id)->toBe($posted['transaction_id']);
});
