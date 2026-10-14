<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseEntryService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\FuelStation\Services\TemporaryFuelStockService;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/PendingDeliveryFixtures.php';

test('temporary fuel carries quantity and cost into a later direct sale without touching tanks', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-20 10:00:00'));
    $f = pendingDeliveryFixture();
    $f['company']->enableModule('fuel_station');
    $f['tank']->update(['warehouse_type' => 'tank', 'capacity' => 20000]);
    $f['item']->update(['income_account_id' => $f['accounts']['4100']->id]);
    $stock = app(TemporaryFuelStockService::class);
    app(CompanyContextService::class)->withContext($f['company'], fn () => DB::transaction(fn () => app(DailyCloseEntryService::class)->purchase(
        $f['company']->id, '2026-09-16', ['supplier_id' => $f['vendor']->id, 'lines' => [[
            'item_id' => $f['item']->id, 'quantity' => 1000, 'unit_cost' => 240, 'temporary_storage' => true,
        ]]], $f['user'], false)));
    expect($stock->balance($f['company']->id, $f['item']->id))->toBe(['quantity' => 1000.0, 'value' => 240000.0]);
    expect((float) $f['item']->fresh()->avg_cost)->toBe(250.0);
    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/sales/direct", [
        'customer_id' => $f['customer']->id, 'item_id' => $f['item']->id, 'quantity' => 200, 'unit_price' => 300,
        'sale_date' => '2026-09-17', 'paid_in_cash' => false, 'temporary_storage' => true,
    ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
    expect($stock->balance($f['company']->id, $f['item']->id))->toBe(['quantity' => 800.0, 'value' => 192000.0])
        ->and(DB::table('inv.stock_movements')->where('warehouse_id', $f['tank']->id)->sum('quantity'))->toEqual(5000);
    $cost = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_temporary_cogs')->sole();
    expect($cost->transaction_date->toDateString())->toBe('2026-09-17')->and((float) $cost->total_debit)->toBe(48000.0);
    $report = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-16', '2026-09-17', 'day', 'petrol', includeTrail: true);
    expect((float) $report['totals']['revenue'])->toBe(60000.0)->and((float) $report['totals']['cogs'])->toBe(48000.0)
        ->and((float) $report['totals']['quantity'])->toBe(200.0)
        ->and((float) $report['productRows'][0]['book_profit'])->toBe(12000.0);
    $before = Invoice::where('company_id', $f['company']->id)->count();
    test()->post("/{$f['company']->slug}/fuel/sales/direct", [
        'customer_id' => $f['customer']->id, 'item_id' => $f['item']->id, 'quantity' => 801, 'unit_price' => 300,
        'sale_date' => '2026-09-18', 'paid_in_cash' => false, 'temporary_storage' => true,
    ])->assertRedirect()->assertSessionHas('error');
    expect(Invoice::where('company_id', $f['company']->id)->count())->toBe($before);
    $invoice = Invoice::where('company_id', $f['company']->id)->sole();
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(\App\Services\CommandBus::class)->dispatch('invoice.void', [
        'id' => $invoice->id, 'reason' => 'Wrong sale', 'reversal_date' => '2026-09-18',
    ], $f['user']));
    expect($stock->balance($f['company']->id, $f['item']->id))->toBe(['quantity' => 1000.0, 'value' => 240000.0]);
    app(CompanyContextService::class)->withContext($f['company'], fn () => DB::transaction(fn () => app(DailyCloseEntryService::class)->purchase(
        $f['company']->id, '2026-09-19', ['supplier_id' => $f['vendor']->id, 'lines' => [[
            'item_id' => $f['item']->id, 'tank_id' => $f['tank']->id, 'quantity' => 1000, 'unit_cost' => 200,
        ]]], $f['user'], true)));
    expect((float) $f['item']->fresh()->avg_cost)->toBe(241.6667);
});

test('amanat fuel settles meter revenue once and money withdrawals remain cash movements', function () {
    $f = pendingDeliveryFixture();
    $f['accounts']['2200'] = \App\Modules\Accounting\Models\Account::create([
        'company_id' => $f['company']->id, 'code' => '2200', 'name' => 'Amanat deposits',
        'type' => 'liability', 'subtype' => 'other_current_liability', 'normal_balance' => 'credit', 'is_active' => true,
    ]);
    $profile = \App\Modules\FuelStation\Models\CustomerProfile::getOrCreateForCustomer($f['company']->id, $f['customer']->id);
    $profile->update(['is_amanat_holder' => true, 'amanat_balance' => 20000]);
    $f['payload']['credit_sales'] = [];
    $f['payload']['closing_cash'] = 24000;
    $f['payload']['amanat_disbursements'] = [
        ['customer_id' => $f['customer']->id, 'kind' => 'fuel_purchase', 'fuel_item_id' => $f['item']->id, 'fuel_quantity' => 20, 'fuel_rate' => 300, 'amount' => 6000],
        ['customer_id' => $f['customer']->id, 'kind' => 'withdrawal', 'amount' => 1000],
    ];
    creditClosePost($f);
    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->sole();
    expect((float) $profile->fresh()->amanat_balance)->toBe(13000.0)
        ->and($close->metadata['amanat_fuel_purchases'])->toEqual(6000)
        ->and($close->metadata['amanat_disbursements'])->toEqual(1000)
        ->and((float) $close->total_debit)->toBe((float) $close->total_credit);
    expect(\App\Modules\FuelStation\Models\AmanatTransaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_purchase')->sole()->fuel_quantity)->toBe('20.00');
    expect($close->metadata['expected_closing'])->toEqual(24000);
    app(\App\Modules\FuelStation\Services\DailyCloseReopenService::class)->reopen($close, $f['user'], 'Correct the withdrawal');
    expect((float) $profile->fresh()->amanat_balance)->toBe(20000.0);
});

test('the delivery form receives temporary fuel immediately and rejects direct quantity on it', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-20 10:00:00'));
    $f = pendingDeliveryFixture();
    $f['company']->enableModule('fuel_station');
    $data = ['date' => '2026-09-16', 'supplier_id' => $f['vendor']->id, 'item_id' => $f['item']->id,
        'quantity' => 1000, 'unit_cost' => 240, 'temporary_storage' => true];
    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/receipts", [...$data, 'direct_quantity' => 200])
        ->assertSessionHasErrors('direct_quantity');
    test()->post("/{$f['company']->slug}/fuel/receipts", $data)->assertSessionHasNoErrors()->assertSessionHas('success');
    expect((float) \App\Modules\Accounting\Models\Bill::where('company_id', $f['company']->id)->sole()->lineItems()->sole()->quantity_received)->toBe(1000.0);
});

test('an unfinished amanat fuel withdrawal can be parked but cannot be posted', function () {
    $row = ['kind' => 'fuel_purchase', 'fuel_quantity' => 0, 'fuel_rate' => 0, 'amount' => 0];
    $rules = ['amanat_disbursements' => ['array', new \App\Modules\FuelStation\Http\Requests\Rules\RequiresCompleteAmanatFuelRows]];
    expect(\Illuminate\Support\Facades\Validator::make(['intent' => 'park', 'amanat_disbursements' => [$row]], $rules)->passes())->toBeTrue()
        ->and(\Illuminate\Support\Facades\Validator::make(['intent' => 'post', 'amanat_disbursements' => [$row]], $rules)->fails())->toBeTrue();
});
