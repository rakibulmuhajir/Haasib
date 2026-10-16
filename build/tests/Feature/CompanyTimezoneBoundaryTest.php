<?php

use App\Modules\Accounting\Services\GeneralHomeFigures;
use App\Services\CompanyContextService;
use App\Support\CompanyClock;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Accounting/BillPaymentEditFixtures.php';

/*
 * The midnight boundary: 02:00 on 16 Oct in Karachi is still 15 Oct in UTC.
 *
 * Classification of the server-side "today" sites swept in this change (hit counts from the
 * plan's inventory grep, excluding migrations):
 *   (a) calendar date, now through CompanyClock: document-date defaults, before_or_equal rules,
 *       report as_of / from / to defaults, "this month" ranges, aging buckets and overdue checks,
 *       payroll paid-on / month defaults, stock-movement dates, daily-close business dates.
 *   (b) real instants, left alone: created_at, sent_at, posted_at, voided_at, locked_at,
 *       applied_at, approved_at, token expiry, audit and folded_at stamps.
 */
beforeEach(function () {
    Carbon::setTestNow('2026-10-15 21:00:00 UTC');
});

afterEach(fn () => Carbon::setTestNow());

function karachiCompanyFixture(): array
{
    $f = billPaymentEditFixture();
    $f['company']->forceFill(['timezone' => 'Asia/Karachi'])->save();

    return $f;
}

function billPaymentBody(array $f, string $date): array
{
    return [
        'vendor_id' => $f['vendor']->id,
        'payment_date' => $date,
        'amount' => 100,
        'currency' => 'PKR',
        'base_currency' => 'PKR',
        'payment_method' => 'bank_transfer',
        'payment_account_id' => $f['bank']->id,
    ];
}

test('a payment dated the Karachi date passes validation at 02:00 local', function () {
    $f = karachiCompanyFixture();

    $this->actingAs($f['user'])
        ->post("/{$f['company']->slug}/bill-payments", billPaymentBody($f, '2026-10-16'))
        ->assertSessionDoesntHaveErrors('payment_date');
});

test('a payment dated tomorrow in Karachi is still rejected', function () {
    $f = karachiCompanyFixture();

    $this->actingAs($f['user'])
        ->post("/{$f['company']->slug}/bill-payments", billPaymentBody($f, '2026-10-17'))
        ->assertSessionHasErrors('payment_date');
});

test('a UTC company still rejects the Karachi date as the future', function () {
    $f = billPaymentEditFixture();

    $this->actingAs($f['user'])
        ->post("/{$f['company']->slug}/bill-payments", billPaymentBody($f, '2026-10-16'))
        ->assertSessionHasErrors('payment_date');
});

test('the trial balance defaults to the Karachi date', function () {
    $f = karachiCompanyFixture();

    $this->actingAs($f['user'])
        ->get("/{$f['company']->slug}/reports/trial-balance")
        ->assertInertia(fn (Assert $page) => $page->where('filters.as_of', '2026-10-16'));
});

test('this month on the home figures covers October for a Karachi company', function () {
    $f = karachiCompanyFixture();
    $company = $f['company'];

    $period = \App\Modules\Accounting\Models\AccountingPeriod::create([
        'company_id' => $company->id, 'fiscal_year_id' => $f['sepPeriod']->fiscal_year_id, 'name' => 'October',
        'period_number' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
    ]);
    $sales = \App\Modules\Accounting\Models\Account::create([
        'company_id' => $company->id, 'code' => '4000', 'name' => 'Sales', 'type' => 'revenue', 'subtype' => 'sales',
        'normal_balance' => 'credit', 'is_active' => true,
    ]);
    $txId = (string) str()->uuid();
    DB::table('acct.transactions')->insert([
        'id' => $txId, 'company_id' => $company->id, 'transaction_number' => 'INV-TZ-1',
        'fiscal_year_id' => $period->fiscal_year_id, 'period_id' => $period->id,
        'transaction_type' => 'invoice', 'transaction_date' => '2026-10-02', 'posting_date' => '2026-10-02', 'description' => 'tz',
        'currency' => 'PKR', 'base_currency' => 'PKR', 'total_debit' => 500, 'total_credit' => 500, 'status' => 'posted',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([[$f['bank'], 500, 0], [$sales, 0, 500]] as $i => [$account, $debit, $credit]) {
        DB::table('acct.journal_entries')->insert([
            'id' => (string) str()->uuid(), 'company_id' => $company->id, 'transaction_id' => $txId, 'account_id' => $account->id,
            'line_number' => $i + 1, 'debit_amount' => $debit, 'credit_amount' => $credit,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $today = app(CompanyClock::class)->now($company);
    $figures = app(CompanyContextService::class)->withContext(
        $company,
        fn () => app(GeneralHomeFigures::class)->for($company, $today)
    );

    // At 02:00 on 16 Oct local, the UTC date is 15 Oct but October is still the month.
    expect($today->toDateString())->toBe('2026-10-16');
    expect($figures['revenue_mtd'])->toBe(500.0);
});
