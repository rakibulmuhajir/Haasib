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

function creditCloseFixture(): array
{
    $user = User::factory()->create();
    test()->actingAs($user);
    $company = Company::create(['name' => 'Credit close', 'slug' => 'credit-close-'.str()->random(10), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $accounts = [];
    foreach ([['1050','asset','cash','debit'], ['1020','asset','bank','debit'], ['1100','asset','accounts_receivable','debit'], ['1200','asset','inventory','debit'], ['4100','revenue','other_income','credit'], ['5100','cogs','cost_of_goods_sold','debit']] as [$code,$type,$subtype,$normal]) {
        $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $type === 'asset' && $code !== '1200' ? 'PKR' : null, 'is_active' => true]);
    }
    StationSettings::create(['company_id' => $company->id, 'payment_channels' => [
        ['code' => 'pos', 'label' => 'HBL POS', 'type' => 'card_pos', 'enabled' => true, 'clearing_account_id' => $accounts['1020']->id],
    ]]);
    $customer = Customer::create(['company_id' => $company->id, 'customer_number' => 'C-1', 'name' => 'Truck owner', 'base_currency' => 'PKR', 'ar_account_id' => $accounts['1100']->id, 'is_active' => true]);
    $item = Item::create(['company_id' => $company->id, 'sku' => 'PETROL', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR', 'avg_cost' => 250]);
    $tank = Warehouse::create(['company_id' => $company->id, 'code' => 'T1', 'name' => 'Tank', 'linked_item_id' => $item->id]);
    $pump = Pump::create(['company_id' => $company->id, 'name' => 'Pump', 'tank_id' => $tank->id]);
    $nozzle = Nozzle::create(['company_id' => $company->id, 'pump_id' => $pump->id, 'tank_id' => $tank->id, 'item_id' => $item->id, 'code' => 'N1', 'label' => 'Front']);
    $payload = ['date' => '2026-09-15', 'opening_cash' => 10000, 'closing_cash' => 25000,
        'nozzle_readings' => [['nozzle_id' => $nozzle->id, 'item_id' => $item->id, 'opening_electronic' => 0, 'closing_electronic' => 100, 'liters_sold' => 100, 'sale_rate' => 300]],
        'payment_receipts' => ['pos' => ['entries' => [['last_four' => '1234', 'amount' => 9000]]]],
        'credit_sales' => [['customer_id' => $customer->id, 'amount' => 6000, 'reference' => 'Slip 42']],
    ];
    return compact('user', 'company', 'customer', 'accounts', 'payload');
}

function creditClosePost(array $f): array
{
    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
}

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
