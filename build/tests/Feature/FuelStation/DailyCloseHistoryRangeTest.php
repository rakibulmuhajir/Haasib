<?php

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
function historyCloseOn(array $f, string $date): void
{
    app(DailyCloseService::class)->processDailyClose(
        $f['company']->id,
        array_replace($f['payload'], ['date' => $date]),
        $f['user'],
    );
}

test('a close outside the default window is still reachable', function () {
    $f = closeWorkflowFixture();
    historyCloseOn($f, now()->subMonths(6)->toDateString());

    $service = app(DailyCloseService::class);

    // The default window is what the page asks for first, and this close is not in it.
    expect($service->getRecentCloses($f['company']->id, 30))->toBeEmpty();

    // Widening has to actually reach it, or the record is unreachable from the page.
    expect($service->getRecentCloses($f['company']->id, 365))->toHaveCount(1)
        ->and($service->getRecentCloses($f['company']->id, null))->toHaveCount(1);
});

test('the total ignores the window, so an empty range is not an empty company', function () {
    $f = closeWorkflowFixture();
    historyCloseOn($f, now()->subMonths(6)->toDateString());

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
    $f = closeWorkflowFixture();
    historyCloseOn($f, now()->subDays(3)->toDateString());
    historyCloseOn($f, now()->subMonths(6)->toDateString());

    $service = app(DailyCloseService::class);

    expect($service->getRecentCloses($f['company']->id, 30))->toHaveCount(1)
        ->and($service->getRecentCloses($f['company']->id, null))->toHaveCount(2)
        ->and($service->countCloses($f['company']->id))->toBe(2);
});
