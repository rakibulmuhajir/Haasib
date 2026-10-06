<?php

use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Services\OpeningStockService;

require_once __DIR__.'/../FuelStation/PendingDeliveryFixtures.php';

/*
 * Changing an item's opening stock moves its stock on hand by the difference: the stock level
 * follows a movement only when one is inserted, so an edited opening figure must do it itself.
 */
test('editing an opening stock figure moves stock on hand by the difference', function () {
    $f = pendingDeliveryFixture();
    $cid = $f['company']->id;
    $level = fn () => (float) StockLevel::where('company_id', $cid)->where('item_id', $f['item']->id)->where('warehouse_id', $f['tank']->id)->value('quantity');
    $before = $level();
    $service = app(OpeningStockService::class);

    $service->recordMovement($cid, $f['tank']->id, $f['item']->id, '2026-08-31', 186, 780, $f['user']->id, 'Opening stock (shelf)');
    expect($level())->toBe($before + 186);

    // The same opening entry (item, warehouse, date, note) corrected to 6: on hand drops by 180.
    $service->recordMovement($cid, $f['tank']->id, $f['item']->id, '2026-08-31', 6, 780, $f['user']->id, 'Opening stock (shelf)');
    expect($level())->toBe($before + 6);
});
