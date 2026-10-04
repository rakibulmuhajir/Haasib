<?php

use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Models\CalculatorFormula;
use App\Modules\FuelStation\Services\Calculator\CalculatorContext;
use App\Modules\FuelStation\Services\Calculator\FormulaEvaluator;
use App\Modules\FuelStation\Services\Calculator\WhenResolver;
use App\Modules\FuelStation\Services\CustomerPeriodSummaryService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Modules\FuelStation\Services\StockStatementService;
use App\Modules\Inventory\Models\Item;
use App\Services\CompanyContextService;
use App\Services\CompanyRbacBootstrapper;
use App\Services\CurrentCompany;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/CreditCloseFixtures.php';
require_once __DIR__.'/CustomerFuelDiscountFixtures.php';

const CALC_NONE = ['type' => 'none'];

function calcNum(float $v): array
{
    return ['type' => 'number', 'value' => $v];
}

function calcOp(string $op, array $left, array $right): array
{
    return ['type' => 'op', 'op' => $op, 'left' => $left, 'right' => $right];
}

function calcValue(string $metric, array $collection, array $when): array
{
    return ['type' => 'value', 'metric' => $metric, 'collection' => $collection, 'when' => $when];
}

function calcEval(array $ast, ?string $companyId = null, ?Carbon $today = null): array
{
    return app(FormulaEvaluator::class)->evaluate(new CalculatorContext($companyId ?? 'none', 'slug'), $ast, $today);
}

/** A posted September close: 100 L of Diesel at 300, 9,000 on a card machine, 6,000 on credit. */
function calcClosedFixture(): array
{
    $f = discountedCustomerFixture();
    $f['payload']['nozzle_readings'] = [[
        'nozzle_id' => $f['dieselNozzle']->id, 'item_id' => $f['diesel']->id,
        'opening_electronic' => 0, 'closing_electronic' => 100, 'liters_sold' => 100, 'sale_rate' => 300,
    ]];
    creditClosePost($f);

    return $f;
}

/** An owner of a fuel station company, plus (optionally) a second member of it. */
function calcPageFixture(bool $withSecondMember = false): array
{
    $user = User::factory()->withoutTwoFactor()->create();
    $other = $withSecondMember ? User::factory()->withoutTwoFactor()->create() : null;
    $company = Company::create([
        'name' => 'Calculator Station',
        'slug' => 'calculator-'.str()->lower(str()->random(8)),
        'owner_id' => $user->id,
        'base_currency' => 'PKR',
    ]);

    DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);
    DB::select("SELECT set_config('app.is_super_admin', 'true', false)");
    app(CompanyRbacBootstrapper::class)->bootstrap($company);
    foreach (array_filter([$user, $other]) as $member) {
        DB::table('auth.company_user')->insert([
            'company_id' => $company->id, 'user_id' => $member->id, 'role' => 'owner',
            'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(CompanyContextService::class)->withContext($company, fn () => app(CompanyContextService::class)->assignRole($member, 'owner'));
    }
    DB::select("SELECT set_config('app.is_super_admin', 'false', false)");
    enterCompany($company);
    $company->enableModule('fuel_station');

    return compact('company', 'user', 'other');
}

// ---- the arithmetic ---------------------------------------------------------------------------

test('the calculator adds, subtracts, multiplies and divides, and brackets change the order', function () {
    expect(calcEval(calcOp('+', calcNum(2), calcNum(3)))['result'])->toBe(5.0)
        ->and(calcEval(calcOp('-', calcNum(2), calcNum(3)))['result'])->toBe(-1.0)
        ->and(calcEval(calcOp('*', calcNum(6), calcNum(7)))['result'])->toBe(42.0)
        ->and(calcEval(calcOp('/', calcNum(9), calcNum(4)))['result'])->toBe(2.25);

    // 2 + 3 x 4 = 14, (2 + 3) x 4 = 20: the tree, not the order typed, decides.
    $plain = calcOp('+', calcNum(2), calcOp('*', calcNum(3), calcNum(4)));
    $bracketed = calcOp('*', ['type' => 'group', 'inner' => calcOp('+', calcNum(2), calcNum(3))], calcNum(4));
    expect(calcEval($plain)['result'])->toBe(14.0)
        ->and(calcEval($bracketed)['result'])->toBe(20.0);
});

test('dividing by zero gives no result and says so', function () {
    $out = calcEval(calcOp('+', calcNum(1), calcOp('/', calcNum(5), calcOp('-', calcNum(2), calcNum(2)))));

    expect($out['result'])->toBeNull()->and($out['message'])->toBe('Division by zero');
});

test('units follow the arithmetic', function () {
    expect(FormulaEvaluator::unit('/', 'Rs', 'L'))->toBe('Rs/L')
        ->and(FormulaEvaluator::unit('*', 'Rs/L', 'L'))->toBe('Rs')
        ->and(FormulaEvaluator::unit('*', 'L', 'Rs/L'))->toBe('Rs')
        ->and(FormulaEvaluator::unit('/', 'Rs', 'Rs/L'))->toBe('L')
        ->and(FormulaEvaluator::unit('/', 'Rs', 'Rs'))->toBeNull()
        ->and(FormulaEvaluator::unit('+', 'Rs', 'Rs'))->toBe('Rs')
        ->and(FormulaEvaluator::unit('*', 'Rs', null))->toBe('Rs');
});

test('a value is the same figure its report shows, and units carry through a formula', function () {
    $f = calcClosedFixture();
    $cid = $f['company']->id;
    $month = ['preset' => 'last_month'];
    $range = ['from' => '2026-09-01', 'to' => '2026-09-30'];
    $item = ['type' => 'product', 'id' => $f['diesel']->id];

    $report = app(ProductProfitabilityReportService::class);
    $key = $report->keyForItem($cid, $f['diesel']->id);
    $row = collect($report->run($cid, '2026-09-01', '2026-09-30')['productRows'])->firstWhere('key', $key);
    expect($row)->not->toBeNull()->and($row['revenue'])->toBeGreaterThan(0);

    $sales = calcEval(calcValue('sales', $item, $range), $cid);
    expect($sales['result'])->toBe((float) $row['revenue'])
        ->and($sales['unit'])->toBe('Rs')
        ->and($sales['parts'][0]['source_href'])->toContain('/fuel/reports/product-profitability')
        ->and($sales['parts'][0]['source_href'])->toContain('start_date=2026-09-01')
        ->and($sales['parts'][0]['label'])->toStartWith('Sales · Diesel');

    // Rs / L is Rs/L, and Rs/L x L comes back to Rs.
    $rate = calcOp('/', calcValue('sales', $item, $range), calcValue('litres_sold', $item, $range));
    $out = calcEval($rate, $cid);
    expect($out['unit'])->toBe('Rs/L')
        ->and($out['result'])->toBe(round($row['revenue'] / $row['quantity'], 4))
        ->and($out['parts'])->toHaveCount(2);
    expect(calcEval(calcOp('*', $rate, calcValue('litres_sold', $item, $range)), $cid)['unit'])->toBe('Rs');

    // Mixing Rs and L with plus still adds, with a warning.
    $mixed = calcEval(calcOp('+', calcValue('sales', $item, $range), calcValue('litres_sold', $item, $range)), $cid);
    expect($mixed['result'])->toBe(round($row['revenue'] + $row['quantity'], 4))
        ->and($mixed['warnings'])->toHaveCount(1)
        ->and($mixed['warnings'][0])->toContain('Rs')->toContain('L');
});

test('stock on a day equals the stock statement closing, and a customer matches the customer page', function () {
    $f = calcClosedFixture();
    $cid = $f['company']->id;
    $item = ['type' => 'product', 'id' => $f['diesel']->id];

    $stock = app(StockStatementService::class)->run($cid, $f['diesel']->id, '2026-09-15', '2026-09-15');
    $qty = calcEval(calcValue('stock_qty', $item, ['on' => '2026-09-15']), $cid);
    expect($qty['result'])->toBe($stock['totals']['closing'] === null ? null : round((float) $stock['totals']['closing'], 4));

    $bought = calcEval(calcValue('purchases_amount', $item, ['from' => '2026-09-01', 'to' => '2026-09-30']), $cid);
    expect($bought['result'])->toBe(round((float) app(StockStatementService::class)->run($cid, $f['diesel']->id, '2026-09-01', '2026-09-30')['totals']['purchase_amount'], 4))
        ->and($bought['parts'][0]['source_href'])->toContain('/fuel/reports/stock-statement?item='.$f['diesel']->id);

    $customer = ['type' => 'customer', 'id' => $f['customer']->id];
    $summary = app(CustomerPeriodSummaryService::class)->run($cid, $f['customer']->id, '2026-09-30', '2026-09-30', $f['company']->slug);
    $owed = calcEval(calcValue('customer_owed', $customer, ['on' => '2026-09-30']), $cid);
    expect($owed['result'])->toBe((float) $summary['money']['closing']);
    expect(calcEval(calcValue('customer_owed', ['type' => 'customer', 'id' => (string) str()->uuid()], ['on' => '2026-09-30']), $cid)['result'])->toBeNull();
});

test('card swipes add up what the closes recorded, per channel and in all', function () {
    $f = calcClosedFixture();
    $cid = $f['company']->id;
    $range = ['from' => '2026-09-01', 'to' => '2026-09-30'];

    expect(calcEval(calcValue('card_swipes', ['type' => 'channel', 'id' => 'pos'], $range), $cid)['result'])->toBe(9000.0)
        ->and(calcEval(calcValue('card_swipes', CALC_NONE, $range), $cid)['result'])->toBe(9000.0)
        ->and(calcEval(calcValue('card_swipes', ['type' => 'channel', 'id' => 'pos'], ['from' => '2026-08-01', 'to' => '2026-08-31']), $cid)['result'])->toBe(0.0);
});

test('relative periods are worked out from today on every run', function () {
    $today = Carbon::parse('2026-10-04');

    expect(WhenResolver::range(['preset' => 'last_month'], $today))->toBe(['2026-09-01', '2026-09-30', 'last month'])
        ->and(WhenResolver::range(['preset' => 'this_month'], $today))->toBe(['2026-10-01', '2026-10-04', 'this month'])
        ->and(WhenResolver::range(['preset' => 'this_year'], $today))->toBe(['2026-01-01', '2026-10-04', 'this year'])
        ->and(WhenResolver::range(['preset' => 'yesterday'], $today))->toBe(['2026-10-03', '2026-10-03', 'yesterday'])
        ->and(WhenResolver::range(['preset' => 'last_n_days', 'n' => 7], $today))->toBe(['2026-09-28', '2026-10-04', 'last 7 days'])
        // March 31st: last month is February, not "March 3rd minus a month".
        ->and(WhenResolver::range(['preset' => 'last_month'], Carbon::parse('2026-03-31')))->toBe(['2026-02-01', '2026-02-28', 'last month'])
        ->and(WhenResolver::day(['preset' => 'month_end_last'], $today))->toBe(['2026-09-30', 'last month end'])
        ->and(WhenResolver::day(['on' => '2026-09-15'], $today)[0])->toBe('2026-09-15');

    $f = calcClosedFixture();
    $sales = calcValue('sales', ['type' => 'all_products'], ['preset' => 'last_month']);
    $run = calcEval($sales, $f['company']->id, Carbon::parse('2026-10-04'));
    expect($run['parts'][0]['from'])->toBe('2026-09-01')->and($run['parts'][0]['to'])->toBe('2026-09-30')
        ->and($run['result'])->toBeGreaterThan(0);
    // Next month the same saved formula reads October, which has no closes yet.
    expect(calcEval($sales, $f['company']->id, Carbon::parse('2026-11-04'))['result'])->toBe(0.0);
});

// ---- the endpoint, saving and visibility ------------------------------------------------------

test('the calculator page renders with its metrics, options, saved list and an example', function () {
    $f = calcPageFixture();

    test()->actingAs($f['user'])
        ->get("/{$f['company']->slug}/fuel/calculator")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Calculator/Index')
            ->where('result', null)
            ->has('metrics', fn (Assert $metrics) => $metrics->etc())
            ->has('options.products')
            ->has('options.expense_accounts')
            ->has('options.customers')
            ->has('options.channels')
            ->has('saved', 0)
            ->where('example.type', 'op'));
});

test('evaluate answers with the same page and a result, and refuses a formula it cannot read', function () {
    $f = calcPageFixture();
    $slug = $f['company']->slug;

    test()->actingAs($f['user'])
        ->post("/{$slug}/fuel/calculator/evaluate", ['formula' => calcOp('*', calcNum(6), calcNum(7))])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('FuelStation/Calculator/Index')
            ->where('result.result', fn ($v) => (float) $v === 42.0)
            ->where('result.parts', []));

    // A string to eval, an unknown metric, a metric for a collection it cannot take: all refused.
    foreach ([
        ['formula' => ['type' => 'eval', 'code' => '1+1']],
        ['formula' => calcValue('nonsense', CALC_NONE, ['preset' => 'today'])],
        ['formula' => calcValue('net_profit', ['type' => 'product', 'id' => (string) str()->uuid()], ['preset' => 'today'])],
        ['formula' => calcValue('stock_qty', ['type' => 'product', 'id' => (string) str()->uuid()], ['preset' => 'this_month'])],
        ['formula' => calcOp('/', calcNum(1), ['type' => 'op', 'op' => '%', 'left' => calcNum(1), 'right' => calcNum(2)])],
    ] as $bad) {
        test()->actingAs($f['user'])->post("/{$slug}/fuel/calculator/evaluate", $bad)->assertSessionHasErrors('formula');
    }
});

test('a formula is limited to 25 values', function () {
    $f = calcPageFixture();
    $value = calcValue('net_profit', CALC_NONE, ['preset' => 'this_month']);
    $sum = fn (int $n) => array_reduce(range(2, $n), fn ($acc) => calcOp('+', $acc, $value), $value);

    test()->actingAs($f['user'])->post("/{$f['company']->slug}/fuel/calculator/evaluate", ['formula' => $sum(26)])
        ->assertSessionHasErrors('formula');
    expect(app(FormulaEvaluator::class)->problem($sum(25)))->toBeNull();
});

test('a personal formula is private, a shared one is seen by the company, and only its owner can change or delete it', function () {
    $f = calcPageFixture(true);
    $slug = $f['company']->slug;
    $formula = calcOp('+', calcNum(1), calcNum(2));

    test()->actingAs($f['user'])->post("/{$slug}/fuel/calculator/formulas", ['name' => 'My own', 'formula' => $formula])->assertRedirect();
    test()->actingAs($f['user'])->post("/{$slug}/fuel/calculator/formulas", ['name' => 'For everyone', 'formula' => $formula, 'is_shared' => true])->assertRedirect();

    $mine = CalculatorFormula::where('name', 'My own')->sole();
    $shared = CalculatorFormula::where('name', 'For everyone')->sole();
    expect($mine->is_shared)->toBeFalse()->and($shared->is_shared)->toBeTrue()->and($mine->user_id)->toBe($f['user']->id);

    // The owner sees both; the other member sees the shared one only.
    test()->actingAs($f['user'])->get("/{$slug}/fuel/calculator")
        ->assertInertia(fn (Assert $page) => $page->has('saved', 2));
    test()->actingAs($f['other'])->get("/{$slug}/fuel/calculator")
        ->assertInertia(fn (Assert $page) => $page
            ->has('saved', 1)
            ->where('saved.0.name', 'For everyone')
            ->where('saved.0.mine', false)
            ->where('saved.0.is_shared', true));

    // They can neither change nor delete the shared one, and cannot even reach the personal one.
    test()->actingAs($f['other'])->put("/{$slug}/fuel/calculator/formulas/{$shared->id}", ['name' => 'Hijacked', 'formula' => $formula])->assertForbidden();
    test()->actingAs($f['other'])->delete("/{$slug}/fuel/calculator/formulas/{$shared->id}")->assertForbidden();
    test()->actingAs($f['other'])->delete("/{$slug}/fuel/calculator/formulas/{$mine->id}")->assertNotFound();
    expect(CalculatorFormula::count())->toBe(2)->and($shared->fresh()->name)->toBe('For everyone');

    // The owner can rename, stop sharing and delete.
    test()->actingAs($f['user'])->put("/{$slug}/fuel/calculator/formulas/{$shared->id}", ['name' => 'Renamed', 'formula' => $formula, 'is_shared' => false])->assertRedirect();
    expect($shared->fresh()->name)->toBe('Renamed')->and($shared->fresh()->is_shared)->toBeFalse();
    test()->actingAs($f['other'])->get("/{$slug}/fuel/calculator")->assertInertia(fn (Assert $page) => $page->has('saved', 0));

    test()->actingAs($f['user'])->delete("/{$slug}/fuel/calculator/formulas/{$mine->id}")->assertRedirect();
    expect(CalculatorFormula::count())->toBe(1);
});
