<?php

namespace App\Modules\Umrah\Handlers;

use App\Modules\Umrah\Commands\SetCommercialPricingStatus;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\CommercialRate;
use App\Modules\Umrah\Models\PricingCategory;
use Illuminate\Validation\ValidationException;

final class SetCommercialPricingStatusHandler
{
    public function handle(SetCommercialPricingStatus $command): void
    {
        if ($command->recordType === 'category') {
            $category = PricingCategory::where('company_id', $command->companyId)->findOrFail($command->recordId);
            if (! $command->isActive && Agent::where('company_id', $command->companyId)
                ->where('is_active', true)->where('pricing_category_id', $category->id)->exists()) {
                throw ValidationException::withMessages([
                    'category' => 'Move active agents out of this category before deactivating it.',
                ]);
            }
            $category->update(['is_active' => $command->isActive]);

            return;
        }

        $rate = CommercialRate::where('company_id', $command->companyId)->findOrFail($command->recordId);
        if ($command->isActive) {
            $targetColumn = $rate->visa_vendor_id ? 'visa_vendor_id' : ($rate->transport_fare_id ? 'transport_fare_id' : 'hotel_room_rate_id');
            $overlap = CommercialRate::where('company_id', $command->companyId)
                ->whereKeyNot($rate->id)
                ->where('service_type', $rate->service_type)
                ->where($targetColumn, $rate->getAttribute($targetColumn))
                ->where('scope_type', $rate->scope_type)
                ->where('pricing_category_id', $rate->pricing_category_id)
                ->where('agent_id', $rate->agent_id)
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', $rate->effective_from))
                ->where(fn ($query) => $query->whereDate('effective_from', '<=', $rate->effective_until?->toDateString() ?: '9999-12-31'))
                ->exists();
            if ($overlap) {
                throw ValidationException::withMessages([
                    'rate' => 'This rate overlaps another active rule. Change its dates before reactivating it.',
                ]);
            }
        }
        $rate->update(['is_active' => $command->isActive]);
    }
}
