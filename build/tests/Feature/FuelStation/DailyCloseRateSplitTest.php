<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Services\DailyCloseService;

/**
 * A rate change that lands inside a business day, which is the Pakistani reality.
 *
 * Rates take effect at midnight; the register signs off at 8am. So a change falls in the
 * middle of a day, and the litres either side of it have to be priced differently. The close
 * does this from a meter snapshot taken at the moment of the change - not from a timestamp -
 * so it does not matter whether the reading was taken at 00:00, 00:15, or during a queue.
 * Every litre before that meter value is old rate, every litre after it is new.
 *
 * This path had never run. Across the whole fourteen-day scenario every rate change fell on
 * a day boundary, so the day went at one rate and no split was needed:
 * `rate_change_segments` is empty on all fourteen closes and every fuel.rate_changes row has
 * a null snapshot. The code was written, shipped, and never exercised by either the
 * service-level scenario or the manual browser run.
 *
 * Nothing about the rate-change logic is changed here. These tests only exercise it.
 *
 * Fixtures come from DailyCloseCreditSalesTest.php, so run the directory:
 *   php artisan test tests/Feature/FuelStation
 */
function rateChangeWithSnapshot(array $f, float $oldRate, float $newRate, float $snapshotMeter): RateChange
{
    $itemId = $f['payload']['nozzle_readings'][0]['item_id'];
    $nozzleId = $f['payload']['nozzle_readings'][0]['nozzle_id'];

    // The previous rate is read from the most recent earlier row for the same item. Without
    // it the snapshot has no old rate to price against and the split declines to run.
    RateChange::create([
        'company_id' => $f['company']->id,
        'item_id' => $itemId,
        'effective_date' => '2026-09-14',
        'sale_rate' => $oldRate,
    ]);

    return RateChange::create([
        'company_id' => $f['company']->id,
        'item_id' => $itemId,
        'effective_date' => '2026-09-15',
        'sale_rate' => $newRate,
        'snapshot_nozzle_readings' => [
            ['nozzle_id' => $nozzleId, 'electronic_reading' => $snapshotMeter],
        ],
    ]);
}

/**
 * The base fixture sells 100 L at 300. Here the meter runs 0 → 100 with the rate changing at
 * 40 litres, so 40 are owed at the old rate and 60 at the new one.
 */
function postSplitClose(array $f, float $newRate, float $expectedRevenue): array
{
    $f['payload']['payment_receipts'] = [];
    $f['payload']['credit_sales'] = [];
    $f['payload']['nozzle_readings'][0]['sale_rate'] = $newRate;

    // Counted cash is stated per test rather than inherited, so each one says out loud what
    // it expects the drawer to hold. Opening 10,000, everything sold for cash.
    $f['payload']['closing_cash'] = 10000 + $expectedRevenue;

    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
}

test('litres either side of the change are priced at their own rate', function () {
    $f = creditCloseFixture();
    rateChangeWithSnapshot($f, oldRate: 300, newRate: 320, snapshotMeter: 40);

    // 40 x 300 = 12,000, then 60 x 320 = 19,200. Priced at one rate it would be 32,000.
    $posted = postSplitClose($f, 320, 31200);

    expect((float) $posted['metadata']['total_revenue'])->toBe(31200.0);
});

test('the close records which litres went at which rate', function () {
    $f = creditCloseFixture();
    rateChangeWithSnapshot($f, oldRate: 300, newRate: 320, snapshotMeter: 40);

    $segments = postSplitClose($f, 320, 31200)['metadata']['rate_change_segments'];

    expect($segments)->toHaveCount(1);

    $segment = $segments[0];

    expect((float) $segment['old_rate'])->toBe(300.0)
        ->and((float) $segment['new_rate'])->toBe(320.0)
        ->and((float) $segment['old_rate_liters'])->toBe(40.0)
        ->and((float) $segment['new_rate_liters'])->toBe(60.0)
        // Nothing was priced on a guess: every litre fell on a known side of the change.
        ->and((float) $segment['fallback_liters'])->toBe(0.0);
});

test('the split is taken from the meter, not the clock', function () {
    $f = creditCloseFixture();

    // Whatever time the reading was actually taken, the meter says where the boundary is.
    rateChangeWithSnapshot($f, oldRate: 300, newRate: 320, snapshotMeter: 75);

    $posted = postSplitClose($f, 320, 30500);

    // 75 x 300 = 22,500, then 25 x 320 = 8,000.
    expect((float) $posted['metadata']['total_revenue'])->toBe(30500.0);
});

test('the revenue posted to the ledger is the split figure', function () {
    $f = creditCloseFixture();
    rateChangeWithSnapshot($f, oldRate: 300, newRate: 320, snapshotMeter: 40);

    $posted = postSplitClose($f, 320, 31200);
    $entries = Transaction::findOrFail($posted['transaction_id'])->journalEntries;

    // The books have to carry the same number the close reported, or the split is cosmetic.
    expect((float) $entries->where('account_id', $f['accounts']['4100']->id)->sum('credit_amount'))->toBe(31200.0)
        ->and((float) $entries->sum('debit_amount'))->toBe((float) $entries->sum('credit_amount'));
});

test('without a snapshot the whole day goes at one rate, and says how much did', function () {
    $f = creditCloseFixture();
    $itemId = $f['payload']['nozzle_readings'][0]['item_id'];

    // A rate change with no reading taken at the moment it happened - the common failure on
    // a real forecourt, where nobody was sent out at midnight.
    RateChange::create([
        'company_id' => $f['company']->id,
        'item_id' => $itemId,
        'effective_date' => '2026-09-15',
        'sale_rate' => 320,
    ]);

    $posted = postSplitClose($f, 320, 32000);

    // Everything at the closing rate, and no segment claiming a split that did not happen.
    expect((float) $posted['metadata']['total_revenue'])->toBe(32000.0)
        ->and($posted['metadata']['rate_change_segments'])->toBe([]);
});

test('a snapshot outside the day is ignored rather than trusted', function () {
    $f = creditCloseFixture();

    // A meter reading beyond the closing reading cannot be a boundary inside this day. The
    // close must decline to split rather than produce a negative or invented segment.
    rateChangeWithSnapshot($f, oldRate: 300, newRate: 320, snapshotMeter: 250);

    $posted = postSplitClose($f, 320, 32000);

    expect((float) $posted['metadata']['total_revenue'])->toBe(32000.0)
        ->and($posted['metadata']['rate_change_segments'])->toBe([]);
});

test('the history row says a rate changed, and whether anything was guessed', function () {
    $f = creditCloseFixture();
    rateChangeWithSnapshot($f, oldRate: 300, newRate: 320, snapshotMeter: 40);
    postSplitClose($f, 320, 31200);

    $row = app(DailyCloseService::class)->getRecentCloses($f['company']->id, null)[0];

    expect($row['rate_change'])->not->toBeNull()
        ->and($row['rate_change']['changed'])->toBeTrue()
        ->and($row['rate_change']['fallback_liters'])->toBe(0.0);
});
