<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\CommercialRate;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\PricingCategory;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaVendor;

class CommercialPricingCatalog
{
    /** @return array<string, mixed> */
    public function payload(string $companyId, ?string $agentId = null): array
    {
        $visaVendors = VisaVendor::where('company_id', $companyId)
            ->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
            ->orderByName()->get(['id', 'vendor_id']);
        $transportProviders = VisaVendor::where('company_id', $companyId)
            ->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)
            ->orderByName()->get(['id', 'vendor_id']);
        $transportFares = TransportFare::where('company_id', $companyId)
            ->with(['transportVendor:id,vendor_id', 'service:id,name', 'sector:id,name', 'package:id,name'])
            ->orderBy('name')->get();
        $roomRates = HotelRoomRate::where('company_id', $companyId)
            ->with('hotel:id,name,city')->orderBy('hotel_id')->orderBy('room_type')->get();

        $targets = [
            CommercialRate::SERVICE_VISA_ADULT => $visaVendors->map(fn (VisaVendor $vendor) => ['id' => $vendor->id, 'label' => $vendor->name])->values(),
            CommercialRate::SERVICE_VISA_CHILD => $visaVendors->map(fn (VisaVendor $vendor) => ['id' => $vendor->id, 'label' => $vendor->name])->values(),
            CommercialRate::SERVICE_STANDARD_TRANSPORT => $transportProviders->map(fn (VisaVendor $vendor) => ['id' => $vendor->id, 'label' => $vendor->name])->values(),
            CommercialRate::SERVICE_TRANSPORT_FARE => $transportFares->map(fn (TransportFare $fare) => [
                'id' => $fare->id,
                'label' => collect([$fare->transportVendor?->name, $fare->name, $fare->sector?->name ?: $fare->package?->name])->filter()->join(' · '),
            ])->values(),
            CommercialRate::SERVICE_HOTEL_ROOM => $roomRates->map(fn (HotelRoomRate $rate) => [
                'id' => $rate->id,
                'label' => "{$rate->hotel?->name} · {$rate->hotel?->city} · ".(HotelRoomRate::TYPES[$rate->room_type] ?? $rate->room_type),
            ])->values(),
        ];

        $rates = CommercialRate::where('company_id', $companyId)
            ->when($agentId, fn ($query) => $query->where('scope_type', CommercialRate::SCOPE_AGENT)->where('agent_id', $agentId))
            ->with(['pricingCategory:id,name', 'agent:id,customer_id'])
            ->orderByDesc('is_active')->orderByDesc('effective_from')->get()
            ->map(function (CommercialRate $rate) use ($targets): array {
                $target = collect($targets[$rate->service_type] ?? [])->firstWhere('id', $rate->targetId());

                return [
                    ...$rate->toArray(),
                    'service_label' => CommercialRate::SERVICE_TYPES[$rate->service_type] ?? $rate->service_type,
                    'target_id' => $rate->targetId(),
                    'target_label' => $target['label'] ?? 'Unavailable service',
                    'scope_id' => $rate->pricing_category_id ?: $rate->agent_id,
                    'scope_label' => match ($rate->scope_type) {
                        CommercialRate::SCOPE_CATEGORY => $rate->pricingCategory?->name,
                        CommercialRate::SCOPE_AGENT => $rate->agent?->name,
                        default => 'Everyone',
                    },
                    'calculation_label' => CommercialRate::CALCULATION_TYPES[$rate->calculation_type] ?? $rate->calculation_type,
                ];
            });

        return [
            'categories' => PricingCategory::where('company_id', $companyId)->withCount(['agents' => fn ($query) => $query->where('is_active', true)])->orderBy('name')->get(),
            'agents' => Agent::where('company_id', $companyId)->where('is_active', true)->orderByName()->get(['id', 'customer_id', 'pricing_category_id']),
            'rates' => $rates,
            'targets' => $targets,
            'serviceTypes' => CommercialRate::SERVICE_TYPES,
            'scopeTypes' => CommercialRate::SCOPE_TYPES,
            'calculationTypes' => CommercialRate::CALCULATION_TYPES,
        ];
    }
}
