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
        return $this->fareTotalsFromAmounts(
            $fare->charging_basis,
            (float) $fare->sale_amount,
            (float) $fare->cost_amount,
            (float) $fare->hajj_terminal_sale_amount,
            (float) $fare->hajj_terminal_cost_amount,
            $quantity,
            $passengerCount,
            $hajjTerminal,
        );
    }

    public function fareTotalsFromAmounts(
        string $chargingBasis,
        float $saleAmount,
        float $costAmount,
        float $hajjSaleAmount,
        float $hajjCostAmount,
        int $quantity,
        int $passengerCount,
        bool $hajjTerminal,
    ): array {
        $quantity = max($quantity, 1);
        $passengerCount = max($passengerCount, 1);
        $factor = match ($chargingBasis) {
            TransportFare::BASIS_PER_PASSENGER => $passengerCount,
            TransportFare::BASIS_FLAT_GROUP => 1,
            default => $quantity,
        };
        $surchargeSale = $hajjTerminal ? round($hajjSaleAmount * $factor, 2) : 0.0;
        $surchargeCost = $hajjTerminal ? round($hajjCostAmount * $factor, 2) : 0.0;

        return [
            'factor' => $factor,
            'surcharge_sale_amount' => $surchargeSale,
            'surcharge_cost_amount' => $surchargeCost,
            'total_sale_amount' => round($saleAmount * $factor + $surchargeSale, 2),
            'total_cost_amount' => round($costAmount * $factor + $surchargeCost, 2),
        ];
    }
}
