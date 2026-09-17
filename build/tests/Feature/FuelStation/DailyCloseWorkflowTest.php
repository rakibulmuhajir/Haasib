<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;

function closeWorkflowFixture(): array
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Close workflow', 'slug' => 'close-'.str()->random(12), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    DB::statement("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    test()->actingAs($user);
    $fy = FiscalYear::create(['company_id' => $company->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']);
    AccountingPeriod::create(['company_id' => $company->id, 'fiscal_year_id' => $fy->id, 'name' => 'September', 'period_number' => 9, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30']);
    $accounts = [];
    foreach ([['1050','asset','cash','debit'], ['1200','asset','inventory','debit'], ['4100','revenue','other_income','credit'], ['5100','cogs','cost_of_goods_sold','debit'], ['6180','expense','operating_expense','debit']] as [$code,$type,$subtype,$normal]) {
        $accounts[$code] = Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $code, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal, 'currency' => $code === '1050' ? 'PKR' : null, 'is_active' => true]);
    }
    // This fixture has no configured nozzles at all, so every post here is a
    // genuine zero-sales-day close; confirm it explicitly so the HTTP/CommandBus
    // validation path (which now refuses an unconfirmed empty post) accepts it.
    $payload = ['date' => '2026-09-15', 'opening_cash' => 420000, 'closing_cash' => 410000, 'nozzle_readings' => [],
        'zero_sales_confirmed' => true, 'zero_sales_reason' => 'No nozzles configured in this test fixture'];
    return compact('user', 'company', 'accounts', 'payload');
}

function workflowExpense(array $f, string $date = '2026-09-15', float $amount = 10000): Transaction
{
    return app(GlPostingService::class)->postBalancedTransaction([
        'company_id' => $f['company']->id, 'transaction_type' => 'expense', 'date' => $date, 'currency' => 'PKR',
    ], [
        ['account_id' => $f['accounts']['6180']->id, 'type' => 'debit', 'amount' => $amount],
        ['account_id' => $f['accounts']['1050']->id, 'type' => 'credit', 'amount' => $amount],
    ]);
}

test('park with shortage resumes editable without journal and picks up forgotten canonical expense before posting', function () {
    $f = closeWorkflowFixture();
    $service = app(DailyCloseReconciliationService::class);
    $service->park($f['company']->id, $f['payload'], $f['user']->id);
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe(0);
    expect($service->draft($f['company']->id, '2026-09-15')['closing_cash'])->toBe(410000);
    $f['payload']['notes'] = 'Investigated receipt';
    $service->park($f['company']->id, $f['payload'], $f['user']->id);
    expect($service->draft($f['company']->id, '2026-09-15')['notes'])->toBe('Investigated receipt');
    workflowExpense($f);
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    expect($result['metadata']['variance'])->toBe(0.0);
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'expense')->count())->toBe(1);
    expect($service->draft($f['company']->id, '2026-09-15'))->toBeNull();
    expect(fn () => $service->park($f['company']->id, $f['payload'], $f['user']->id))->toThrow(RuntimeException::class);
});

test('posting short is immutable while late expense reconciles and downstream physical close remains unchanged', function () {
    $f = closeWorkflowFixture();
    test()->travelTo(\Carbon\Carbon::parse('2026-09-16 10:00:00'));
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    $original = $close->metadata;
    expect((float) $original['variance'])->toBe(-10000.0);
    expect($original['posting_snapshot']['business_date'])->toBe('2026-09-15');
    expect($original['posting_snapshot']['posted_by'])->toBe($f['user']->id);
    $nextPayload = array_replace($f['payload'], ['date' => '2026-09-16', 'opening_cash' => 410000, 'closing_cash' => 409999]);
    $next = app(DailyCloseService::class)->processDailyClose($f['company']->id, $nextPayload, $f['user']);
    $nextOriginal = Transaction::findOrFail($next['transaction_id'])->metadata;
    test()->travelTo(\Carbon\Carbon::parse('2026-09-16 14:00:00'));
    workflowExpense($f);
    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    expect($view['current']['variance'])->toBe(0.0);
    expect($view['activity'])->toHaveCount(1);
    expect($view['activity'][0]['business_date'])->toBe('2026-09-15');
    expect($view['activity'][0]['entered_by'])->toBe($f['user']->id);
    expect($view['activity'][0]['reconciliation_effect'])->toBe(-10000.0);
    expect($close->fresh()->metadata)->toBe($original);
    expect(Transaction::findOrFail($next['transaction_id'])->metadata)->toBe($nextOriginal);
    expect(app(DailyCloseService::class)->getPreviousDayClosing($f['company']->id, '2026-09-16')['closing_cash'])->toBe(410000.0);
    test()->travelBack();
});

test('a supplier bill payment posted through daily close records an em dash separated payment account name', function () {
    $f = closeWorkflowFixture();

    $apAccount = Account::create([
        'company_id' => $f['company']->id,
        'code' => '2100',
        'name' => 'Accounts Payable',
        'type' => 'liability',
        'subtype' => 'accounts_payable',
        'normal_balance' => 'credit',
        'is_active' => true,
    ]);

    $vendor = Vendor::create([
        'company_id' => $f['company']->id,
        'vendor_number' => 'VEND-0001',
        'name' => 'Acme Fuel Supplies',
        'base_currency' => 'PKR',
        'is_active' => true,
        'ap_account_id' => $apAccount->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    $bill = Bill::create([
        'company_id' => $f['company']->id,
        'vendor_id' => $vendor->id,
        'bill_number' => 'BILL-0001',
        'bill_date' => '2026-09-01',
        'due_date' => '2026-09-30',
        'status' => 'received',
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'exchange_rate' => 1,
        'subtotal' => 5000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 5000,
        'paid_amount' => 0,
        'balance' => 5000,
        'base_amount' => 5000,
        'created_by_user_id' => $f['user']->id,
    ]);

    BillPayment::create([
        'company_id' => $f['company']->id,
        'vendor_id' => $vendor->id,
        'payment_number' => 'PAY-0001',
        'payment_date' => '2026-09-15',
        'amount' => 5000,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'base_amount' => 5000,
        'payment_method' => 'cash',
        'payment_account_id' => $f['accounts']['1050']->id,
        'created_by_user_id' => $f['user']->id,
    ]);

    $payload = array_replace($f['payload'], ['closing_cash' => 415000]);
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);
    $close = Transaction::findOrFail($result['transaction_id']);

    expect($close->metadata['bill_payment_details'])->toHaveCount(1);
    $detail = $close->metadata['bill_payment_details'][0];
    expect($detail['payment_account_name'])->toBe('1050 — 1050');
    expect($detail['payment_account_name'])->toContain(' — ');
    expect($detail['payment_account_name'])->not->toContain('â€”');
});

test('dispatching fuel.daily_close.save through the CommandBus HTTP endpoint succeeds without a bogus date error', function () {
    $f = closeWorkflowFixture();
    enableCloseHttp($f);

    $response = test()
        ->withHeaders(['X-Action' => 'fuel.daily_close.save', 'X-Company-Slug' => $f['company']->slug])
        ->postJson('/api/commands', ['params' => $f['payload']]);

    $response->assertCreated();
    expect($response->json('ok'))->toBeTrue();
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->count())->toBe(1);
});

test('dispatching fuel.daily_close.save via the CommandBus directly (no current request) succeeds', function () {
    $f = closeWorkflowFixture();
    app(\App\Services\CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CompanyContextService::class)->assignRole($f['user'], 'owner'));

    // Simulate a non-HTTP dispatch (queued job, console command, tinker) where
    // app(Request::class) still resolves to some other/empty request, proving
    // SaveDailyCloseAction::rules() no longer depends on the current HTTP request.
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    $result = app(\App\Services\CompanyContextService::class)->withContext($f['company'], function () use ($f) {
        return app(\App\Services\CommandBus::class)->dispatch('fuel.daily_close.save', $f['payload'], $f['user']);
    });

    expect($result)->toHaveKey('transaction_id');
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->count())->toBe(1);
});

test('an empty post without zero-sales confirmation is rejected, with confirmation it posts and records the flag, and park stays lenient', function () {
    $f = closeWorkflowFixture();
    enableCloseHttp($f);
    $url = "/{$f['company']->slug}/fuel/daily-close";

    // park with empty readings must stay ok (no confirmation required).
    test()->post($url, $f['payload'] + ['intent' => 'park'])->assertSessionHasNoErrors()->assertSessionHas('success');

    // An empty post without the explicit flag must be rejected (422/validation), not silently claim the date.
    $unconfirmedPayload = array_merge($f['payload'], ['intent' => 'post', 'zero_sales_confirmed' => false, 'zero_sales_reason' => null]);
    test()->post($url, $unconfirmedPayload)->assertSessionHasErrors('nozzle_readings');
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->count())->toBe(0);

    // An empty post WITH the explicit zero-sales confirmation and a reason must succeed.
    test()->post($url, array_merge($f['payload'], [
        'intent' => 'post',
        'zero_sales_confirmed' => true,
        'zero_sales_reason' => 'Station closed for maintenance all day',
    ]))->assertSessionHasNoErrors()->assertSessionHas('success');

    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->firstOrFail();
    expect($close->metadata['posting_snapshot']['zero_sales_confirmed'])->toBeTrue();
    expect($close->metadata['posting_snapshot']['zero_sales_reason'])->toBe('Station closed for maintenance all day');
});

test('entry time never assigns business date and company sources never leak', function () {
    $f = closeWorkflowFixture();
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    workflowExpense($f, '2026-09-16');
    $other = closeWorkflowFixture();
    workflowExpense($other);
    $view = app(DailyCloseReconciliationService::class)->view(Transaction::findOrFail($posted['transaction_id']));
    expect($view['activity'])->toBe([]);
    expect($view['current']['variance'])->toBe(-10000.0);
});

test('reversal exposes separate cash effect without changing snapshot', function () {
    $f = closeWorkflowFixture();
    $expense = workflowExpense($f);
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    $original = $close->metadata;
    app(PostingService::class)->reverseTransaction($expense, 'Receipt cancelled', '2026-09-15');
    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    expect($view['current']['variance'])->toBe(-10000.0);
    expect($view['activity'])->not->toBeEmpty();
    expect($close->fresh()->metadata)->toBe($original);
});


test('expenses initiated in daily close create one canonical accounting transaction', function () {
    $f = closeWorkflowFixture();
    $f['payload']['expenses'] = [['account_id' => $f['accounts']['6180']->id, 'amount' => 10000, 'description' => 'Generator repair']];
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $expenses = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'expense')->get();
    expect($expenses)->toHaveCount(1);
    expect($expenses[0]->transaction_date->toDateString())->toBe('2026-09-15');
    expect($result['metadata']['variance'])->toBe(0.0);
    $close = Transaction::findOrFail($result['transaction_id']);
    expect($close->isAmendable())->toBeFalse();
    expect($close->journalEntries()->where('account_id', $f['accounts']['6180']->id)->count())->toBe(0);
});

test('database rejects replacing or deleting a posted snapshot', function () {
    $f = closeWorkflowFixture();
    $result = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $id = $result['transaction_id'];
    expect(fn () => DB::transaction(fn () => DB::table('acct.transactions')->where('id', $id)->update(['metadata' => '{}'])))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('acct.transactions')->where('id', $id)->delete()))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect(Transaction::findOrFail($id)->metadata['posting_snapshot']['posted_by'])->toBe($f['user']->id);
});


test('stock purchase and stock correction change reconciliation while physical dip stays frozen', function () {
    $f = closeWorkflowFixture();
    $item = \App\Modules\Inventory\Models\Item::create(['company_id' => $f['company']->id, 'sku' => 'DIESEL', 'name' => 'Diesel', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR']);
    $tank = \App\Modules\Inventory\Models\Warehouse::create(['company_id' => $f['company']->id, 'code' => 'TANK', 'name' => 'Tank', 'linked_item_id' => $item->id]);
    $movement = ['company_id' => $f['company']->id, 'warehouse_id' => $tank->id, 'item_id' => $item->id, 'movement_date' => '2026-09-14', 'movement_type' => 'opening', 'quantity' => 1000, 'unit_cost' => 0, 'total_cost' => 0, 'created_by_user_id' => $f['user']->id];
    \App\Modules\Inventory\Models\StockMovement::create($movement);
    $f['payload']['tank_readings'] = [['tank_id' => $tank->id, 'stick_reading' => 100, 'liters' => 1000]];
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    $original = $close->metadata;
    \App\Modules\Inventory\Models\StockMovement::create(array_replace($movement, ['movement_date' => '2026-09-15', 'movement_type' => 'purchase', 'quantity' => 100]));
    $view = app(DailyCloseReconciliationService::class)->view($close);
    expect($view['current']['tanks'][0]['physical_liters'])->toEqual(1000);
    expect($view['current']['tanks'][0]['variance_liters'])->toBe(-100.0);
    \App\Modules\Inventory\Models\StockMovement::create(array_replace($movement, ['movement_date' => '2026-09-15', 'movement_type' => 'adjustment_out', 'quantity' => -30]));
    $view = app(DailyCloseReconciliationService::class)->view($close);
    expect($view['current']['tanks'][0]['variance_liters'])->toBe(-70.0);
    expect($close->fresh()->metadata)->toBe($original);
    expect((float) \App\Modules\FuelStation\Models\TankReading::where('tank_id', $tank->id)->value('dip_measurement_liters'))->toBe(1000.0);
    expect(fn () => DB::transaction(fn () => DB::table('fuel.tank_readings')->where('tank_id', $tank->id)->update(['dip_measurement_liters' => 900])))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect(app(DailyCloseService::class)->openingBaselineForTank($f['company']->id, $tank->id, $item->id, '2026-09-16')['liters'])->toBe(1000.0);

});

test('amended and deleted canonical journals expose differences against their original source evidence', function () {
    $f = closeWorkflowFixture();
    $expense = workflowExpense($f);
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    $snapshot = $close->metadata;
    app(PostingService::class)->reverseTransaction($expense, 'Correct amount', '2026-09-16');
    $replacement = workflowExpense($f, '2026-09-15', 7000);
    $view = app(DailyCloseReconciliationService::class)->view($close);
    expect($view['current']['variance'])->toBe(-3000.0);
    $replacement->delete();
    $view = app(DailyCloseReconciliationService::class)->view($close);
    expect($view['current']['variance'])->toBe(-10000.0);
    expect($close->fresh()->metadata)->toBe($snapshot);
});

test('post-close expense service refuses another company expense account atomically', function () {
    $f = closeWorkflowFixture();
    $other = closeWorkflowFixture();
    $count = Transaction::where('company_id', $f['company']->id)->count();
    expect(fn () => app(\App\Modules\FuelStation\Services\DailyCloseEntryService::class)->expense($f['company']->id, '2026-09-15', [
        'account_id' => $other['accounts']['6180']->id, 'amount' => 100, 'description' => 'Wrong company',
    ]))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(Transaction::where('company_id', $f['company']->id)->count())->toBe($count);
});


test('unchanged canonical sources are not falsely flagged after JSON snapshot roundtrip and history renders', function () {
    $f = closeWorkflowFixture();
    workflowExpense($f);
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    expect(app(DailyCloseReconciliationService::class)->view($close)['activity'])->toBe([]);
    $history = app(DailyCloseService::class)->getRecentCloses($f['company']->id, 365);
    expect($history[0]['has_post_close_activity'])->toBeFalse();
    expect($close->isLockable())->toBeTrue();
});


test('history list runs a small fixed number of queries regardless of how many closes it lists', function () {
    $f = closeWorkflowFixture();
    foreach (range(1, 10) as $i) {
        $date = sprintf('2026-09-%02d', $i);
        $payload = array_replace($f['payload'], ['date' => $date]);
        app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);
    }

    DB::enableQueryLog();
    $history = app(DailyCloseService::class)->getRecentCloses($f['company']->id, 365);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($history)->toHaveCount(10);
    // One query for the closes themselves, one aggregated query for the audit
    // flag - not ~6-7 queries per close (which would be 60-70+ for 10 closes).
    expect($queryCount)->toBeLessThan(10);
});

test('updating a tank reading on a posted Daily Close date surfaces a friendly error instead of a 500', function () {
    $f = closeWorkflowFixture();
    enableCloseHttp($f);

    $item = Item::create(['company_id' => $f['company']->id, 'sku' => 'DIESEL-1', 'name' => 'Diesel', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR']);
    $tank = Warehouse::create(['company_id' => $f['company']->id, 'code' => 'TANK-1', 'name' => 'Tank 1', 'linked_item_id' => $item->id, 'warehouse_type' => 'tank', 'capacity' => 10000]);

    // Created before the close is posted: the trigger only blocks writes
    // dated on an already-posted Daily Close, so the reading must exist first.
    $reading = TankReading::create([
        'company_id' => $f['company']->id,
        'tank_id' => $tank->id,
        'item_id' => $item->id,
        'reading_date' => '2026-09-15',
        'reading_type' => 'closing',
        'stick_reading' => 100,
        'dip_measurement_liters' => 5000,
        'system_calculated_liters' => 5000,
        'variance_liters' => 0,
        'variance_type' => 'none',
        'status' => 'draft',
        'recorded_by_user_id' => $f['user']->id,
    ]);

    app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);

    $response = test()->put("/{$f['company']->slug}/fuel/tank-readings/{$reading->id}", [
        'dip_measurement_liters' => 5500,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('immutable');
});

test('late entry subsequently deleted remains visible in append-only audit history', function () {
    $f = closeWorkflowFixture();
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $expense = workflowExpense($f);
    $expense->delete();
    $view = app(DailyCloseReconciliationService::class)->view(Transaction::findOrFail($posted['transaction_id']));
    expect($view['activity'])->toBe([]);
    expect($view['has_post_close_activity'])->toBeTrue();
    expect(count($view['audit_events']))->toBeGreaterThanOrEqual(2);
    expect($view['current']['variance'])->toBe(-10000.0);
    $event = $view['audit_events'][0];
    expect(fn () => DB::transaction(fn () => DB::table('fuel.daily_close_activity')->where('id', $event->id)->delete()))
        ->toThrow(\Illuminate\Database\QueryException::class);
});


function enableCloseHttp(array $f): void
{
    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$f['user']->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(\App\Services\CompanyRbacBootstrapper::class)->bootstrap($f['company']);
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CompanyContextService::class)->assignRole($f['user'], 'owner'));
    $f['company']->enableModule('fuel_station');
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    test()->actingAs($f['user']);
}

test('HTTP park resume post and late expense show snapshot current totals and history flag', function () {
    $f = closeWorkflowFixture(); enableCloseHttp($f);
    $url = "/{$f['company']->slug}/fuel/daily-close";
    test()->post($url, $f['payload'] + ['intent' => 'park'])->assertSessionHasNoErrors()->assertSessionHas('success');
    $page = test()->get($url.'?date=2026-09-15')->assertOk()->viewData('page')['props'];
    expect($page['parkedDraft']['closing_cash'])->toBe(410000);
    test()->post($url, $f['payload'])->assertSessionHasNoErrors()->assertSessionHas('success');
    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->firstOrFail();
    test()->post($url.'/'.$close->id.'/expenses', ['account_id' => $f['accounts']['6180']->id, 'amount' => 10000, 'description' => 'Forgotten repair'])->assertSessionHasNoErrors()->assertSessionHas('success');
    $page = test()->get($url.'/'.$close->id)->assertOk()->viewData('page')['props'];
    expect((float) $page['reconciliation']['snapshot']['totals']['variance'])->toBe(-10000.0);
    expect((float) $page['reconciliation']['current']['variance'])->toBe(0.0);
    expect($page['reconciliation']['has_post_close_activity'])->toBeTrue();
    $history = test()->get($url.'/history')->assertOk()->viewData('page')['props'];
    expect($history['closes'][0]['has_post_close_activity'])->toBeTrue();
    test()->get($url.'/'.$close->id.'/amend')->assertRedirect();
});

test('HTTP workflow refuses unauthorized users and other-company close ids', function () {
    $f = closeWorkflowFixture(); enableCloseHttp($f);
    $other = closeWorkflowFixture();
    $posted = app(DailyCloseService::class)->processDailyClose($other['company']->id, $other['payload'], $other['user']);
    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/daily-close/{$posted['transaction_id']}/expenses", [
        'account_id' => $f['accounts']['6180']->id, 'amount' => 10, 'description' => 'Wrong close',
    ])->assertNotFound();
    $stranger = User::factory()->create();
    DB::table('auth.company_user')->insert(['company_id' => $f['company']->id, 'user_id' => $stranger->id, 'role' => 'operations', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    test()->actingAs($stranger)->post("/{$f['company']->slug}/fuel/daily-close", $f['payload'] + ['intent' => 'park'])->assertForbidden();
});

test('normal Amanat form has explicit business date and is included once in daily close', function () {
    $f = closeWorkflowFixture(); enableCloseHttp($f);
    Account::create(['company_id' => $f['company']->id, 'code' => '2200', 'name' => 'Customer Amanat Deposits', 'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'currency' => 'PKR', 'is_active' => true]);
    $customer = \App\Modules\Accounting\Models\Customer::create(['company_id' => $f['company']->id, 'customer_number' => 'AM-ONE', 'name' => 'Amanat customer', 'base_currency' => 'PKR']);
    test()->post("/{$f['company']->slug}/fuel/amanat/{$customer->id}/deposit", ['business_date' => '2026-09-15', 'amount' => 100, 'reference' => 'Receipt'])->assertSessionHasNoErrors()->assertSessionHas('success');
    $f['payload']['closing_cash'] = 420100;
    test()->post("/{$f['company']->slug}/fuel/daily-close", $f['payload'])->assertSessionHasNoErrors()->assertSessionHas('success');
    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->firstOrFail();
    expect((float) $close->metadata['variance'])->toBe(0.0);
    expect(\App\Modules\FuelStation\Models\AmanatTransaction::where('company_id', $f['company']->id)->count())->toBe(1);
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'amanat_deposit')->count())->toBe(1);
});


test('ordinary posted invoice and bill amendments preserve old journals and reconcile only the delta', function (string $kind) {
    $f = closeWorkflowFixture();
    $receivable = $kind === 'invoice';
    $control = Account::create(['company_id' => $f['company']->id, 'code' => $receivable ? '1100' : '2000', 'name' => 'Control',
        'type' => $receivable ? 'asset' : 'liability', 'subtype' => $receivable ? 'accounts_receivable' : 'accounts_payable',
        'normal_balance' => $receivable ? 'debit' : 'credit', 'is_active' => true]);
    $f['company']->update([$receivable ? 'ar_account_id' : 'ap_account_id' => $control->id]);
    $partyClass = $receivable ? \App\Modules\Accounting\Models\Customer::class : \App\Modules\Accounting\Models\Vendor::class;
    $party = $partyClass::create(['company_id' => $f['company']->id, $receivable ? 'customer_number' : 'vendor_number' => 'PARTY-1',
        'name' => 'Document party', 'base_currency' => 'PKR', 'is_active' => true, $receivable ? 'ar_account_id' : 'ap_account_id' => $control->id]);
    $documentClass = $receivable ? \App\Modules\Accounting\Models\Invoice::class : \App\Modules\Accounting\Models\Bill::class;
    $document = $documentClass::create(['company_id' => $f['company']->id, $receivable ? 'customer_id' : 'vendor_id' => $party->id,
        $kind.'_number' => 'DOC-ONE', $kind.'_date' => '2026-09-15', 'due_date' => '2026-09-30', 'status' => $receivable ? 'sent' : 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'subtotal' => 100, 'total_amount' => 100, 'balance' => 100, 'base_amount' => 100]);
    $accountKey = $receivable ? 'income_account_id' : 'expense_account_id';
    $account = $f['accounts'][$receivable ? '4100' : '6180'];
    $document->lineItems()->create(['company_id' => $f['company']->id, 'line_number' => 1, 'description' => 'Original',
        'quantity' => 1, 'unit_price' => 100, 'line_total' => 100, 'total' => 100, $accountKey => $account->id]);
    $posting = app(PostingService::class);
    $original = $receivable ? $posting->postInvoice($document) : $posting->postBill($document);
    $document->update(['transaction_id' => $original->id]);
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']); $snapshot = $close->metadata;
    $params = ['id' => $document->id, 'customer' => $party->id, 'currency' => 'PKR', 'line_items' => [
        ['description' => 'Corrected', 'quantity' => 1, 'unit_price' => 200, $accountKey => $account->id],
    ]];
    $action = $receivable ? \App\Modules\Accounting\Actions\Invoice\UpdateAction::class : \App\Modules\Accounting\Actions\Bill\UpdateAction::class;
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app($action)->handle($params));
    expect((float) $document->fresh()->total_amount)->toBe(200.0);
    expect((float) $original->fresh()->total_debit)->toBe(100.0);
    expect($original->fresh()->reversed_by_id)->not->toBeNull();
    expect($document->fresh()->transaction_id)->not->toBe($original->id);
    expect((float) Transaction::findOrFail($document->fresh()->transaction_id)->total_debit)->toBe(200.0);
    $view = app(DailyCloseReconciliationService::class)->view($close->fresh());
    expect($view['has_post_close_activity'])->toBeTrue();
    expect($view['current']['account_effects'][$account->id])->toBe($receivable ? -200.0 : 10200.0);
    expect($close->fresh()->metadata)->toBe($snapshot);
    if (!$receivable) {
        $document->fresh()->lineItems()->firstOrFail()->update(['quantity_received' => 1]);
        expect(fn () => app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app($action)->handle($params)))
            ->toThrow(\InvalidArgumentException::class);
    }
})->with(['invoice', 'bill']);

test('a notes-only invoice or bill edit leaves the posted journal untouched', function (string $kind) {
    $f = closeWorkflowFixture();
    $receivable = $kind === 'invoice';
    $control = Account::create(['company_id' => $f['company']->id, 'code' => $receivable ? '1100' : '2000', 'name' => 'Control',
        'type' => $receivable ? 'asset' : 'liability', 'subtype' => $receivable ? 'accounts_receivable' : 'accounts_payable',
        'normal_balance' => $receivable ? 'debit' : 'credit', 'is_active' => true]);
    $f['company']->update([$receivable ? 'ar_account_id' : 'ap_account_id' => $control->id]);
    $partyClass = $receivable ? \App\Modules\Accounting\Models\Customer::class : \App\Modules\Accounting\Models\Vendor::class;
    $party = $partyClass::create(['company_id' => $f['company']->id, $receivable ? 'customer_number' : 'vendor_number' => 'PARTY-1',
        'name' => 'Document party', 'base_currency' => 'PKR', 'is_active' => true, $receivable ? 'ar_account_id' : 'ap_account_id' => $control->id]);
    $documentClass = $receivable ? \App\Modules\Accounting\Models\Invoice::class : \App\Modules\Accounting\Models\Bill::class;
    $document = $documentClass::create(['company_id' => $f['company']->id, $receivable ? 'customer_id' : 'vendor_id' => $party->id,
        $kind.'_number' => 'DOC-ONE', $kind.'_date' => '2026-09-15', 'due_date' => '2026-09-30', 'status' => $receivable ? 'sent' : 'received',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'subtotal' => 100, 'total_amount' => 100, 'balance' => 100, 'base_amount' => 100]);
    $accountKey = $receivable ? 'income_account_id' : 'expense_account_id';
    $account = $f['accounts'][$receivable ? '4100' : '6180'];
    $document->lineItems()->create(['company_id' => $f['company']->id, 'line_number' => 1, 'description' => 'Original',
        'quantity' => 1, 'unit_price' => 100, 'line_total' => 100, 'total' => 100, $accountKey => $account->id]);
    $posting = app(PostingService::class);
    $original = $receivable ? $posting->postInvoice($document) : $posting->postBill($document);
    $document->update(['transaction_id' => $original->id]);

    // Same customer/vendor, same currency, same date, same line amounts/accounts —
    // only the notes text differs. This must not reverse or renumber the journal.
    $params = ['id' => $document->id, 'customer' => $party->id, 'currency' => 'PKR', 'notes' => 'Just a memo, nothing financial changed', 'line_items' => [
        ['description' => 'Original', 'quantity' => 1, 'unit_price' => 100, $accountKey => $account->id],
    ]];
    $action = $receivable ? \App\Modules\Accounting\Actions\Invoice\UpdateAction::class : \App\Modules\Accounting\Actions\Bill\UpdateAction::class;
    app(\App\Services\CompanyContextService::class)->withContext($f['company'], fn () => app($action)->handle($params));

    expect($document->fresh()->notes)->toBe('Just a memo, nothing financial changed');
    expect($document->fresh()->transaction_id)->toBe($original->id);
    expect($original->fresh()->reversed_by_id)->toBeNull();
    expect(Transaction::where('company_id', $f['company']->id)->where('transaction_type', $kind)->count())->toBe(1);
})->with(['invoice', 'bill']);


test('a legacy close without a posting_snapshot remains amendable and reversible', function () {
    $f = closeWorkflowFixture();

    // A legacy close: posted directly (as pre-snapshot code did), with no
    // metadata['posting_snapshot'] key at all.
    $legacy = app(GlPostingService::class)->postBalancedTransaction([
        'company_id' => $f['company']->id, 'transaction_number' => 'FDC-LEGACY-1', 'transaction_type' => 'fuel_daily_close',
        'date' => '2026-09-15', 'currency' => 'PKR', 'metadata' => ['opening_cash' => 420000, 'closing_cash' => 410000],
    ], [
        ['account_id' => $f['accounts']['6180']->id, 'type' => 'debit', 'amount' => 10000],
        ['account_id' => $f['accounts']['1050']->id, 'type' => 'credit', 'amount' => 10000],
    ]);

    expect($legacy->isAmendable())->toBeTrue();

    $result = app(\App\Modules\FuelStation\Services\DailyCloseAmendmentService::class)->amendDailyClose(
        $legacy, $f['payload'], $f['user'], 'Corrected a typo in the original entry'
    );

    $reversal = Transaction::findOrFail($result['reversal_id']);
    expect($reversal->transaction_type)->toBe('fuel_daily_close_reversal');
    expect($legacy->fresh()->reversed_by_id)->toBe($reversal->id);
    expect(Transaction::findOrFail($result['correction_id'])->corrects_transaction_id)->toBe($legacy->id);
});

test('a snapshot close cannot be amended or reversed through the legacy amendment path', function () {
    $f = closeWorkflowFixture();
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);

    expect($close->isAmendable())->toBeFalse();
    expect(fn () => app(\App\Modules\FuelStation\Services\DailyCloseAmendmentService::class)->amendDailyClose(
        $close, $f['payload'], $f['user'], 'Attempted correction'
    ))->toThrow(\RuntimeException::class);
    expect(fn () => app(PostingService::class)->reverseTransaction($close, 'Attempted reversal', '2026-09-15'))->toThrow(\RuntimeException::class);
});

test('posted close journal cannot be altered or reversed through ordinary accounting paths', function () {
    $f = closeWorkflowFixture();
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    $close = Transaction::findOrFail($posted['transaction_id']);
    expect(fn () => app(PostingService::class)->reverseTransaction($close, 'Replay close', '2026-09-15'))->toThrow(\RuntimeException::class);
    $entry = $close->journalEntries()->firstOrFail();
    expect(fn () => DB::transaction(fn () => DB::table('acct.journal_entries')->where('id', $entry->id)->update(['description' => 'Changed'])))
        ->toThrow(\Illuminate\Database\QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('acct.transactions')->where('id', $close->id)->update(['status' => 'draft'])))
        ->toThrow(\Illuminate\Database\QueryException::class);
});


test('normal salary advance form posts once and appears as late activity for its advance date', function () {
    $f = closeWorkflowFixture(); enableCloseHttp($f);
    $employee = \App\Modules\Payroll\Models\Employee::create(['company_id' => $f['company']->id, 'employee_number' => 'EMP-ONE',
        'first_name' => 'Test', 'last_name' => 'Employee', 'hire_date' => '2026-01-01', 'employment_type' => 'full_time',
        'employment_status' => 'active', 'is_active' => true, 'base_salary' => 20000, 'currency' => 'PKR']);
    $posted = app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
    test()->post("/{$f['company']->slug}/salary-advances", ['employee_id' => $employee->id, 'advance_date' => '2026-09-15',
        'amount' => 10000, 'bank_account_id' => $f['accounts']['1050']->id, 'payment_method' => 'cash', 'reason' => 'Forgotten advance'])
        ->assertSessionHasNoErrors()->assertSessionHas('success');
    $view = app(DailyCloseReconciliationService::class)->view(Transaction::findOrFail($posted['transaction_id']));
    expect($view['current']['variance'])->toBe(0.0);
    expect($view['activity'])->toHaveCount(1);
    $advance = \App\Modules\Payroll\Models\SalaryAdvance::where('company_id', $f['company']->id)->sole();
    expect($advance->journal_entry_id)->not->toBeNull();
});
