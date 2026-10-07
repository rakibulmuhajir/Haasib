<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\FuelHomeService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;

require_once __DIR__.'/PendingDeliveryFixtures.php';
require_once __DIR__.'/StockBooksFixtures.php';

/*
 * Fuel sold straight off the tanker never passes a pump, so no close has it. The profitability
 * report takes it from the bill (direct_quantity, and its share of the bill's cost) and the
 * direct-delivery invoice, and Home must not add it a second time.
 */
function offTankerFixture(): array
{
    test()->travelTo(\Carbon\Carbon::parse('2026-09-20 09:00:00'));
    $f = pendingDeliveryFixture();
    // The stock statement reads only tanks, which is where off-tanker litres are found.
    $f['tank']->update(['warehouse_type' => 'tank', 'capacity' => 20000]);

    // 100 L through the pump on 15 Sep.
    creditClosePost($f);

    // 16 Sep (no close): a 1,000 L bill at 240, 200 L of it sold straight off the tanker at 300.
    pendingDeliveryBill($f, '2026-09-16', 1000, 0.0, $f['tank']->id, 'BILL-DIRECT', 200);
    $result = app(CompanyContextService::class)->withContext($f['company'], fn () => app(CommandBus::class)->dispatch('invoice.create', [
        'customer' => $f['customer']->id,
        'currency' => 'PKR',
        'date' => '2026-09-16',
        'draft' => false,
        'send_immediately' => true,
        'is_direct_delivery' => true,
        'line_items' => [[
            'item_id' => $f['item']->id, 'description' => 'Petrol off the tanker', 'quantity' => 200, 'unit_price' => 300,
            'tax_rate' => 0, 'income_account_id' => $f['accounts']['4100']->id,
        ]],
    ], $f['user'], true));
    $f['invoice'] = Invoice::findOrFail($result['data']['id']);

    return $f;
}

function offTankerCloseFigures(array $f): array
{
    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->sole();

    return (array) ($close->metadata['fuel_sales']['petrol'] ?? []);
}

test('the report adds what went off the tanker to sold, revenue and cost', function () {
    $f = offTankerFixture();
    $close = offTankerCloseFigures($f);
    expect((float) $f['invoice']->total_amount)->toBe(60000.0);

    $r = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'petrol');
    $row = $r['productRows'][0];

    expect($row['direct_quantity'])->toBe(200.0)
        ->and($row['direct_revenue'])->toBe(60000.0)
        ->and($row['quantity'])->toBe((float) $close['liters'] + 200.0)
        ->and($row['revenue'])->toBe((float) $close['revenue'] + 60000.0)
        // The bill's share: 240,000 x 200 / 1,000.
        ->and(round($row['cogs'], 2))->toBe(round((float) $close['cogs'] + 48000.0, 2))
        ->and(round($row['gross_profit'], 2))->toBe(round($row['revenue'] - $row['cogs'], 2))
        ->and($row['purchased_quantity'])->toBe(1000.0)
        ->and($r['totals']['quantity'])->toBe($row['quantity'])
        ->and($r['totals']['revenue'])->toBe($row['revenue']);

    // The day it happened carries it too.
    $day = collect($r['periodRows'])->firstWhere('key', '2026-09-16');
    expect($day['quantity'])->toBe(200.0)
        ->and($day['revenue'])->toBe(60000.0)
        ->and(round($day['cogs'], 2))->toBe(48000.0)
        ->and(round($day['gross_profit'], 2))->toBe(12000.0)
        ->and(round($day['margin_per_unit'], 2))->toBe(60.0);
});

test('a product filter that excludes the fuel leaves its off-tanker sales out', function () {
    $f = offTankerFixture();

    $r = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'diesel');

    expect($r['productRows'])->toBe([])
        ->and($r['totals']['quantity'])->toBe(0);
});

test('home shows the report totals, not the off-tanker sales added twice', function () {
    $f = offTankerFixture();

    $report = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30');
    $home = app(FuelHomeService::class)->period($f['company'], '2026-09-01', '2026-09-30');

    expect($home['revenue'])->toBe((float) $report['totals']['revenue'])
        ->and($home['sales_total'])->toBe((float) $report['totals']['revenue'])
        ->and($home['cogs'])->toBe((float) $report['totals']['cogs'])
        ->and(round($home['gross_profit'], 2))->toBe(round((float) $report['totals']['gross_profit'], 2));

    $petrol = collect($home['products'])->firstWhere('name', 'Petrol');
    expect($petrol['direct_quantity'])->toBe(200.0)
        ->and($petrol['quantity'])->toBe((float) $report['productRows'][0]['quantity']);
});

test('value trails reconcile totals, products and periods to the actual report and reach saved prices', function () {
    $f = offTankerFixture();
    $service = app(ProductProfitabilityReportService::class);
    $plain = $service->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'petrol');
    $report = $service->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'petrol', null, true);
    $trail = $report['valueTrail'];

    expect($plain)->not->toHaveKey('valueTrail')
        ->and($report['totals'])->toBe($plain['totals'])
        ->and($report['productRows'])->toBe($plain['productRows'])
        ->and($trail['context'])->toBe($report['filters']);

    foreach (['revenue', 'cogs', 'quantity', 'purchased_quantity', 'gross_profit'] as $field) {
        foreach (['total' => $report['totals'], 'product:petrol' => $report['productRows'][0]] as $scope => $row) {
            $node = $trail['nodes'][$trail['roots'][$scope.':'.$field]];
            expect($node['value'])->toBe((float) $row[$field]);
            $values = array_map(fn ($id) => $trail['nodes'][$id]['value'], $node['children']);
            $actual = $field === 'gross_profit' ? $values[0] - $values[1] : array_sum($values);
            expect(round($actual, 2))->toBe(round($node['value'], 2));
        }
    }
    foreach ($report['periodRows'] as $row) {
        expect($trail['nodes'][$trail['roots']['period:'.$row['key'].':gross_profit']]['value'])->toBe((float) $row['gross_profit']);
    }
    $invoiceNode = collect($trail['nodes'])->first(fn ($node) => ($node['source']['id'] ?? null) === $f['invoice']->id && $node['label'] === 'Price used');
    expect($invoiceNode['value'])->toBe(300.0)
        ->and($invoiceNode['source']['date'])->toBe('2026-09-16');
    expect(collect($trail['nodes'])->contains(fn ($node) => $node['label'] === 'Closing meter'))->toBeTrue();

    $f['item']->update(['selling_price' => 999]);
    $historical = $service->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'petrol', null, true)['valueTrail'];
    expect(collect($historical['nodes'])->where('label', 'Price used')->pluck('value')->unique()->all())->toBe([300.0]);

    // Enabling explanations once must not make subsequent report consumers collect them.
    expect($service->run($f['company']->id, '2026-09-01', '2026-09-30'))->not->toHaveKey('valueTrail');
});

test('value trails keep filters and grouped periods and do not invent sources for empty results', function () {
    $f = offTankerFixture();
    $service = app(ProductProfitabilityReportService::class);
    $report = $service->run($f['company']->id, '2026-09-01', '2026-09-30', 'month', 'petrol', null, true);
    expect($report['valueTrail']['roots'])->toHaveKey('period:2026-09:gross_profit');
    $empty = $service->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'diesel', null, true);
    $trail = $empty['valueTrail'];
    expect($trail['nodes'][$trail['roots']['total:revenue']]['children'])->toBe([])
        ->and(collect($trail['nodes'])->filter(fn ($node) => $node['source'] !== null))->toHaveCount(0)
        ->and($trail['context']['product'])->toBe('diesel');
});

test('value trails show cost corrections once and keep their journal source', function () {
    $f = offTankerFixture();
    $close = Transaction::where('company_id', $f['company']->id)->where('transaction_type', 'fuel_daily_close')->sole();
    $correctionId = stockBooksEntry($f['company']->id, '2026-09-15', 'fuel_close_cost_fix', [
        [$f['accounts']['5100']->id, 1500, 'debit'], [$f['accounts']['1200']->id, 1500, 'credit'],
    ]);
    Transaction::whereKey($correctionId)->update(['reference_id' => $close->id, 'metadata' => [
        'close_id' => $close->id, 'lines' => [['fuel_category' => 'petrol', 'cogs_delta' => 1500]],
    ]]);
    $report = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'petrol', null, true);
    $trail = $report['valueTrail'];
    $correction = collect($trail['nodes'])->where('label', 'Posted cost correction')->sole();
    expect($correction['value'])->toBe(1500.0)->and($correction['source']['id'])->toBe($correctionId);
    $cost = collect($trail['nodes'])->first(fn ($node) => $node['label'] === 'Petrol · cost of sales');
    expect(round(array_sum(array_map(fn ($id) => $trail['nodes'][$id]['value'], $cost['children'])), 2))->toBe(round($cost['value'], 2));
});

test('the report lazily loads trails and refuses to collect them when the account preference is off', function () {
    $f = offTankerFixture();
    $f['company']->enableModule('fuel_station');
    $url = '/'.$f['company']->slug.'/fuel/reports/product-profitability?start_date=2026-09-01&end_date=2026-09-30&product=petrol';
    $this->actingAs($f['user'])->get($url)->assertOk()->assertInertia(fn ($page) => $page
        ->component('FuelStation/Reports/ProductProfitability')->missing('valueTrail'));
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => \Inertia\Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'FuelStation/Reports/ProductProfitability',
        'X-Inertia-Partial-Data' => 'valueTrail',
    ];
    $this->get($url, $headers)->assertOk()->assertJsonPath('props.valueTrail.context.product', 'petrol');

    $f['user']->update(['settings' => ['show_value_trails' => false]]);
    $this->actingAs($f['user']->fresh())->get($url, $headers)->assertOk()->assertJsonPath('props.valueTrail', null);
});

test('source references are withheld when a report reader cannot view the original documents', function () {
    $f = offTankerFixture();
    $report = app(ProductProfitabilityReportService::class)->run($f['company']->id, '2026-09-01', '2026-09-30', 'day', 'petrol', null, true);
    $reader = Mockery::mock(\App\Models\User::class)->makePartial();
    $reader->shouldReceive('isGodMode')->andReturn(false);
    $reader->shouldReceive('hasCompanyPermission')->andReturn(false);
    $trail = app(\App\Modules\FuelStation\Services\ProfitValueTrailPresenter::class)->present($report['valueTrail'], $f['company'], $reader);
    foreach ($trail['nodes'] as $node) {
        if ($node['source']) {
            expect($node['source'])->toBe(['restricted' => true]);
        }
    }
});
