<?php

use App\Support\Palette\Commands\DateParser;

/** Reads the date off the end of a line, as the matcher does, and resolves it. */
function dayOf(string $line, string $today = '2026-10-16'): array
{
    $found = DateParser::extract($line);

    return $found['date'] === null
        ? ['ok' => false, 'message' => 'no date']
        : DateParser::resolve($found['date'], $today);
}

test('a typed date is booked on the right day', function (string $line, string $expected) {
    expect(dayOf($line))->toBe(['ok' => true, 'date' => $expected]);
})->with([
    ['deposit 100 yesterday', '2026-10-15'],
    ['deposit 100 today', '2026-10-16'],
    ['deposit 100 tomorrow', '2026-10-17'],
    ['deposit 100 on 14 oct', '2026-10-14'],
    ['deposit 100 on 14 Oct 2026', '2026-10-14'],
    ['deposit 100 on 14 october 2025', '2025-10-14'],
    ['deposit 100 on 14/10', '2026-10-14'],
    ['deposit 100 on 14/10/2026', '2026-10-14'],
    ['deposit 100 on 14-10-2026', '2026-10-14'],
    ['deposit 100 on 14-10-26', '2026-10-14'],
    ['deposit 100 on 2026-10-14', '2026-10-14'],
    ['deposit 100 on 5 sept', '2026-09-05'],
    ['deposit 100 on 29 feb 2028', '2028-02-29'],
    ['deposit 100 ON 1/2', '2026-02-01'],
]);

test('relative words count from the company date, across month and year ends', function () {
    expect(dayOf('deposit 100 yesterday', '2026-11-01')['date'])->toBe('2026-10-31')
        ->and(dayOf('deposit 100 yesterday', '2027-01-01')['date'])->toBe('2026-12-31')
        ->and(dayOf('deposit 100 tomorrow', '2026-12-31')['date'])->toBe('2027-01-01')
        ->and(dayOf('deposit 100 tomorrow', '2028-02-28')['date'])->toBe('2028-02-29');
});

test('a year-less date uses the company year, unless that is months ahead', function () {
    expect(dayOf('deposit 100 on 28 dec', '2027-01-03')['date'])->toBe('2026-12-28')
        ->and(dayOf('deposit 100 on 28 dec', '2026-10-16')['date'])->toBe('2026-12-28')
        ->and(dayOf('deposit 100 on 5 jan', '2026-12-30')['date'])->toBe('2026-01-05');
});

test('a day that does not exist is refused', function () {
    expect(dayOf('deposit 100 on 31/02'))->toBe(['ok' => false, 'message' => '31/02 is not a date'])
        ->and(dayOf('deposit 100 on 29 feb 2027')['ok'])->toBeFalse()
        ->and(dayOf('deposit 100 on 14/13')['ok'])->toBeFalse()
        ->and(DateParser::resolve('0/1/2026', '2026-10-16'))->toBe(['ok' => false, 'message' => '0/1/2026 is not a date']);
});

test('resolve takes the phrase with or without a leading on', function () {
    expect(DateParser::resolve('on 14 oct', '2026-10-16'))->toBe(['ok' => true, 'date' => '2026-10-14'])
        ->and(DateParser::resolve('14 oct', '2026-10-16'))->toBe(['ok' => true, 'date' => '2026-10-14'])
        ->and(DateParser::resolve('soonish', '2026-10-16'))->toBe(['ok' => false, 'message' => 'soonish is not a date']);
});

test('extract takes the date off the end and leaves the rest', function () {
    expect(DateParser::extract('deposit 5000 meezan yesterday'))->toBe(['line' => 'deposit 5000 meezan', 'date' => 'yesterday'])
        ->and(DateParser::extract('paid 8000 to karachi office on 14 oct'))->toBe(['line' => 'paid 8000 to karachi office', 'date' => '14 oct'])
        ->and(DateParser::extract('sale 500 walk-in on 3/4'))->toBe(['line' => 'sale 500 walk-in', 'date' => '3/4'])
        ->and(DateParser::extract('deposit 5000 meezan'))->toBe(['line' => 'deposit 5000 meezan', 'date' => null]);
});

test('other words are not mistaken for a date', function () {
    expect(DateParser::extract('expense 250 on account')['date'])->toBeNull()
        ->and(DateParser::extract('expense 250 tea on 3 tea')['date'])->toBeNull()
        ->and(DateParser::extract('expense 250 tea on 3 oct 202')['date'])->toBeNull();
});
