<?php

namespace App\Modules\Umrah\Handlers;

use App\Modules\Umrah\Commands\SaveCommercialRate;
use App\Modules\Umrah\Models\CommercialRate;
use Illuminate\Support\Facades\DB;

final class SaveCommercialRateHandler
{
    public function handle(SaveCommercialRate $command): CommercialRate
    {
        return DB::transaction(function () use ($command): CommercialRate {
            $rate = $command->rateId
                ? CommercialRate::where('company_id', $command->companyId)->lockForUpdate()->findOrFail($command->rateId)
                : new CommercialRate([
                    'company_id' => $command->companyId,
                    'created_by_user_id' => $command->userId,
                    'is_active' => true,
                ]);

            $targetColumns = [
                'visa_vendor_id' => null,
                'transport_fare_id' => null,
                'hotel_room_rate_id' => null,
            ];
            $targetColumn = match ($command->data['service_type']) {
                CommercialRate::SERVICE_TRANSPORT_FARE => 'transport_fare_id',
                CommercialRate::SERVICE_HOTEL_ROOM => 'hotel_room_rate_id',
                default => 'visa_vendor_id',
            };
            $targetColumns[$targetColumn] = $command->data['target_id'];
            $scopeType = $command->data['scope_type'];
            $amountCalculation = in_array($command->data['calculation_type'], [
                CommercialRate::CALC_SET_PRICE,
                CommercialRate::CALC_DISCOUNT_AMOUNT,
                CommercialRate::CALC_MARKUP_AMOUNT,
            ], true);

            $rate->fill([
                'service_type' => $command->data['service_type'],
                ...$targetColumns,
                'scope_type' => $scopeType,
                'pricing_category_id' => $scopeType === CommercialRate::SCOPE_CATEGORY ? $command->data['scope_id'] : null,
                'agent_id' => $scopeType === CommercialRate::SCOPE_AGENT ? $command->data['scope_id'] : null,
                'calculation_type' => $command->data['calculation_type'],
                'amount' => $amountCalculation ? $command->data['amount'] : null,
                'percentage' => $amountCalculation ? null : $command->data['percentage'],
                'cost_amount' => $scopeType === CommercialRate::SCOPE_DEFAULT ? ($command->data['cost_amount'] ?? null) : null,
                'currency' => $command->data['currency'],
                'effective_from' => $command->data['effective_from'],
                'effective_until' => $command->data['effective_until'] ?? null,
                'notes' => $command->data['notes'] ?? null,
            ])->save();

            return $rate->fresh();
        });
    }
}
