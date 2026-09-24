<?php

use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\Inventory\Models\Item;

/**
 * When a close's readings were taken, and how many hours each close therefore covers.
 *
 * A close is labelled with its business date but read the morning after, normally at 08:00. On
 * a rate-change night a station reads at midnight instead: the day before the change covers 16
 * hours and the day of it 32. Totals are right either way - litres come from the meters - but
 * anything comparing days saw one weak day and one strong one with nothing to explain it. The
 * history now labels every close that is not 24 hours, both sides of the change, and leaves the
 * ordinary day alone.
 *
 * Nobody types the time. It is 08:00 the next morning, or 00:00 when a rate change takes effect
 * that day - the first minute of the new rate.
 *
 * Fixtures come from DailyCloseWorkflowTest.php, so run the directory:
 *   php artisan test tests/Feature/FuelStation
 */
function readingsClose(array $f, string $date): array
{
    $payload = array_replace($f['payload'], ['date' => $date]);

    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);
}

/** A rate for Petrol on each date given; the first is setup, every later one a change. */
function readingsRates(array $f, string ...$dates): void
{
    $item = Item::create(['company_id' => $f['company']->id, 'sku' => 'PETROL-RT', 'name' => 'Petrol', 'item_type' => 'product', 'unit_of_measure' => 'liter', 'currency' => 'PKR']);
    foreach ($dates as $i => $date) {
        RateChange::create([
            'company_id' => $f['company']->id,
            'item_id' => $item->id,
            'effective_date' => $date,
            'sale_rate' => 300 + $i,
            'purchase_rate' => 250,
        ]);
    }
}

function hoursByDate(array $f): array
{
    return collect(app(DailyCloseService::class)->getRecentCloses($f['company']->id, null))
        ->pluck('hours_covered', 'date')->all();
}

test('a close with no time given was read at 08:00 the next morning', function () {
    $f = closeWorkflowFixture();

    $posted = readingsClose($f, '2026-09-15');

    expect($posted['metadata']['readings_taken_at'])->toBe('2026-09-16 08:00');
});

test('a rate-change night labels both closes, and leaves the ordinary day alone', function () {
    $f = closeWorkflowFixture();

    readingsRates($f, '2026-09-01', '2026-09-16');          // the rate changes on 16 Sep

    readingsClose($f, '2026-09-14');                        // read 15 Sep 08:00
    readingsClose($f, '2026-09-15');                        // read 16 Sep 00:00, when the rate changed
    readingsClose($f, '2026-09-16');                        // read 17 Sep 08:00
    readingsClose($f, '2026-09-17');                        // read 18 Sep 08:00

    $hours = hoursByDate($f);

    expect($hours['2026-09-15'])->toBe(16.0)   // the day before the change, cut short
        ->and($hours['2026-09-16'])->toBe(32.0) // the day of it, running to the next morning
        ->and($hours['2026-09-17'])->toBe(24.0) // back to normal: no label on the page
        ->and($hours['2026-09-14'])->toBeNull(); // nothing before it to measure from
});

test('the night before a rate change is read at midnight, but not before an item\'s first rate', function () {
    $f = closeWorkflowFixture();
    readingsRates($f, '2026-09-16', '2026-09-18');

    expect(readingsClose($f, '2026-09-15')['metadata']['readings_taken_at'])->toBe('2026-09-16 08:00')
        ->and(readingsClose($f, '2026-09-17')['metadata']['readings_taken_at'])->toBe('2026-09-18 00:00');
});
