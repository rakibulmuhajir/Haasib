<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;

require_once __DIR__.'/PendingDeliveryFixtures.php';

/*
 * The purchase rate on a rate change is a reference only: stock cost comes from deliveries
 * (weighted average on receipt), so a rate change never overwrites avg_cost/cost_price or
 * posts a stock revaluation journal. See RateChangeService::createWithRevaluation.
 */
test('a rate change keeps the delivery-based cost and posts no revaluation', function () {
    test()->travelTo(\Carbon\Carbon::parse('2026-09-15 09:00:00'));
    $f = pendingDeliveryFixture();
    $f['item']->update(['avg_cost' => 240, 'cost_price' => 240, 'current_stock' => 5000, 'fuel_category' => 'petrol']);
    $journalsBefore = Transaction::where('company_id', $f['company']->id)->count();

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/rates", [
        'item_id' => $f['item']->id,
        'effective_date' => '2026-09-15',
        'purchase_rate' => 260,
        'sale_rate' => 280,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $item = $f['item']->fresh();
    expect((float) $item->avg_cost)->toBe(240.0)
        ->and((float) $item->cost_price)->toBe(240.0)
        ->and((float) $item->selling_price)->toBe(280.0)
        ->and(Transaction::where('company_id', $f['company']->id)->count())->toBe($journalsBefore)
        ->and(RateChange::where('company_id', $f['company']->id)->sole()->revaluation_amount)->toBeNull();
});
