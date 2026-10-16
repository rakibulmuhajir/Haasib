<?php

use App\Models\User;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Services\WithholdingRegisterService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/WithholdingFixtures.php';

/** A bill payment on $date at the filer rate, so the register has a line to count. */
function whtRegisterPay(array $f, float $amount, string $date, string $rateId, array $extra = [])
{
    return whtPayBill($f, whtBill($f, $amount, 'BILL-'.str()->random(6)), $amount, $rateId, ['payment_date' => $date, ...$extra]);
}

test('the register totals tax per section and side', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $services = whtRate($f['company'], 4, null, 'both', 'WHT-SVC');
    $goods = \App\Modules\Accounting\Models\TaxRate::create([
        'company_id' => $f['company']->id, 'jurisdiction_id' => \App\Modules\Accounting\Models\Jurisdiction::query()->value('id'),
        'code' => 'WHT-GDS', 'name' => '153(1)(a) Goods', 'rate' => 2, 'tax_type' => 'withholding', 'section' => '153(1)(a)',
        'applies_to' => 'payments_made', 'effective_from' => '2026-07-01',
    ]);
    whtRegisterPay($f, 100000, '2026-09-01', $services->id);
    whtRegisterPay($f, 50000, '2026-09-02', $services->id);
    whtRegisterPay($f, 100000, '2026-09-03', $goods->id);

    $report = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30');

    $bySection = collect($report['sections'])->keyBy('section');
    expect($bySection['153(1)(b)']['wht'])->toBe(6000.0)
        ->and($bySection['153(1)(b)']['gross'])->toBe(150000.0)
        ->and($bySection['153(1)(b)']['count'])->toBe(2)
        ->and($bySection['153(1)(a)']['wht'])->toBe(2000.0)
        ->and($report['totals'])->toBe(['gross' => 250000.0, 'wht' => 8000.0])
        ->and($report['rows'])->toHaveCount(3);
});

test('the period filter keeps only payments dated inside it', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    whtRegisterPay($f, 100000, '2026-08-20', $rate->id);
    whtRegisterPay($f, 100000, '2026-09-05', $rate->id);

    $august = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-08-01', '2026-08-31');
    $september = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30');

    expect($august['rows'])->toHaveCount(1)->and($august['rows'][0]['date'])->toBe('2026-08-20')
        ->and($september['rows'])->toHaveCount(1)->and($september['rows'][0]['date'])->toBe('2026-09-05');
});

test('a voided payment appears with its reversal and the totals net to zero', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $payment = whtRegisterPay($f, 100000, '2026-09-05', $rate->id);
    AccountingPeriod::create(['company_id' => $f['company']->id, 'fiscal_year_id' => $f['sepPeriod']->fiscal_year_id, 'name' => 'October', 'period_number' => 10, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31']);
    app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('bill_payment.void', ['id' => $payment->id], $f['user'], true));

    // September still shows the deduction; the reversal belongs to the day it was reversed.
    $september = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30');
    expect($september['rows'])->toHaveCount(1)->and($september['rows'][0]['kind'])->toBe('deduction')->and($september['totals']['wht'])->toBe(4000.0);

    $both = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-10-31');
    expect($both['rows'])->toHaveCount(2)
        ->and(collect($both['rows'])->pluck('kind')->all())->toBe(['deduction', 'reversal'])
        ->and($both['rows'][1]['wht'])->toBe(-4000.0)
        ->and($both['totals']['wht'])->toBe(0.0);
});

test('the register lists what customers deducted from us, apart from what we deducted', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    whtRegisterPay($f, 100000, '2026-09-05', $rate->id);
    $received = whtReceivedRegisterRow($f, $rate);

    $made = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30', 'made');
    $in = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30', 'received');
    $all = app(WithholdingRegisterService::class)->run($f['company']->id, $f['company']->slug, '2026-09-01', '2026-09-30');

    expect($made['rows'])->toHaveCount(1)->and($made['rows'][0]['side'])->toBe('made')
        ->and($in['rows'])->toHaveCount(1)->and($in['rows'][0]['side'])->toBe('received')->and($in['totals']['wht'])->toBe(1500.0)
        ->and($in['rows'][0]['party'])->toBe('Receipt Buyer')
        ->and($all['rows'])->toHaveCount(2);
});

/** A receipt with 1,500 withheld, written straight to the table: the register only reads it. */
function whtReceivedRegisterRow(array $f, $rate): \App\Modules\Accounting\Models\Payment
{
    $customer = \App\Modules\Accounting\Models\Customer::create([
        'company_id' => $f['company']->id, 'customer_number' => 'C-REG', 'name' => 'Receipt Buyer',
        'base_currency' => 'PKR', 'is_active' => true, 'tax_id' => '7654321-0', 'filer_status' => 'filer',
    ]);

    return \App\Modules\Accounting\Models\Payment::create([
        'company_id' => $f['company']->id, 'customer_id' => $customer->id, 'payment_number' => 'PAY-REG-1',
        'payment_date' => '2026-09-10', 'amount' => 50000, 'currency' => 'PKR', 'base_currency' => 'PKR', 'base_amount' => 50000,
        'payment_method' => 'bank_transfer', 'wht_rate_id' => $rate->id, 'wht_rate' => 3, 'wht_amount' => 1500, 'wht_section' => '153(1)(b)',
    ]);
}

test('the register page and CSV render for someone with the reports permission', function () {
    $f = billPaymentEditFixture();
    whtTax($f['company'], true);
    $rate = whtRate($f['company'], 4);
    $f['vendor']->update(['tax_id' => '1234567-8', 'filer_status' => 'filer']);
    whtRegisterPay($f, 100000, '2026-09-05', $rate->id);
    $query = ['from' => '2026-09-01', 'to' => '2026-09-30'];

    $this->actingAs($f['user'])->get("/{$f['company']->slug}/reports/withholding-tax?".http_build_query($query))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounting/reports/WithholdingRegister', false)
            ->where('taxOn', true)
            ->where('report.totals.wht', 4000)
            ->where('report.rows.0.party', 'Fuel Depot')
            ->where('report.rows.0.tax_id', '1234567-8')
            ->where('report.rows.0.section', '153(1)(b)'));

    $csv = $this->actingAs($f['user'])->get("/{$f['company']->slug}/reports/withholding-tax?".http_build_query($query + ['format' => 'csv']));
    $csv->assertOk();
    expect($csv->headers->get('content-type'))->toContain('text/csv')
        ->and($csv->streamedContent())->toContain('Fuel Depot')->toContain('4000.00')->toContain('1234567-8');
});

test('the register is refused without the reports permission', function () {
    $f = billPaymentEditFixture();
    $clerk = User::factory()->withoutTwoFactor()->create();
    addCompanyMemberRow($f['company'], $clerk, 'operations');

    $this->actingAs($clerk)->get("/{$f['company']->slug}/reports/withholding-tax")->assertForbidden();
});

test('the menu entry follows the Charge tax switch', function () {
    $f = billPaymentEditFixture();

    whtTax($f['company'], false);
    $this->actingAs($f['user'])->get("/{$f['company']->slug}/reports/withholding-tax")
        ->assertInertia(fn (Assert $page) => $page->where('auth.taxEnabled', false)->where('taxOn', false));

    whtTax($f['company'], true);
    $this->actingAs($f['user'])->get("/{$f['company']->slug}/reports/withholding-tax")
        ->assertInertia(fn (Assert $page) => $page->where('auth.taxEnabled', true)->where('taxOn', true));
});
