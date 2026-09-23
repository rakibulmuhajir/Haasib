<?php

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\FuelStation\Services\DailyCloseService;

/**
 * The history page's date window has to be reachable, and an empty window has to be
 * distinguishable from an empty company.
 *
 * It was hardcoded to thirty days. The manual E2E posted five closes back-dated to March and
 * the page reported "No daily close records found", under a button offering to create the
 * first one, while all five sat in the table — correctly posted, and confirmed by the next
 * day's close, which found the previous one without trouble.
 *
 * The tell was already in this repo: both existing history tests pass 365 rather than taking
 * the default, because their fixtures are back-dated too. The window was being worked around
 * in tests instead of being made a parameter.
 *
 * Fixtures come from DailyCloseWorkflowTest.php, so run the directory:
 *   php artisan test tests/Feature/FuelStation
 */
/**
 * "Today" for every test here. The window counts back from the clock, so reading the real one
 * made these tests depend on the day they ran - and fail outright in any month the fixture
 * had not opened an accounting period for.
 */
const HISTORY_TODAY = '2026-09-28';

/** Well outside a 30-day window from HISTORY_TODAY, in a different accounting period. */
const HISTORY_OLD_DATE = '2026-06-15';

/** Inside a 30-day window from HISTORY_TODAY, in the fixture's own September period. */
const HISTORY_RECENT_DATE = '2026-09-25';

/**
 * Post a zero-sales close on a date, opening its accounting period first if the fixture did not.
 * closeWorkflowFixture opens September 2026 only, and the ledger refuses to post outside an
 * open period.
 */
function historyCloseOn(array $f, string $date): void
{
    $day = \Carbon\Carbon::parse($date);

    $hasPeriod = AccountingPeriod::where('company_id', $f['company']->id)
        ->whereDate('start_date', '<=', $day)
        ->whereDate('end_date', '>=', $day)
        ->exists();

    if (! $hasPeriod) {
        AccountingPeriod::create([
            'company_id' => $f['company']->id,
            'fiscal_year_id' => FiscalYear::where('company_id', $f['company']->id)->value('id'),
            'name' => $day->format('F'),
            'period_number' => $day->month,
            'start_date' => $day->copy()->startOfMonth()->toDateString(),
            'end_date' => $day->copy()->endOfMonth()->toDateString(),
        ]);
    }

    app(DailyCloseService::class)->processDailyClose(
        $f['company']->id,
        array_replace($f['payload'], ['date' => $date]),
        $f['user'],
    );
}

test('a close outside the default window is still reachable', function () {
    $this->travelTo(\Carbon\Carbon::parse(HISTORY_TODAY));
    $f = closeWorkflowFixture();
    historyCloseOn($f, HISTORY_OLD_DATE);

    $service = app(DailyCloseService::class);

    // The default window is what the page asks for first, and this close is not in it.
    expect($service->getRecentCloses($f['company']->id, 30))->toBeEmpty();

    // Widening has to actually reach it, or the record is unreachable from the page.
    expect($service->getRecentCloses($f['company']->id, 365))->toHaveCount(1)
        ->and($service->getRecentCloses($f['company']->id, null))->toHaveCount(1);
});

test('the total ignores the window, so an empty range is not an empty company', function () {
    $this->travelTo(\Carbon\Carbon::parse(HISTORY_TODAY));
    $f = closeWorkflowFixture();
    historyCloseOn($f, HISTORY_OLD_DATE);

    // This is the pair the page compares: nothing in range, but something on record. Without
    // the second number there is no way to tell that apart from a company that has never
    // closed a day, and the page said the wrong one.
    expect(app(DailyCloseService::class)->getRecentCloses($f['company']->id, 30))->toBeEmpty()
        ->and(app(DailyCloseService::class)->countCloses($f['company']->id))->toBe(1);
});

test('a company that has never closed a day reports nothing on record', function () {
    $f = closeWorkflowFixture();

    // The genuine empty state, which must keep saying so — this is the one case where
    // "create your first daily close" is the right thing to offer.
    expect(app(DailyCloseService::class)->countCloses($f['company']->id))->toBe(0);
});

test('the window counts back from today, and keeps what falls inside it', function () {
    $this->travelTo(\Carbon\Carbon::parse(HISTORY_TODAY));
    $f = closeWorkflowFixture();
    historyCloseOn($f, HISTORY_OLD_DATE);
    historyCloseOn($f, HISTORY_RECENT_DATE);

    $service = app(DailyCloseService::class);

    expect($service->getRecentCloses($f['company']->id, 30))->toHaveCount(1)
        ->and($service->getRecentCloses($f['company']->id, null))->toHaveCount(2)
        ->and($service->countCloses($f['company']->id))->toBe(2);
});
