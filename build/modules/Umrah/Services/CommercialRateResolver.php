<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\CommercialRate;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CommercialRateResolver
{
    /** @return array<string, mixed> */
    public function visa(VisaVendor $vendor, string $ageBand, ?string $agentId, string|Carbon|null $serviceDate): array
    {
        $child = $ageBand === 'child';

        return $this->resolve(
            $vendor->company_id,
            $child ? CommercialRate::SERVICE_VISA_CHILD : CommercialRate::SERVICE_VISA_ADULT,
            'visa_vendor_id',
            $vendor->id,
            $agentId,
            $serviceDate,
            (float) $vendor->getAttribute($child ? 'child_retail_amount' : 'adult_retail_amount'),
            (float) $vendor->getAttribute($child ? 'child_cost_amount' : 'adult_cost_amount'),
        );
    }

    /** @return array<string, mixed> */
    public function standardTransport(VisaVendor $provider, ?string $agentId, string|Carbon|null $serviceDate): array
    {
        return $this->resolve(
            $provider->company_id,
            CommercialRate::SERVICE_STANDARD_TRANSPORT,
            'visa_vendor_id',
            $provider->id,
            $agentId,
            $serviceDate,
            (float) $provider->standard_bus_retail_amount,
            (float) $provider->standard_bus_cost_amount,
        );
    }

    /** @return array<string, mixed> */
    public function transportFare(TransportFare $fare, ?string $agentId, string|Carbon|null $serviceDate): array
    {
        return $this->resolve(
            $fare->company_id,
            CommercialRate::SERVICE_TRANSPORT_FARE,
            'transport_fare_id',
            $fare->id,
            $agentId,
            $serviceDate,
            (float) $fare->sale_amount,
            (float) $fare->cost_amount,
        );
    }

    /** @return array<string, mixed> */
    public function hotelRoom(HotelRoomRate $rate, ?string $agentId, string|Carbon|null $serviceDate): array
    {
        return $this->resolve(
            $rate->company_id,
            CommercialRate::SERVICE_HOTEL_ROOM,
            'hotel_room_rate_id',
            $rate->id,
            $agentId,
            $serviceDate,
            (float) $rate->retail_amount,
            (float) $rate->cost_amount,
        );
    }

    /**
     * Resolve one non-stacking commercial price. An agent or category rule is
     * calculated from the effective default sale, never from another override.
     *
     * @return array<string, mixed>
     */
    private function resolve(
        string $companyId,
        string $serviceType,
        string $targetColumn,
        string $targetId,
        ?string $agentId,
        string|Carbon|null $serviceDate,
        float $legacySale,
        float $legacyCost,
    ): array {
        $date = $serviceDate instanceof Carbon
            ? $serviceDate->toDateString()
            : (filled($serviceDate) ? Carbon::parse($serviceDate)->toDateString() : now()->toDateString());
        $currency = (string) Company::whereKey($companyId)->value('base_currency');

        $baseQuery = fn (): Builder => CommercialRate::query()
            ->where('company_id', $companyId)
            ->where('service_type', $serviceType)
            ->where($targetColumn, $targetId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query
                ->whereNull('effective_until')
                ->orWhereDate('effective_until', '>=', $date));

        $default = $baseQuery()
            ->where('scope_type', CommercialRate::SCOPE_DEFAULT)
            ->latest('effective_from')
            ->first();
        $defaultSale = $default ? (float) $default->amount : $legacySale;
        $defaultCost = $default && $default->cost_amount !== null ? (float) $default->cost_amount : $legacyCost;
        $winner = null;

        if ($agentId) {
            $winner = $baseQuery()
                ->where('scope_type', CommercialRate::SCOPE_AGENT)
                ->where('agent_id', $agentId)
                ->latest('effective_from')
                ->first();

            if (! $winner) {
                $categoryId = Agent::where('company_id', $companyId)
                    ->whereKey($agentId)
                    ->whereHas('pricingCategory', fn (Builder $query) => $query->where('is_active', true))
                    ->value('pricing_category_id');

                if ($categoryId) {
                    $winner = $baseQuery()
                        ->where('scope_type', CommercialRate::SCOPE_CATEGORY)
                        ->where('pricing_category_id', $categoryId)
                        ->latest('effective_from')
                        ->first();
                }
            }
        }

        $sale = $winner ? $this->apply($defaultSale, $winner) : $defaultSale;
        $source = $winner?->scope_type ?? ($default ? 'default' : 'legacy');

        return [
            'sale_amount' => round(max($sale, 0), 2),
            'cost_amount' => round(max($defaultCost, 0), 2),
            'currency' => $currency,
            'service_date' => $date,
            'source' => $source,
            'rule_id' => $winner?->id ?? $default?->id,
            'default_rule_id' => $default?->id,
            'default_sale_amount' => round(max($defaultSale, 0), 2),
            'calculation_type' => $winner?->calculation_type ?? ($default ? CommercialRate::CALC_SET_PRICE : 'legacy'),
            'adjustment_amount' => $winner?->amount !== null ? (float) $winner->amount : null,
            'adjustment_percentage' => $winner?->percentage !== null ? (float) $winner->percentage : null,
        ];
    }

    private function apply(float $base, CommercialRate $rule): float
    {
        return match ($rule->calculation_type) {
            CommercialRate::CALC_SET_PRICE => (float) $rule->amount,
            CommercialRate::CALC_DISCOUNT_AMOUNT => $base - (float) $rule->amount,
            CommercialRate::CALC_DISCOUNT_PERCENTAGE => $base * (1 - ((float) $rule->percentage / 100)),
            CommercialRate::CALC_MARKUP_AMOUNT => $base + (float) $rule->amount,
            CommercialRate::CALC_MARKUP_PERCENTAGE => $base * (1 + ((float) $rule->percentage / 100)),
            default => $base,
        };
    }
}
