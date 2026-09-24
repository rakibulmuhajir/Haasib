<?php

use App\Modules\FuelStation\Services\DailyCloseService;
use Illuminate\Validation\ValidationException;

/**
 * What a close does with meter readings: where its litres come from, what it refuses, and the
 * one legitimate case of a meter reading lower than it did yesterday.
 *
 * Three faults, all found in the run-up to live data:
 *
 *  - A closing reading below the opening one was accepted and booked as zero litres. Day 13 of
 *    the scenario run lost 375 litres of diesel that way, with only a cash surplus to show for it.
 *    Refused since 22 September, and that refusal had no test until this file.
 *
 *  - The litres themselves came from the request. The close posted fuel revenue from a figure
 *    the browser computed and never checked against the meters.
 *
 *  - Once backwards readings were refused, a totaliser genuinely rolling past its last digit
 *    could not be closed at all.
 *
 * Fixtures come from DailyCloseCreditSalesTest.php, so run the directory:
 *   php artisan test tests/Feature/FuelStation
 */
function meterClose(array $f, array $reading, float $expectedRevenue): array
{
    $f['payload']['payment_receipts'] = [];
    $f['payload']['credit_sales'] = [];
    $f['payload']['nozzle_readings'][0] = array_replace($f['payload']['nozzle_readings'][0], $reading);
    // Opening 10,000, everything sold for cash; each test states the revenue it expects.
    $f['payload']['closing_cash'] = 10000 + $expectedRevenue;

    return app(DailyCloseService::class)->processDailyClose($f['company']->id, $f['payload'], $f['user']);
}

test('a closing reading below the opening one is refused', function () {
    $f = creditCloseFixture();

    // Exactly day 13: the closing reading left at zero against an opening of 303,625.
    expect(fn () => meterClose($f, [
        'opening_electronic' => 303625,
        'closing_electronic' => 0,
        'liters_sold' => 0,
    ], 0))->toThrow(ValidationException::class, 'cannot go backwards');
});

test('the refusal names both readings and the way out', function () {
    $f = creditCloseFixture();

    try {
        meterClose($f, ['opening_electronic' => 303625, 'closing_electronic' => 0, 'liters_sold' => 0], 0);
        $this->fail('The backwards reading was accepted.');
    } catch (ValidationException $e) {
        $message = $e->errors()['nozzle_readings.0.closing_electronic'][0] ?? '';

        expect($message)->toContain('303,625')
            ->and($message)->toContain('Meter rolled over');
    }
});

test('litres come from the meters, not from the request', function () {
    $f = creditCloseFixture();

    // The meters say 100 litres; the request claims 999. The meters win.
    $posted = meterClose($f, [
        'opening_electronic' => 0,
        'closing_electronic' => 100,
        'liters_sold' => 999,
    ], 30000);

    expect((float) $posted['metadata']['total_revenue'])->toBe(30000.0);
});

test('a declared rollover is closed with the litres either side of zero', function () {
    $f = creditCloseFixture();

    // A 7-digit totaliser at 9,999,900 passes 9,999,999 and reads 100: 200 litres.
    $posted = meterClose($f, [
        'opening_electronic' => 9999900,
        'closing_electronic' => 100,
        'meter_rolled_over' => true,
        'liters_sold' => 0,
    ], 60000);

    expect((float) $posted['metadata']['total_revenue'])->toBe(60000.0);
});

test('a rollover cannot be claimed for a meter nowhere near its top', function () {
    $f = creditCloseFixture();

    // 6,611,600 on a 7-digit meter has 3.4 million litres to go. A tick here is a mistake
    // being explained away, and it must not buy the reading a pass.
    expect(fn () => meterClose($f, [
        'opening_electronic' => 6611600,
        'closing_electronic' => 100,
        'meter_rolled_over' => true,
    ], 0))->toThrow(ValidationException::class, 'nowhere near');
});

test('a rollover tick on a reading that went up is refused, not multiplied', function () {
    $f = creditCloseFixture();

    // Honouring the tick here would add a whole meter's range to the day's litres.
    expect(fn () => meterClose($f, [
        'opening_electronic' => 0,
        'closing_electronic' => 100,
        'meter_rolled_over' => true,
    ], 0))->toThrow(ValidationException::class, 'not below the opening');
});
