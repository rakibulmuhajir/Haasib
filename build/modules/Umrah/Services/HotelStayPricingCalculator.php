<?php

namespace App\Modules\Umrah\Services;

use Illuminate\Support\Carbon;

class HotelStayPricingCalculator
{
    public function calculate(string $checkIn, string $checkOut, int $rooms, int $bedsPerRoom, float $retailPerBed, float $costPerBed): array
    {
        $nights = max((int) Carbon::parse($checkIn)->startOfDay()->diffInDays(Carbon::parse($checkOut)->startOfDay()), 1);
        $rooms = max($rooms, 1);
        $bedsPerRoom = max($bedsPerRoom, 1);

        return [
            'night_count' => $nights,
            'beds_per_room' => $bedsPerRoom,
            'total_retail_amount' => round($retailPerBed * $bedsPerRoom * $rooms * $nights, 2),
            'total_cost_amount' => round($costPerBed * $bedsPerRoom * $rooms * $nights, 2),
        ];
    }
}
