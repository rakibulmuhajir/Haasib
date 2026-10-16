<?php

use App\Modules\Accounting\Models\FiscalYear;
use Carbon\Carbon;

test('a fiscal year inside one calendar year is named by that year', function () {
    expect(FiscalYear::labelFor(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')))->toBe('FY 2026');
});

test('a fiscal year spanning two calendar years is named by both', function () {
    expect(FiscalYear::labelFor(Carbon::parse('2026-07-01'), Carbon::parse('2027-06-30')))->toBe('FY 2026-2027');
});
