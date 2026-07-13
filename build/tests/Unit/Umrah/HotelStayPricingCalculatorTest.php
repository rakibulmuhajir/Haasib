<?php

use App\Modules\Umrah\Services\HotelStayPricingCalculator;

test('hotel stay price uses beds, rooms, and calendar nights', function () {
    $result = app(HotelStayPricingCalculator::class)->calculate('2026-08-01 15:00', '2026-08-04 11:00', 2, 3, 100, 70);

    expect($result)->toBe(['night_count' => 3, 'beds_per_room' => 3, 'total_retail_amount' => 1800.0, 'total_cost_amount' => 1260.0]);
});

test('a double room total is twice its per-bed rate', function () {
    $result = app(HotelStayPricingCalculator::class)->calculate('2026-08-01', '2026-08-02', 1, 2, 100, 80);

    expect($result['total_retail_amount'])->toBe(200.0)
        ->and($result['total_cost_amount'])->toBe(160.0);
});
