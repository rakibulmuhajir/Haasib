<?php

use App\Modules\FuelStation\Services\DailyCloseService;
use Illuminate\Validation\ValidationException;

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
 * Fixtures come from DailyCloseWorkflowTest.php, so run the directory:
 *   php artisan test tests/Feature/FuelStation
 */
function readingsClose(array $f, string $date, ?string $takenAt = null): array
{
    $payload = array_replace($f['payload'], ['date' => $date]);
    if ($takenAt !== null) {
        $payload['readings_taken_at'] = $takenAt;
    }

    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $payload, $f['user']);
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

    readingsClose($f, '2026-09-14');                        // read 15 Sep 08:00
    readingsClose($f, '2026-09-15', '2026-09-16T00:00');    // rate changes at midnight: read then
    readingsClose($f, '2026-09-16');                        // read 17 Sep 08:00
    readingsClose($f, '2026-09-17');                        // read 18 Sep 08:00

    $hours = hoursByDate($f);

    expect($hours['2026-09-15'])->toBe(16.0)   // the day before the change, cut short
        ->and($hours['2026-09-16'])->toBe(32.0) // the day of it, running to the next morning
        ->and($hours['2026-09-17'])->toBe(24.0) // back to normal: no label on the page
        ->and($hours['2026-09-14'])->toBeNull(); // nothing before it to measure from
});

test('a readings time that cannot belong to the business date is refused', function () {
    $f = closeWorkflowFixture();

    // Five days out: a time typed against the wrong day, not a late reading.
    expect(fn () => readingsClose($f, '2026-09-15', '2026-09-20T08:00'))
        ->toThrow(ValidationException::class, 'Check the date');
});
