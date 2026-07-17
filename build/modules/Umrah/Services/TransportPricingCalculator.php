<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\TransportFare;

class TransportPricingCalculator
{
    public function separateIncludedBusCost(float $baseVisaCost, float $includedBusCost, int $visaPassengerCount): array
    {
        $deduction = min(round($baseVisaCost, 2), round($includedBusCost * max($visaPassengerCount, 0), 2));

        return [
            'deduction' => $deduction,
            'adjusted_visa_cost' => round(max($baseVisaCost - $deduction, 0), 2),
        ];
    }

    public function fareTotals(TransportFare $fare, int $quantity, int $passengerCount, bool $hajjTerminal): array
    {
        $quantity = max($quantity, 1);
        $passengerCount = max($passengerCount, 1);
        $factor = match ($fare->charging_basis) {
            TransportFare::BASIS_PER_PASSENGER => $passengerCount,
            TransportFare::BASIS_FLAT_GROUP => 1,
            default => $quantity,
        };
        $surchargeSale = $hajjTerminal ? round((float) $fare->hajj_terminal_sale_amount * $factor, 2) : 0.0;
        $surchargeCost = $hajjTerminal ? round((float) $fare->hajj_terminal_cost_amount * $factor, 2) : 0.0;

        return [
            'factor' => $factor,
            'surcharge_sale_amount' => $surchargeSale,
            'surcharge_cost_amount' => $surchargeCost,
            'total_sale_amount' => round((float) $fare->sale_amount * $factor + $surchargeSale, 2),
            'total_cost_amount' => round((float) $fare->cost_amount * $factor + $surchargeCost, 2),
        ];
    }
}
