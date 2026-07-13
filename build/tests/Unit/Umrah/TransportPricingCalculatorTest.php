<?php

use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Services\TransportPricingCalculator;

test('specialized transport removes the included bus cost from visa cost', function () {
    $result = app(TransportPricingCalculator::class)->replaceIncludedBusCost(1200, 50, 4, true);

    expect($result)->toBe([
        'deduction' => 200.0,
        'adjusted_visa_cost' => 1000.0,
    ]);
});

test('standard bus leaves the vendor visa cost unchanged', function () {
    $result = app(TransportPricingCalculator::class)->replaceIncludedBusCost(1200, 50, 4, false);

    expect($result)->toBe([
        'deduction' => 0.0,
        'adjusted_visa_cost' => 1200.0,
    ]);
});

test('per vehicle fare applies hajj terminal surcharge to each vehicle', function () {
    $fare = new TransportFare([
        'charging_basis' => TransportFare::BASIS_PER_VEHICLE,
        'sale_amount' => 500,
        'cost_amount' => 400,
        'hajj_terminal_sale_amount' => 90,
        'hajj_terminal_cost_amount' => 60,
    ]);

    $result = app(TransportPricingCalculator::class)->fareTotals($fare, 2, 7, true);

    expect($result['factor'])->toBe(2)
        ->and($result['total_sale_amount'])->toBe(1180.0)
        ->and($result['total_cost_amount'])->toBe(920.0);
});

test('per passenger fare uses the passenger count', function () {
    $fare = new TransportFare([
        'charging_basis' => TransportFare::BASIS_PER_PASSENGER,
        'sale_amount' => 100,
        'cost_amount' => 70,
        'hajj_terminal_sale_amount' => 0,
        'hajj_terminal_cost_amount' => 0,
    ]);

    $result = app(TransportPricingCalculator::class)->fareTotals($fare, 1, 8, false);

    expect($result['factor'])->toBe(8)
        ->and($result['total_sale_amount'])->toBe(800.0)
        ->and($result['total_cost_amount'])->toBe(560.0);
});
