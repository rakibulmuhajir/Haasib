<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GroupAccountingService
{
    public function __construct(private readonly UmrahCoreService $core) {}

    public function summary(VisaGroup $group): array
    {
        $group->load([
            'agent:id,name',
            'vendor:id,name',
            'mandatoryTransportVendor:id,name',
            'transportItems:id,visa_group_id,transport_vendor_id,transport_service_id,description,quantity,passenger_count,total_sale_amount,total_cost_amount',
            'transportItems.service:id,name,vehicle_type',
            'transportItems.transportVendor:id,name',
            'vouchers' => fn ($query) => $query
                ->whereNull('superseded_at')
                ->withCount(['allPassengers as passengers_count'])
                ->orderBy('voucher_number'),
            'vouchers.billingVoucher:id,voucher_number',
        ]);
        $passengers = Passenger::where('company_id', $group->company_id)
            ->where('visa_group_id', $group->id)
            ->get(['date_of_birth', 'imported_age', 'service_type', 'transport_charge_amount']);
        $referenceDate = $group->travel_date?->copy()->startOfDay() ?? now()->startOfDay();
        $ageCounts = $this->ageCounts($passengers, $referenceDate);

        $visaPax = $passengers->where('service_type', Passenger::SERVICE_VISA_TRANSPORT)->count();
        $transportOnlyPax = $passengers->where('service_type', Passenger::SERVICE_TRANSPORT_ONLY)->count();
        $transportOnlyCharge = (float) $passengers
            ->where('service_type', Passenger::SERVICE_TRANSPORT_ONLY)
            ->sum('transport_charge_amount');
        $chargeableVouchers = $group->vouchers
            ->where('status', Voucher::STATUS_APPROVED)
            ->whereNull('billing_voucher_id');
        $hotelStays = $chargeableVouchers->sum(fn (Voucher $voucher) => collect($voucher->hotel_stays)
            ->where('source', 'company')->count());
        $services = collect();
        if ($visaPax > 0) {
            $services->push(['stage' => 'group', 'service' => 'Visa with mandatory transport', 'quantity' => $visaPax, 'charge' => (float) $group->visa_sale_amount]);
        }
        if ($transportOnlyPax > 0) {
            $services->push(['stage' => 'group', 'service' => 'Transport only', 'quantity' => $transportOnlyPax, 'charge' => $transportOnlyCharge]);
        }
        foreach ($group->transportItems as $item) {
            $services->push([
                'stage' => 'group',
                'service' => $item->description ?: ($item->service?->name ?? 'Specialized transport'),
                'quantity' => $item->quantity,
                'charge' => (float) $item->total_sale_amount,
            ]);
        }
        if ($hotelStays > 0 || (float) $group->hotel_amount > 0) {
            $services->push(['stage' => 'voucher', 'service' => 'Approved company hotel stays', 'quantity' => $hotelStays, 'charge' => (float) $group->hotel_amount]);
        }

        $voucherBreakdown = $group->vouchers->map(fn (Voucher $voucher) => [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'status' => $voucher->status,
            'passengers' => (int) $voucher->passengers_count,
            'company_stays' => collect($voucher->hotel_stays)->where('source', 'company')->count(),
            'self_stays' => collect($voucher->hotel_stays)->where('source', 'self')->count(),
            'hotel_sale_amount' => (float) $voucher->hotel_sale_amount,
            'hotel_cost_amount' => (float) $voucher->hotel_cost_amount,
            'accounting_state' => $this->voucherAccountingState($voucher),
            'billing_voucher_number' => $voucher->billingVoucher?->voucher_number,
        ])->values();

        return [
            'group' => $group->only([
                'id', 'group_number', 'travel_date', 'transport_mode', 'vendor_id', 'mandatory_transport_vendor_id',
                'visa_sale_amount', 'transport_amount', 'hotel_amount', 'discount_amount', 'visa_cost_amount',
                'transport_cost_amount', 'hotel_cost_amount', 'included_bus_cost_deduction', 'mandatory_transport_cost_amount',
                'total_receivable', 'total_paid', 'balance', 'profit',
            ]) + ['agent' => $group->agent?->only(['id', 'name']), 'vendor' => $group->vendor?->only(['id', 'name']), 'mandatory_transport_vendor' => $group->mandatoryTransportVendor?->only(['id', 'name'])],
            'passengerSummary' => ['total' => $passengers->count(), ...$ageCounts, 'visa' => $visaPax, 'transport_only' => $transportOnlyPax],
            'services' => $services->values(),
            'voucherBreakdown' => $voucherBreakdown,
        ];
    }

    public function voucherSummary(Voucher $voucher): array
    {
        $voucher->load([
            'agent:id,name',
            'billingVoucher:id,voucher_number',
            'group' => fn ($query) => $query->with([
                'vendor:id,name',
                'mandatoryTransportVendor:id,name',
                'transportItems:id,visa_group_id,transport_vendor_id,description,quantity,total_sale_amount,total_cost_amount',
                'transportItems.transportVendor:id,name',
            ]),
        ]);

        $passengerIds = VoucherPassenger::withTrashed()
            ->where('company_id', $voucher->company_id)
            ->where('voucher_id', $voucher->id)
            ->pluck('passenger_id');
        $passengers = Passenger::withTrashed()->where('company_id', $voucher->company_id)
            ->whereIn('id', $passengerIds)
            ->get(['date_of_birth', 'imported_age', 'service_type']);
        $referenceDate = $voucher->group?->travel_date?->copy()->startOfDay()
            ?? $voucher->onward_departure_at?->copy()->startOfDay()
            ?? now()->startOfDay();
        $ageCounts = $this->ageCounts($passengers, $referenceDate);
        $companyStays = collect($voucher->hotel_stays)->where('source', 'company');
        $selfStays = collect($voucher->hotel_stays)->where('source', 'self');
        $group = $voucher->group;
        $groupRevenue = round((float) $group->visa_sale_amount + (float) $group->transport_amount - (float) $group->discount_amount, 2);
        $groupCost = round((float) $group->visa_cost_amount + (float) $group->transport_cost_amount, 2);

        return [
            'voucher' => $voucher->only([
                'id', 'voucher_number', 'status', 'service_bundle', 'visa_group_id', 'billing_voucher_id',
                'hotel_sale_amount', 'hotel_cost_amount', 'hotel_sale_transaction_id', 'hotel_cost_transaction_id',
                'cancelled_at', 'superseded_at',
            ]) + [
                'agent' => $voucher->agent?->only(['id', 'name']),
                'billing_voucher' => $voucher->billingVoucher?->only(['id', 'voucher_number']),
            ],
            'groupPosting' => [
                'id' => $group->id,
                'group_number' => $group->group_number,
                'vendor' => $group->vendor?->only(['id', 'name']),
                'mandatory_transport_vendor' => $group->mandatoryTransportVendor?->only(['id', 'name']),
                'visa_sale_amount' => (float) $group->visa_sale_amount,
                'transport_amount' => (float) $group->transport_amount,
                'discount_amount' => (float) $group->discount_amount,
                'revenue' => $groupRevenue,
                'visa_cost_amount' => (float) $group->visa_cost_amount,
                'transport_cost_amount' => (float) $group->transport_cost_amount,
                'cost' => $groupCost,
                'profit' => round($groupRevenue - $groupCost, 2),
                'accounting_state' => $group->sale_transaction_id || $group->cost_transaction_id
                    ? 'posted'
                    : ($groupRevenue > 0 || $groupCost > 0 ? 'unposted' : 'no_charge'),
            ],
            'voucherPosting' => [
                'company_stays' => $companyStays->count(),
                'self_stays' => $selfStays->count(),
                'hotel_sale_amount' => (float) $voucher->hotel_sale_amount,
                'hotel_cost_amount' => (float) $voucher->hotel_cost_amount,
                'profit' => round((float) $voucher->hotel_sale_amount - (float) $voucher->hotel_cost_amount, 2),
                'accounting_state' => $this->voucherAccountingState($voucher),
            ],
            'groupConsolidated' => $group->only([
                'hotel_amount', 'hotel_cost_amount', 'total_receivable', 'total_paid', 'balance', 'profit',
            ]),
            'passengerSummary' => [
                'total' => $passengers->count(),
                ...$ageCounts,
                'visa' => $passengers->where('service_type', Passenger::SERVICE_VISA_TRANSPORT)->count(),
                'transport_only' => $passengers->where('service_type', Passenger::SERVICE_TRANSPORT_ONLY)->count(),
            ],
        ];
    }

    public function update(VisaGroup $group, array $data): VisaGroup
    {
        return DB::transaction(function () use ($group, $data): VisaGroup {
            $group = VisaGroup::where('company_id', $group->company_id)->lockForUpdate()->findOrFail($group->id);
            $before = $group->only(['visa_sale_amount', 'transport_amount', 'discount_amount', 'total_receivable', 'visa_cost_amount', 'transport_cost_amount']);
            $oldVendorIds = array_filter([$group->vendor_id, $group->mandatory_transport_vendor_id]);
            $vendors = $this->core->resolveGroupVendors($group->company_id, [
                'vendor_id' => $data['vendor_id'],
                'mandatory_transport_vendor_id' => $data['mandatory_transport_vendor_id'] ?? null,
                'transport_mode' => $group->transport_mode,
            ], false);

            $group->update([
                'vendor_id' => $vendors['vendor_id'],
                'mandatory_transport_vendor_id' => $vendors['mandatory_transport_vendor_id'],
                'visa_sale_amount' => round((float) $data['visa_sale_amount'], 2),
                'transport_amount' => round((float) $data['transport_amount'], 2),
                'discount_amount' => round((float) $data['discount_amount'], 2),
            ]);
            $group = $this->core->recalculateGroup($group->fresh());
            $this->core->postGroupFinancialAdjustment($group, $before, $data['reason']);
            $this->core->recalculateAgent($group->agent_id);
            foreach (array_unique([...$oldVendorIds, $group->vendor_id, $group->mandatory_transport_vendor_id]) as $vendorId) {
                if ($vendorId) {
                    $this->core->recalculateVendor($vendorId);
                }
            }

            return $group->fresh();
        });
    }

    private function ageCounts(Collection $passengers, $referenceDate): array
    {
        $counts = ['adults' => 0, 'children' => 0, 'infants' => 0];
        foreach ($passengers as $passenger) {
            $age = $passenger->date_of_birth
                ? $passenger->date_of_birth->diffInYears($referenceDate)
                : $passenger->imported_age;
            $key = $age !== null && $age < 2 ? 'infants' : ($age !== null && $age < 12 ? 'children' : 'adults');
            $counts[$key]++;
        }

        return $counts;
    }

    private function voucherAccountingState(Voucher $voucher): string
    {
        if ($voucher->status === Voucher::STATUS_CANCELLED) {
            return 'reversed';
        }
        if ($voucher->superseded_at) {
            return 'superseded';
        }
        if ($voucher->billing_voucher_id) {
            return 'shared';
        }
        if ($voucher->status === Voucher::STATUS_DRAFT) {
            return 'pending';
        }
        if ((float) $voucher->hotel_sale_amount <= 0 && (float) $voucher->hotel_cost_amount <= 0) {
            return 'no_charge';
        }

        return $voucher->hotel_sale_transaction_id || $voucher->hotel_cost_transaction_id ? 'posted' : 'unposted';
    }
}
