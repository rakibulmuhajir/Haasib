<?php

use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\FuelStation\Services\FuelHomeService;
use App\Modules\FuelStation\Services\ProductProfitabilityReportService;
use App\Services\CommandBus;
use App\Services\CompanyContextService;

require_once __DIR__.'/PendingDeliveryFixtures.php';

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
