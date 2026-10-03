<?php

use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseService;

require_once __DIR__.'/PendingDeliveryFixtures.php';

/*
 * A delivery added on its own (Fuel deliveries > Add delivery) is a bill whose litres are not
 * received yet: that day's close lists it as delivered and receives it when it is posted. A day
 * already closed is refused.
 */
test('a delivery added on its own is a bill that waits for its day\'s close', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $f['company']->enableModule('fuel_station');
    $f['tank']->update(['warehouse_type' => 'tank', 'capacity' => 20000]);

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/receipts", [
        'date' => '2026-09-15',
        'supplier_id' => $f['vendor']->id,
        'item_id' => $f['item']->id,
        'tank_id' => $f['tank']->id,
        'quantity' => 6000,
        'unit_cost' => 260,
        'paid_now' => false,
    ])->assertSessionHasNoErrors()->assertSessionHas('success');

    $bill = Bill::where('company_id', $f['company']->id)->with('lineItems')->sole();
    expect($bill->bill_date->toDateString())->toBe('2026-09-15')
        ->and((float) $bill->lineItems->sole()->quantity)->toBe(6000.0)
        ->and((float) $bill->lineItems->sole()->quantity_received)->toBe(0.0);

    // The day's close sees it as delivered, not yet received.
    $pending = app(DailyCloseService::class)->pendingDeliveries($f['company']->id, $f['tank']->id, $f['item']->id, null, '2026-09-15');
    expect(collect($pending)->sum('remaining'))->toBe(6000.0);
});

test('a delivery dated on a closed day is refused', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $f['company']->enableModule('fuel_station');
    $f['tank']->update(['warehouse_type' => 'tank', 'capacity' => 20000]);
    Transaction::create([
        'company_id' => $f['company']->id, ...fixtureYearAndPeriod($f['company']->id), 'transaction_number' => 'DC-2026-09-14-'.str()->random(4),
        'transaction_type' => 'fuel_daily_close', 'transaction_date' => '2026-09-14', 'posting_date' => '2026-09-14',
        'description' => 'Daily close 2026-09-14', 'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => 0, 'total_credit' => 0,
        'status' => 'posted', 'metadata' => [],
    ]);

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/receipts", [
        'date' => '2026-09-14', 'supplier_id' => $f['vendor']->id, 'item_id' => $f['item']->id,
        'tank_id' => $f['tank']->id, 'quantity' => 1000, 'unit_cost' => 260,
    ])->assertSessionHasErrors(['date']);

    expect(Bill::where('company_id', $f['company']->id)->count())->toBe(0);
});
