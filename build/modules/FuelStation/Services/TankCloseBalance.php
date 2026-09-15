<?php

namespace App\Modules\FuelStation\Services;

use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\StockMovement;

class TankCloseBalance
{
    /** Opening inventory excludes this day's trading, including an amended close. */
    public function opening(string $companyId, string $tankId, string $itemId, string $date): array
    {
        $movements = StockMovement::where('company_id', $companyId)
            ->where('warehouse_id', $tankId)->where('item_id', $itemId)
            ->where(function ($query) use ($date) {
                $query->whereDate('movement_date', '<', $date)
                    ->orWhere(function ($query) use ($date) {
                        $query->whereDate('movement_date', $date)->where('movement_type', 'opening');
                    });
            });

        if ((clone $movements)->exists()) {
            return ['liters' => (float) $movements->sum('quantity'), 'date' => $date,
                'source_label' => 'Opening stock before sales'];
        }

        $reading = TankReading::where('company_id', $companyId)->where('tank_id', $tankId)
            ->where('status', TankReading::STATUS_POSTED)
            ->where(function ($query) {
                $query->whereNull('notes')->orWhere('notes', '!=', 'Daily close dip');
            })
            ->where(function ($query) use ($date) {
                $query->where(function ($query) use ($date) {
                    $query->whereDate('reading_date', $date)->where('reading_type', 'opening');
                })->orWhere(function ($query) use ($date) {
                    $query->whereDate('reading_date', '<', $date)->where('reading_type', 'closing');
                });
            })->orderByDesc('reading_date')->first();

        return ['liters' => (float) ($reading?->dip_measurement_liters ?? 0),
            'date' => $reading ? $date : null, 'source_label' => 'Tank dip before sales'];
    }

    public function movementsDuringDay(string $companyId, string $tankId, string $itemId, string $date): float
    {
        return (float) StockMovement::where('company_id', $companyId)
            ->where('warehouse_id', $tankId)->where('item_id', $itemId)
            ->whereDate('movement_date', $date)->where('movement_type', '!=', 'opening')
            ->where(function ($query) {
                $query->whereNull('reference_type')->orWhere('reference_type', '!=', 'fuel.daily_close');
            })->sum('quantity');
    }

    /** Compare a dip only to stock at the time it was taken. */
    public function calculate(string $type, float $dip, float $opening, bool $hasBaseline, float $receipts, float $sales): array
    {
        $morning = $type === TankReading::TYPE_OPENING;
        $expected = $morning ? ($hasBaseline ? $opening : $dip) : $opening + $receipts - $sales;

        return [
            'expected' => round($expected, 2),
            'variance' => round($dip - $expected, 2),
            'closing' => round($morning ? $dip + $receipts - $sales : $dip, 2),
        ];
    }
}
