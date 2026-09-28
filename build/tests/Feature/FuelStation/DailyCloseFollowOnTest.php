<?php

use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseFollowOnService;
use App\Modules\FuelStation\Services\DailyCloseReconciliationService;
use App\Modules\FuelStation\Services\DailyCloseReopenService;
use App\Modules\FuelStation\Services\DailyCloseService;

require_once __DIR__.'/CreditCloseFixtures.php';

/**
 * A mistyped closing count on one day shows as a false over/short on that day and the opposite
 * on the next. Editing the first day leaves the next one opening from the old count until it is
 * refreshed; the refresh re-posts it from the corrected figures, every other entry as it was.
 */
test('after an edit the next day is flagged and re-posted from the corrected closing cash', function () {
    $f = creditCloseFixture();
    app(\App\Services\CurrentCompany::class)->set($f['company']);
    $closes = app(DailyCloseService::class);
    $companyId = $f['company']->id;

    // 15th: 100 L at 300, 9,000 on card, 6,000 on credit: 15,000 cash on 10,000 -> 25,000.
    $first = Transaction::findOrFail($closes->processDailyClose($companyId, $f['payload'], $f['user'])['transaction_id']);

    // 16th: 50 L more, all cash: 25,000 + 15,000 = 40,000 counted.
    $reading = $f['payload']['nozzle_readings'][0];
    $second = ['date' => '2026-09-16', 'opening_cash' => 25000, 'closing_cash' => 40000, 'nozzle_readings' => [[
        ...$reading, 'opening_electronic' => 100, 'closing_electronic' => 150, 'liters_sold' => 50,
    ]]];
    $closes->processDailyClose($companyId, $second, $f['user']);

    // The 15th's count was really 24,000 (a short of 1,000 that day). Edit and re-post it.
    app(DailyCloseReopenService::class)->reopen($first, $f['user'], 'Count was 24,000');
    $draft = app(DailyCloseReconciliationService::class)->draft($companyId, '2026-09-15');
    $draft['closing_cash'] = 24000;
    unset($draft['intent']);
    $first = Transaction::findOrFail($closes->processDailyClose($companyId, $draft, $f['user'])['transaction_id']);

    $followOn = app(DailyCloseFollowOnService::class);
    $drift = $followOn->nextDayDrift($first);
    expect($drift['next_date'])->toBe('2026-09-16')
        ->and($drift['changes'])->toContain(['what' => 'Opening cash', 'was' => 25000.0, 'now' => 24000.0]);

    $followOn->refreshNextDay($first, $f['user']);

    $next = Transaction::where('company_id', $companyId)->where('transaction_type', 'fuel_daily_close')
        ->whereDate('transaction_date', '2026-09-16')->whereNull('deleted_at')->firstOrFail();
    // Opens from 24,000 now: expected 39,000 against 40,000 counted, so the 1,000 shows as over.
    expect((float) $next->metadata['opening_cash'])->toBe(24000.0)
        ->and((float) $next->metadata['variance'])->toBe(1000.0)
        ->and($followOn->nextDayDrift($first->fresh()))->toBeNull();
});
