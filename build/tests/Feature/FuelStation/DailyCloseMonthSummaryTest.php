<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\DailyCloseMonthSummaryService;

/**
 * The month sheet adds up live closes only. An edited day leaves soft-deleted old versions
 * behind and a reversed close is no longer true; neither may be counted. Metadata is written by
 * hand here so each figure the sheet shows can be checked against an input.
 */
const MONTH_TANK = '11111111-1111-4111-8111-111111111111';
const MONTH_NOZZLE = '22222222-2222-4222-8222-222222222222';

/** The fiscal year and the month's period a close on $date must sit in (made once, reused). */
function monthPeriodFor(Company|string $company, string $date): array
{
    $companyId = is_string($company) ? $company : $company->id;
    $day = \Carbon\Carbon::parse($date);
    $fy = \App\Modules\Accounting\Models\FiscalYear::firstOrCreate(
        ['company_id' => $companyId, 'name' => '2026'],
        ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'status' => 'open']
    );
    $period = \App\Modules\Accounting\Models\AccountingPeriod::firstOrCreate(
        ['company_id' => $companyId, 'fiscal_year_id' => $fy->id, 'period_number' => $day->month],
        ['name' => $day->format('F'), 'start_date' => $day->copy()->startOfMonth()->toDateString(), 'end_date' => $day->copy()->endOfMonth()->toDateString()]
    );

    return ['fiscal_year_id' => $fy->id, 'period_id' => $period->id, 'base_currency' => 'PKR'];
}

function monthClose(Company $company, string $date, array $meta, string $suffix = ''): Transaction
{
    return Transaction::create([
        'company_id' => $company->id,
        ...monthPeriodFor($company, $date),
        'transaction_number' => 'DC-'.$date.$suffix.'-'.str()->random(4),
        'transaction_type' => 'fuel_daily_close',
        'transaction_date' => $date,
        'posting_date' => $date,
        'description' => 'Daily close '.$date,
        'currency' => 'PKR',
        'total_debit' => 0,
        'total_credit' => 0,
        'status' => 'posted',
        'metadata' => $meta,
    ]);
}

/** A posted close is immutable; the reopen bypass (scoped to this one row) lets a fixture shape it, as DailyCloseReopenTest does. */
function monthShapePostedClose(Transaction $close, callable $change): void
{
    \Illuminate\Support\Facades\DB::select("SELECT set_config('app.reopening_close_id', ?, false)", [$close->id]);
    try {
        $change($close);
    } finally {
        \Illuminate\Support\Facades\DB::select("SELECT set_config('app.reopening_close_id', '', false)");
    }
}

function monthMeta(float $opening, float $in, float $out, float $variance, float $dip, float $litres, float $revenue): array
{
    $closing = $opening + $in - $out + $variance;

    return [
        'total_revenue' => $revenue,
        'fuel_sales' => ['petrol' => ['liters' => $litres, 'revenue' => $revenue, 'cogs' => 0]],
        'posting_snapshot' => [
            'totals' => [
                'opening_cash' => $opening, 'money_in' => $in, 'money_out' => $out,
                'closing_cash' => $closing, 'expected_closing' => $closing - $variance,
                'variance' => $variance, 'total_revenue' => $revenue,
            ],
            'tanks' => [[
                'tank_id' => MONTH_TANK, 'tank_name' => 'Tank 1', 'expected_liters' => $dip - $variance,
                'physical_liters' => $dip, 'variance_liters' => $variance,
            ]],
            'nozzles' => [[
                'nozzle_id' => MONTH_NOZZLE, 'tank_id' => MONTH_TANK, 'liters_dispensed' => $litres,
            ]],
        ],
    ];
}

function monthFixture(): Company
{
    $user = User::factory()->create();
    $company = Company::create(['name' => 'Month sheet', 'slug' => 'month-'.str()->lower(str()->random(10)), 'owner_id' => $user->id, 'base_currency' => 'PKR']);
    enterCompany($company);

    return $company;
}

test('a month adds up its live closes and skips deleted and reversed ones', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-10-05'));
    $company = monthFixture();

    // Previous month: the tank ended it at 5000 L.
    monthClose($company, '2026-08-31', monthMeta(100000, 0, 0, 0, 5000, 0, 0));

    // Two live closes; day two opens with day one's count.
    monthClose($company, '2026-09-01', monthMeta(100000, 50000, 20000, -500, 4000, 1000, 50000));
    monthClose($company, '2026-09-02', monthMeta(129500, 60000, 10000, 300, 3200, 800, 60000));

    // An older version of 1 Sep, soft-deleted when the day was edited.
    monthShapePostedClose(monthClose($company, '2026-09-01', monthMeta(1, 999999, 1, 0, 1, 9999, 999999), 'old'), fn ($c) => $c->delete());

    // A reversed close.
    $live = Transaction::where('company_id', $company->id)->whereDate('transaction_date', '2026-09-02')->first();
    $reversed = monthClose($company, '2026-09-03', monthMeta(1, 888888, 1, 0, 1, 7777, 888888));
    monthShapePostedClose($reversed, function ($c) use ($live) {
        $c->reversed_by_id = $live->id;
        $c->save();
    });

    $s = app(DailyCloseMonthSummaryService::class)->run($company->id, '2026-09');

    expect($s['close_count'])->toBe(2)
        ->and($s['label'])->toBe('September 2026')
        ->and($s['prev_month'])->toBe('2026-08')
        ->and($s['next_month'])->toBe('2026-10')
        ->and(collect($s['closes'])->pluck('date')->all())->toBe(['2026-09-01', '2026-09-02']);

    $c = $s['cash'];
    expect($c['opening'])->toBe(100000.0)
        ->and($c['money_in'])->toBe(110000.0)
        ->and($c['money_out'])->toBe(30000.0)
        ->and($c['short_over'])->toBe(-200.0)
        ->and($c['short_days'])->toBe(1)
        ->and($c['over_days'])->toBe(1)
        ->and($c['closing'])->toBe($c['opening'] + $c['money_in'] - $c['money_out'] + $c['short_over']);

    // Per-fuel litres are summed across the two live days only.
    $petrol = collect($s['sales'])->firstWhere('label', 'Petrol');
    expect($petrol['detail'])->toBe('1,800 L')
        ->and($petrol['amount'])->toBe(110000.0)
        ->and($s['sales_total'])->toBe(110000.0);

    // The tank opens from the last close before the month, not from anything inside it.
    $tank = $s['tanks'][0];
    expect($tank['name'])->toBe('Tank 1')
        ->and($tank['opening'])->toBe(5000.0)
        ->and($tank['sold'])->toBe(1800.0)
        ->and($tank['closing'])->toBe(3200.0)
        // The row reads across (closing − expected); what the daily dips posted is kept beside it.
        ->and($tank['expected'])->toBe(3200.0)
        ->and($tank['variance'])->toBe(0.0)
        ->and($tank['daily_variance'])->toBe(-200.0);

    // The 3rd is not a counted close and today is past it, so it is reported missing.
    expect($s['missing_dates'])->toContain('2026-09-03')
        ->and($s['missing_dates'])->not->toContain('2026-09-01');
});

test('a month with no closes is empty', function () {
    $company = monthFixture();

    $s = app(DailyCloseMonthSummaryService::class)->run($company->id, '2026-03');

    expect($s['close_count'])->toBe(0)
        ->and($s['tanks'])->toBe([]);
});
