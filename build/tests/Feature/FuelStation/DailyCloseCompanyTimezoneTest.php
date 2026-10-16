<?php

use Carbon\Carbon;

require_once __DIR__.'/DailyCloseWorkflowFixtures.php';

/*
 * The morning ritual runs on the station's own clock: before noon the close page offers
 * yesterday (the dip entered this morning closes yesterday), from noon it offers today.
 * "Before noon" and "yesterday" are Karachi's, not UTC's.
 */
afterEach(fn () => Carbon::setTestNow());

function closePageDate(array $f): string
{
    return test()->get("/{$f['company']->slug}/fuel/daily-close")->assertOk()->viewData('page')['props']['date'];
}

test('02:00 in Karachi offers yesterday, the morning dip closing the day before', function () {
    $f = closeWorkflowFixture();
    $f['company']->forceFill(['timezone' => 'Asia/Karachi'])->save();
    enableCloseHttp($f);

    Carbon::setTestNow('2026-10-15 21:00:00 UTC'); // 02:00 on 16 Oct in Karachi

    expect(closePageDate($f))->toBe('2026-10-15');
});

test('13:00 in Karachi offers today even though it is morning in UTC', function () {
    $f = closeWorkflowFixture();
    $f['company']->forceFill(['timezone' => 'Asia/Karachi'])->save();
    enableCloseHttp($f);

    Carbon::setTestNow('2026-10-15 08:00:00 UTC'); // 13:00 on 15 Oct in Karachi

    expect(closePageDate($f))->toBe('2026-10-15');
});

test('the same instant is still morning for a UTC company', function () {
    $f = closeWorkflowFixture();
    enableCloseHttp($f);

    Carbon::setTestNow('2026-10-15 08:00:00 UTC');

    expect(closePageDate($f))->toBe('2026-10-14');
});
