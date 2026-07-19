<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\PaymentAllocation;
use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UmrahCoreService
{
    public function __construct(
        private GlPostingService $glPostingService,
        private PostingService $postingService,
        private TransportPricingCalculator $transportPricing,
    ) {}

    public function nextAgentNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, Agent::query(), 'agent_number', 'AGT');
    }

    public function nextVendorNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, VisaVendor::query(), 'vendor_number', 'UVN');
    }

    public function nextGroupNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, VisaGroup::query(), 'group_number', 'UGR');
    }

    public function nextPaymentNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, GroupPayment::query(), 'payment_number', 'UPM');
    }

    public function nextVoucherNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, Voucher::query(), 'voucher_number', 'UVR');
    }

    public function nextHotelVendorNumber(string $companyId): string
    {
        return $this->nextNumber($companyId, HotelVendor::query(), 'vendor_number', 'HVN');
    }

    public function resolveGroupVendors(string $companyId, array $data, bool $forceDefaults): array
    {
        $vendorQuery = VisaVendor::where('company_id', $companyId)
            ->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)
            ->where('is_active', true);
        $vendor = ! $forceDefaults && ! empty($data['vendor_id'])
            ? (clone $vendorQuery)->find($data['vendor_id'])
            : (clone $vendorQuery)->where('is_default', true)->first();

        if (! $vendor) {
            throw ValidationException::withMessages(['vendor_id' => 'Set an active default visa vendor before creating a group.']);
        }

        $data['vendor_id'] = $vendor->id;
        if (($data['transport_mode'] ?? VisaGroup::TRANSPORT_STANDARD_BUS) !== VisaGroup::TRANSPORT_STANDARD_BUS) {
            $data['mandatory_transport_vendor_id'] = null;

            return $data;
        }

        $providerId = ! $forceDefaults && ! empty($data['mandatory_transport_vendor_id'])
            ? $data['mandatory_transport_vendor_id']
            : $vendor->resolvedMandatoryTransportVendorId();
        $provider = VisaVendor::where('company_id', $companyId)
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orWhere('provides_mandatory_transport', true))
            ->find($providerId);
        if (! $provider) {
            throw ValidationException::withMessages(['mandatory_transport_vendor_id' => 'Configure the mandatory transport provider for the selected visa vendor.']);
        }
        $data['mandatory_transport_vendor_id'] = $provider->id;

        return $data;
    }

    public function createGroup(string $companyId, array $data): VisaGroup
    {
        return DB::transaction(function () use ($companyId, $data) {
            $data = $this->applyServiceDefaults($companyId, $data);
            $financials = $this->calculateGroupFinancials($data, 0);
            $passengerCount = max((int) ($data['passenger_count'] ?? 0), count($data['passengers'] ?? []));
            $transportItems = $data['resolved_transport_items'] ?? [];
            $primaryTransport = $transportItems[0] ?? null;

            $group = VisaGroup::create([
                'company_id' => $companyId,
                'agent_id' => $data['agent_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'mandatory_transport_vendor_id' => $data['mandatory_transport_vendor_id'] ?? null,
                'transport_service_id' => $primaryTransport['transport_service_id'] ?? ($data['transport_service_id'] ?? null),
                'driver_id' => $primaryTransport['driver_id'] ?? ($data['driver_id'] ?? null),
                'group_number' => ($data['group_number'] ?? null) ?: $this->nextGroupNumber($companyId),
                'name' => trim((string) ($data['name'] ?? '')) ?: $this->defaultGroupName($companyId, $data['agent_id'], $passengerCount),
                'status' => VisaGroup::STATUS_VISA_APPROVED,
                'travel_date' => $data['travel_date'] ?? null,
                'flight_info' => [
                    'airline' => $data['flight_airline'] ?? null,
                    'number' => $data['flight_number'] ?? null,
                    'notes' => $data['flight_notes'] ?? null,
                ],
                'hotel_info' => [
                    'makkah' => $data['hotel_makkah'] ?? null,
                    'madinah' => $data['hotel_madinah'] ?? null,
                    'notes' => $data['hotel_notes'] ?? null,
                ],
                'transport_required' => (bool) ($data['transport_required'] ?? false),
                'transport_mode' => $data['transport_mode'] ?? VisaGroup::TRANSPORT_STANDARD_BUS,
                'included_bus_cost_per_passenger' => $data['included_bus_cost_per_passenger'] ?? 50,
                'included_bus_cost_deduction' => $data['included_bus_cost_deduction'] ?? 0,
                'mandatory_transport_cost_amount' => $data['mandatory_transport_cost_amount'] ?? 0,
                'transport_quantity' => $primaryTransport['quantity'] ?? (int) ($data['transport_quantity'] ?? 1),
                'transport_pax_capacity' => $primaryTransport['pax_capacity'] ?? ($data['transport_pax_capacity'] ?? null),
                'passenger_count' => $passengerCount,
                'visa_sale_amount' => $financials['visa_sale_amount'],
                'transport_amount' => $financials['transport_amount'],
                'discount_amount' => $financials['discount_amount'],
                'visa_cost_amount' => $financials['visa_cost_amount'],
                'transport_cost_amount' => $financials['transport_cost_amount'],
                'total_receivable' => $financials['total_receivable'],
                'total_paid' => 0,
                'balance' => $financials['balance'],
                'profit' => $financials['profit'],
                'notes' => $data['notes'] ?? null,
            ]);

            $agentCountry = Agent::where('company_id', $companyId)
                ->whereKey($group->agent_id)
                ->value('country') ?: 'Pakistan';

            foreach (($data['passengers'] ?? []) as $index => $passenger) {
                if (! trim((string) ($passenger['full_name'] ?? ''))) {
                    continue;
                }

                Passenger::create([
                    'company_id' => $companyId,
                    'visa_group_id' => $group->id,
                    'full_name' => $passenger['full_name'],
                    'passport_number' => $passenger['passport_number'] ?? null,
                    'nationality' => ! empty($passenger['nationality']) ? $passenger['nationality'] : $agentCountry,
                    'date_of_birth' => $passenger['date_of_birth'] ?? null,
                    'imported_age' => $passenger['imported_age'] ?? null,
                    'service_type' => $passenger['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT,
                    'transport_charge_amount' => round((float) ($passenger['transport_charge_amount'] ?? 0), 2),
                    'visa_status' => $passenger['visa_status'] ?? Passenger::STATUS_PENDING,
                    'sort_order' => $index,
                ]);
            }

            foreach ($transportItems as $item) {
                unset($item['pax_capacity']);
                GroupTransportItem::create([
                    ...$item,
                    'company_id' => $companyId,
                    'visa_group_id' => $group->id,
                ]);
            }

            $this->recalculateGroup($group->fresh());
            $this->postGroupSale($group->fresh());
            $this->postGroupCost($group->fresh());
            $this->recalculateAgent($group->agent_id);
            $this->recalculateGroupVendors($group->fresh());

            return $group->fresh(['agent', 'vendor', 'mandatoryTransportVendor', 'visaService', 'transportService', 'transportItems.transportVendor']);
        });
    }

    public function addPassenger(VisaGroup $group, array $data): Passenger
    {
        return DB::transaction(function () use ($group, $data) {
            $before = $group->only([
                'visa_sale_amount', 'transport_amount', 'discount_amount', 'total_receivable',
                'visa_cost_amount', 'transport_cost_amount',
            ]);
            $nationality = ! empty($data['nationality'])
                ? $data['nationality']
                : ($group->agent()->value('country') ?: 'Pakistan');

            $passenger = Passenger::create([
                'company_id' => $group->company_id,
                'visa_group_id' => $group->id,
                'full_name' => $data['full_name'],
                'passport_number' => $data['passport_number'] ?? null,
                'nationality' => $nationality,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'imported_age' => $data['imported_age'] ?? null,
                'service_type' => $data['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT,
                'transport_charge_amount' => round((float) ($data['transport_charge_amount'] ?? 0), 2),
                'visa_status' => $data['visa_status'] ?? Passenger::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
                'sort_order' => (int) $group->passengers()->count(),
            ]);

            if ($passenger->service_type === Passenger::SERVICE_TRANSPORT_ONLY) {
                $group->increment('transport_amount', (float) $passenger->transport_charge_amount);
            } elseif ($group->vendor_id) {
                $vendor = VisaVendor::where('company_id', $group->company_id)->find($group->vendor_id);
                if ($vendor) {
                    $pricing = $this->calculateVisaPricingFromVendor($vendor, [$passenger->toArray()], optional($group->travel_date)->toDateString(), 1);
                    $costDeduction = min($pricing['cost'], (float) $group->included_bus_cost_per_passenger);
                    $mandatoryTransportCost = $group->transport_mode === VisaGroup::TRANSPORT_STANDARD_BUS ? $costDeduction : 0;

                    $group->update([
                        'visa_sale_amount' => round((float) $group->visa_sale_amount + $pricing['sale'], 2),
                        'visa_cost_amount' => round((float) $group->visa_cost_amount + $pricing['cost'] - $costDeduction, 2),
                        'included_bus_cost_deduction' => round((float) $group->included_bus_cost_deduction + $costDeduction, 2),
                        'mandatory_transport_cost_amount' => round((float) $group->mandatory_transport_cost_amount + $mandatoryTransportCost, 2),
                        'transport_cost_amount' => round((float) $group->transport_cost_amount + $mandatoryTransportCost, 2),
                    ]);
                }
            }

            $this->recalculateGroup($group->fresh());
            $this->postGroupFinancialAdjustment($group->fresh(), $before, $data['override_reason'] ?? 'Passenger added');
            $this->recalculateAgent($group->agent_id);
            $this->recalculateGroupVendors($group->fresh());

            return $passenger;
        });
    }

    public function updatePassenger(VisaGroup $group, Passenger $passenger, array $data): Passenger
    {
        return DB::transaction(function () use ($group, $passenger, $data) {
            $group = VisaGroup::where('company_id', $group->company_id)->lockForUpdate()->findOrFail($group->id);
            $passenger = Passenger::where('company_id', $group->company_id)->where('visa_group_id', $group->id)->lockForUpdate()->findOrFail($passenger->id);
            $before = $group->only(['visa_sale_amount', 'transport_amount', 'discount_amount', 'total_receivable', 'visa_cost_amount', 'transport_cost_amount']);
            $old = $this->passengerFinancialContribution($group, $passenger->toArray());
            $passenger->update(collect($data)->except('override_reason')->all());
            $new = $this->passengerFinancialContribution($group, $passenger->fresh()->toArray());

            $group->update([
                'visa_sale_amount' => max(round((float) $group->visa_sale_amount - $old['visa_sale'] + $new['visa_sale'], 2), 0),
                'visa_cost_amount' => max(round((float) $group->visa_cost_amount - $old['visa_cost'] + $new['visa_cost'], 2), 0),
                'included_bus_cost_deduction' => max(round((float) $group->included_bus_cost_deduction - $old['bus_deduction'] + $new['bus_deduction'], 2), 0),
                'transport_amount' => max(round((float) $group->transport_amount - $old['transport_sale'] + $new['transport_sale'], 2), 0),
                'mandatory_transport_cost_amount' => max(round((float) $group->mandatory_transport_cost_amount - $old['mandatory_transport_cost'] + $new['mandatory_transport_cost'], 2), 0),
                'transport_cost_amount' => max(round((float) $group->transport_cost_amount - $old['mandatory_transport_cost'] + $new['mandatory_transport_cost'], 2), 0),
            ]);
            $this->recalculateGroup($group->fresh());
            $this->postGroupFinancialAdjustment($group->fresh(), $before, $data['override_reason'] ?? 'Passenger corrected');
            $this->recalculateAgent($group->agent_id);
            $this->recalculateGroupVendors($group->fresh());

            return $passenger->fresh();
        });
    }

    public function removePassenger(VisaGroup $group, Passenger $passenger, string $reason): void
    {
        DB::transaction(function () use ($group, $passenger, $reason) {
            $group = VisaGroup::where('company_id', $group->company_id)->lockForUpdate()->findOrFail($group->id);
            $passenger = Passenger::where('company_id', $group->company_id)->where('visa_group_id', $group->id)->lockForUpdate()->findOrFail($passenger->id);
            if (Voucher::where('company_id', $group->company_id)->where('visa_group_id', $group->id)
                ->where('status', Voucher::STATUS_APPROVED)->whereNull('superseded_at')
                ->whereHas('passengers', fn ($query) => $query->whereKey($passenger->id))->exists()) {
                throw ValidationException::withMessages(['passenger' => 'Cancel or amend the approved voucher before removing this passenger.']);
            }
            $before = $group->only(['visa_sale_amount', 'transport_amount', 'discount_amount', 'total_receivable', 'visa_cost_amount', 'transport_cost_amount']);
            $amounts = $this->passengerFinancialContribution($group, $passenger->toArray());
            VoucherPassenger::where('company_id', $group->company_id)->where('passenger_id', $passenger->id)->delete();
            $passenger->delete();
            $group->update([
                'visa_sale_amount' => max(round((float) $group->visa_sale_amount - $amounts['visa_sale'], 2), 0),
                'visa_cost_amount' => max(round((float) $group->visa_cost_amount - $amounts['visa_cost'], 2), 0),
                'included_bus_cost_deduction' => max(round((float) $group->included_bus_cost_deduction - $amounts['bus_deduction'], 2), 0),
                'transport_amount' => max(round((float) $group->transport_amount - $amounts['transport_sale'], 2), 0),
                'mandatory_transport_cost_amount' => max(round((float) $group->mandatory_transport_cost_amount - $amounts['mandatory_transport_cost'], 2), 0),
                'transport_cost_amount' => max(round((float) $group->transport_cost_amount - $amounts['mandatory_transport_cost'], 2), 0),
            ]);
            $this->recalculateGroup($group->fresh());
            $this->postGroupFinancialAdjustment($group->fresh(), $before, $reason);
            $this->recalculateAgent($group->agent_id);
            $this->recalculateGroupVendors($group->fresh());
        });
    }

    public function addPayment(string $companyId, array $data): GroupPayment
    {
        return DB::transaction(function () use ($companyId, $data) {
            $group = ! empty($data['visa_group_id'])
                ? VisaGroup::where('company_id', $companyId)->lockForUpdate()->findOrFail($data['visa_group_id'])
                : null;
            $amount = round((float) $data['amount'], 6);
            $company = $this->company($companyId);
            $currency = strtoupper($data['currency']);
            $exchangeRate = $currency === $company->base_currency ? null : (float) $data['exchange_rate'];
            $baseAmount = round($amount * ($exchangeRate ?? 1), 2);
            $direction = $data['direction'] ?? GroupPayment::DIRECTION_RECEIVED;

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payment amount must be greater than zero.']);
            }

            if ($group && $direction === GroupPayment::DIRECTION_RECEIVED && $group->agent_id !== $data['agent_id']) {
                throw ValidationException::withMessages(['visa_group_id' => 'Selected group does not belong to this agent.']);
            }

            $vendor = null;
            if ($direction === GroupPayment::DIRECTION_SENT) {
                $vendor = ! empty($data['visa_vendor_id'])
                    ? VisaVendor::where('company_id', $companyId)->lockForUpdate()->findOrFail($data['visa_vendor_id'])
                    : (! empty($data['transport_vendor_id'])
                        ? VisaVendor::where('company_id', $companyId)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->lockForUpdate()->findOrFail($data['transport_vendor_id'])
                        : HotelVendor::where('company_id', $companyId)->lockForUpdate()->findOrFail($data['hotel_vendor_id']));
            }

            $payment = GroupPayment::create([
                'company_id' => $companyId,
                'visa_group_id' => null,
                'agent_id' => $direction === GroupPayment::DIRECTION_RECEIVED ? $data['agent_id'] : null,
                'direction' => $direction,
                'visa_vendor_id' => $data['visa_vendor_id'] ?? null,
                'transport_vendor_id' => $data['transport_vendor_id'] ?? null,
                'hotel_vendor_id' => $data['hotel_vendor_id'] ?? null,
                'account_id' => $data['account_id'] ?? null,
                'payment_number' => $data['payment_number'] ?: $this->nextPaymentNumber($companyId),
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'base_currency' => $company->base_currency,
                'base_amount' => $baseAmount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($direction === GroupPayment::DIRECTION_RECEIVED) {
                $this->postAgentPayment($payment);
            } else {
                $this->postVendorPayment($payment->fresh(['visaVendor', 'transportVendor', 'hotelVendor']));
                $vendor->update([
                    'total_paid' => round((float) $vendor->total_paid + $baseAmount, 2),
                    'balance' => max(round((float) $vendor->balance - $baseAmount, 2), 0),
                ]);
            }

            $requestedAllocations = $data['allocations'] ?? [];
            if ($group && $requestedAllocations === []) {
                $allocationAmount = min($baseAmount, $this->allocationOutstanding($payment, $group));
                if ($allocationAmount > 0) {
                    $requestedAllocations[] = [
                        'visa_group_id' => $group->id,
                        'base_amount' => $allocationAmount,
                    ];
                }
            }
            foreach ($requestedAllocations as $allocation) {
                $this->allocatePayment($payment, $allocation);
            }
            if ($payment->agent_id) {
                $this->recalculateAgent($payment->agent_id);
            }

            return $payment->fresh(['allocations.group']);
        });
    }

    public function recalculateGroup(VisaGroup $group): VisaGroup
    {
        $passengerCount = $group->passengers()->count();
        $paid = (float) $group->paymentAllocations()
            ->whereHas('payment', fn ($query) => $query->where('direction', GroupPayment::DIRECTION_RECEIVED))
            ->sum('base_amount');
        $financials = $this->calculateGroupFinancials($group->toArray(), $paid);

        $group->update([
            'passenger_count' => $passengerCount,
            'total_receivable' => $financials['total_receivable'],
            'total_paid' => $paid,
            'balance' => $financials['balance'],
            'profit' => $financials['profit'],
        ]);

        return $group->fresh();
    }

    public function allocatePayment(GroupPayment $payment, array $data): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $data) {
            $payment = GroupPayment::where('company_id', $payment->company_id)->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== GroupPayment::STATUS_POSTED) {
                throw ValidationException::withMessages(['payment' => 'A reversed payment cannot be allocated.']);
            }
            $group = VisaGroup::where('company_id', $payment->company_id)->lockForUpdate()->findOrFail($data['visa_group_id']);
            $amount = round((float) $data['base_amount'], 2);
            $allocated = (float) $payment->allocations()->sum('base_amount');
            $available = round((float) $payment->base_amount - $allocated, 2);

            if ($payment->direction === GroupPayment::DIRECTION_RECEIVED && $group->agent_id !== $payment->agent_id) {
                throw ValidationException::withMessages(['visa_group_id' => 'Selected group does not belong to this agent.']);
            }
            $outstanding = $this->allocationOutstanding($payment, $group);
            if ($amount > $available + 0.01) {
                throw ValidationException::withMessages(['base_amount' => 'Allocation cannot exceed the available payment credit.']);
            }
            if ($amount > $outstanding + 0.01) {
                throw ValidationException::withMessages(['base_amount' => 'Allocation cannot exceed this party\'s outstanding amount for the group.']);
            }
            if ($payment->allocations()->where('visa_group_id', $group->id)->exists()) {
                throw ValidationException::withMessages(['visa_group_id' => 'This payment is already allocated to the selected group.']);
            }

            $allocation = PaymentAllocation::create([
                'company_id' => $payment->company_id,
                'group_payment_id' => $payment->id,
                'visa_group_id' => $group->id,
                'base_amount' => $amount,
            ]);
            $this->postPaymentAllocation($allocation->fresh(['payment', 'group']));
            $this->recalculateGroup($group->fresh());
            if ($payment->agent_id) {
                $this->recalculateAgent($payment->agent_id);
            }

            return $allocation->fresh(['group']);
        });
    }

    public function recalculateAgent(string $agentId): void
    {
        $agent = Agent::find($agentId);
        if (! $agent) {
            return;
        }

        $groups = VisaGroup::where('company_id', $agent->company_id)
            ->where('agent_id', $agent->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED);

        $agent->update([
            'total_receivable' => (float) (clone $groups)->sum('total_receivable'),
            'total_paid' => (float) (clone $groups)->sum('total_paid'),
            'balance' => (float) (clone $groups)->sum('balance'),
        ]);
    }

    public function recalculateVendor(string $vendorId): void
    {
        $vendor = VisaVendor::find($vendorId);
        if (! $vendor) {
            return;
        }

        $visaCost = VisaGroup::where('company_id', $vendor->company_id)
            ->where('vendor_id', $vendor->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->sum('visa_cost_amount');
        $mandatoryTransportCost = VisaGroup::where('company_id', $vendor->company_id)
            ->where('mandatory_transport_vendor_id', $vendor->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->sum('mandatory_transport_cost_amount');
        $specializedTransportCost = GroupTransportItem::where('company_id', $vendor->company_id)
            ->where('transport_vendor_id', $vendor->id)
            ->whereHas('group', fn ($query) => $query->where('status', '!=', VisaGroup::STATUS_CANCELLED))
            ->sum('total_cost_amount');
        $totalCost = round((float) $visaCost + (float) $mandatoryTransportCost + (float) $specializedTransportCost, 2);

        $vendor->update([
            'total_cost' => $totalCost,
            'total_paid' => (float) GroupPayment::where('company_id', $vendor->company_id)
                ->where(fn ($query) => $query->where('visa_vendor_id', $vendor->id)->orWhere('transport_vendor_id', $vendor->id))
                ->where('direction', GroupPayment::DIRECTION_SENT)
                ->where('status', GroupPayment::STATUS_POSTED)->sum('base_amount'),
        ]);
        $vendor->update(['balance' => round((float) $vendor->total_cost - (float) $vendor->total_paid, 2)]);
    }

    private function recalculateGroupVendors(VisaGroup $group): void
    {
        collect([
            $group->vendor_id,
            $group->mandatory_transport_vendor_id,
            ...$group->transportItems()->pluck('transport_vendor_id')->all(),
        ])->filter()->unique()->each(fn (string $vendorId) => $this->recalculateVendor($vendorId));
    }

    /**
     * @return array<int, array{id:string,party_key:string,group_number:string,name:string,outstanding_amount:float}>
     */
    public function paymentAllocationOptions(string $companyId): array
    {
        $groups = VisaGroup::where('company_id', $companyId)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->with(['transportItems:id,visa_group_id,transport_vendor_id,total_cost_amount', 'vouchers' => fn ($query) => $query
                ->where('status', Voucher::STATUS_APPROVED)
                ->whereNull('superseded_at')
                ->whereNull('billing_voucher_id')])
            ->orderBy('created_at')
            ->get(['id', 'agent_id', 'vendor_id', 'mandatory_transport_vendor_id', 'group_number', 'name', 'balance', 'visa_cost_amount', 'mandatory_transport_cost_amount']);

        $sentAllocations = PaymentAllocation::where('company_id', $companyId)
            ->whereNull('reversed_at')
            ->whereHas('payment', fn ($query) => $query->where('direction', GroupPayment::DIRECTION_SENT))
            ->with('payment:id,direction,visa_vendor_id,transport_vendor_id,hotel_vendor_id')
            ->get()
            ->groupBy(fn (PaymentAllocation $allocation) => $this->paymentPartyKey($allocation->payment).'|'.$allocation->visa_group_id)
            ->map(fn ($allocations) => (float) $allocations->sum('base_amount'));

        $options = collect();
        foreach ($groups as $group) {
            if ($group->agent_id && (float) $group->balance > 0.01) {
                $options->push($this->allocationOption($group, 'agent:'.$group->agent_id, (float) $group->balance));
            }

            if ($group->vendor_id) {
                $partyKey = 'visa:'.$group->vendor_id;
                $outstanding = max(round((float) $group->visa_cost_amount - (float) ($sentAllocations[$partyKey.'|'.$group->id] ?? 0), 2), 0);
                if ($outstanding > 0.01) {
                    $options->push($this->allocationOption($group, $partyKey, $outstanding));
                }
            }

            $transportCosts = $group->transportItems
                ->whereNotNull('transport_vendor_id')
                ->groupBy('transport_vendor_id')
                ->map(fn ($items) => (float) $items->sum('total_cost_amount'));
            if ($group->mandatory_transport_vendor_id && (float) $group->mandatory_transport_cost_amount > 0) {
                $transportCosts[$group->mandatory_transport_vendor_id] = round(
                    (float) ($transportCosts[$group->mandatory_transport_vendor_id] ?? 0) + (float) $group->mandatory_transport_cost_amount,
                    2,
                );
            }
            foreach ($transportCosts as $vendorId => $cost) {
                $partyKey = 'transport:'.$vendorId;
                $outstanding = max(round($cost - (float) ($sentAllocations[$partyKey.'|'.$group->id] ?? 0), 2), 0);
                if ($outstanding > 0.01) {
                    $options->push($this->allocationOption($group, $partyKey, $outstanding));
                }
            }

            $hotelCosts = collect($group->vouchers)
                ->flatMap(fn (Voucher $voucher) => $voucher->hotel_stays ?? [])
                ->filter(fn (array $stay) => ! empty($stay['hotel_vendor_id']) && (float) ($stay['total_cost_amount'] ?? 0) > 0)
                ->groupBy('hotel_vendor_id')
                ->map(fn ($stays) => (float) $stays->sum(fn (array $stay) => (float) $stay['total_cost_amount']));

            foreach ($hotelCosts as $vendorId => $cost) {
                $partyKey = 'hotel:'.$vendorId;
                $outstanding = max(round($cost - (float) ($sentAllocations[$partyKey.'|'.$group->id] ?? 0), 2), 0);
                if ($outstanding > 0.01) {
                    $options->push($this->allocationOption($group, $partyKey, $outstanding));
                }
            }
        }

        return $options->values()->all();
    }

    public function vendorStatement(VisaVendor $vendor, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $events = collect();
        $groups = VisaGroup::where('company_id', $vendor->company_id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->where(fn ($query) => $query
                ->where('vendor_id', $vendor->id)
                ->orWhere('mandatory_transport_vendor_id', $vendor->id))
            ->get(['id', 'group_number', 'name', 'travel_date', 'created_at', 'vendor_id', 'mandatory_transport_vendor_id', 'visa_cost_amount', 'mandatory_transport_cost_amount']);

        foreach ($groups as $group) {
            $date = $group->travel_date?->toDateString() ?? $group->created_at->toDateString();
            if ($group->vendor_id === $vendor->id && (float) $group->visa_cost_amount > 0) {
                $events->push($this->vendorStatementCostRow($date, $group, 'Visa cost', (float) $group->visa_cost_amount));
            }
            if ($group->mandatory_transport_vendor_id === $vendor->id && (float) $group->mandatory_transport_cost_amount > 0) {
                $events->push($this->vendorStatementCostRow($date, $group, 'Mandatory transport', (float) $group->mandatory_transport_cost_amount));
            }
        }

        GroupTransportItem::where('company_id', $vendor->company_id)
            ->where('transport_vendor_id', $vendor->id)
            ->whereHas('group', fn ($query) => $query->where('status', '!=', VisaGroup::STATUS_CANCELLED))
            ->with('group:id,group_number,name,travel_date,created_at')
            ->get(['id', 'visa_group_id', 'description', 'total_cost_amount', 'created_at'])
            ->each(function (GroupTransportItem $item) use ($events) {
                if ((float) $item->total_cost_amount <= 0 || ! $item->group) {
                    return;
                }
                $date = $item->group->travel_date?->toDateString() ?? $item->created_at->toDateString();
                $events->push($this->vendorStatementCostRow($date, $item->group, 'Transport: '.$item->description, (float) $item->total_cost_amount));
            });

        GroupPayment::where('company_id', $vendor->company_id)
            ->where('direction', GroupPayment::DIRECTION_SENT)
            ->where('status', GroupPayment::STATUS_POSTED)
            ->where(fn ($query) => $query->where('visa_vendor_id', $vendor->id)->orWhere('transport_vendor_id', $vendor->id))
            ->with('allocations:id,group_payment_id,base_amount')
            ->get()
            ->each(function (GroupPayment $payment) use ($events) {
                $allocated = round((float) $payment->allocations->sum('base_amount'), 2);
                $events->push([
                    'date' => $payment->payment_date->toDateString(),
                    'type' => 'payment',
                    'reference' => $payment->payment_number,
                    'description' => $payment->reference ?: 'Payment sent',
                    'charge' => 0.0,
                    'payment' => (float) $payment->base_amount,
                    'allocated' => $allocated,
                    'advance' => max(round((float) $payment->base_amount - $allocated, 2), 0),
                ]);
            });

        $events = $events->sortBy(fn (array $row) => $row['date'].'|'.($row['type'] === 'cost' ? '0' : '1').'|'.$row['reference'])->values();
        $opening = $dateFrom
            ? round((float) $events->filter(fn (array $row) => $row['date'] < $dateFrom)->sum(fn (array $row) => $row['charge'] - $row['payment']), 2)
            : 0.0;
        $rows = $events
            ->when($dateFrom, fn ($collection) => $collection->where('date', '>=', $dateFrom))
            ->when($dateTo, fn ($collection) => $collection->where('date', '<=', $dateTo));
        $running = $opening;
        $rows = $rows->map(function (array $row) use (&$running) {
            $running = round($running + $row['charge'] - $row['payment'], 2);

            return [...$row, 'balance' => $running];
        })->values();

        return [
            'opening_balance' => $opening,
            'charges' => round((float) $rows->sum('charge'), 2),
            'payments' => round((float) $rows->sum('payment'), 2),
            'closing_balance' => $running,
            'rows' => $rows->all(),
        ];
    }

    private function vendorStatementCostRow(string $date, VisaGroup $group, string $description, float $amount): array
    {
        return [
            'date' => $date,
            'type' => 'cost',
            'reference' => $group->group_number,
            'description' => $description.' - '.$group->name,
            'charge' => round($amount, 2),
            'payment' => 0.0,
            'allocated' => 0.0,
            'advance' => 0.0,
        ];
    }

    private function allocationOutstanding(GroupPayment $payment, VisaGroup $group): float
    {
        if ($group->status === VisaGroup::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['visa_group_id' => 'Payments cannot be allocated to a cancelled group.']);
        }

        if ($payment->direction === GroupPayment::DIRECTION_RECEIVED) {
            if ($group->agent_id !== $payment->agent_id) {
                throw ValidationException::withMessages(['visa_group_id' => 'Selected group does not belong to this agent.']);
            }

            return max((float) $group->balance, 0);
        }

        if ($payment->visa_vendor_id) {
            if ($group->vendor_id !== $payment->visa_vendor_id) {
                throw ValidationException::withMessages(['visa_group_id' => 'Selected group does not belong to this visa vendor.']);
            }
            $payable = (float) $group->visa_cost_amount;
        } elseif ($payment->transport_vendor_id) {
            $payable = (float) GroupTransportItem::where('company_id', $payment->company_id)
                ->where('visa_group_id', $group->id)
                ->where('transport_vendor_id', $payment->transport_vendor_id)
                ->sum('total_cost_amount');
            if ($group->mandatory_transport_vendor_id === $payment->transport_vendor_id) {
                $payable += (float) $group->mandatory_transport_cost_amount;
            }
            if ($payable <= 0) {
                throw ValidationException::withMessages(['visa_group_id' => 'Selected group has no transport cost for this vendor.']);
            }
        } else {
            $payable = Voucher::where('company_id', $payment->company_id)
                ->where('visa_group_id', $group->id)
                ->where('status', Voucher::STATUS_APPROVED)
                ->whereNull('superseded_at')
                ->whereNull('billing_voucher_id')
                ->get(['hotel_stays'])
                ->sum(fn (Voucher $voucher) => collect($voucher->hotel_stays)
                    ->where('hotel_vendor_id', $payment->hotel_vendor_id)
                    ->sum(fn (array $stay) => (float) ($stay['total_cost_amount'] ?? 0)));
            if ($payable <= 0) {
                throw ValidationException::withMessages(['visa_group_id' => 'Selected group has no approved hotel cost for this vendor.']);
            }
        }

        $allocated = PaymentAllocation::where('company_id', $payment->company_id)
            ->whereNull('reversed_at')
            ->where('visa_group_id', $group->id)
            ->whereHas('payment', fn ($query) => $query
                ->where('direction', GroupPayment::DIRECTION_SENT)
                ->when($payment->visa_vendor_id, fn ($party) => $party->where('visa_vendor_id', $payment->visa_vendor_id))
                ->when($payment->transport_vendor_id, fn ($party) => $party->where('transport_vendor_id', $payment->transport_vendor_id))
                ->when($payment->hotel_vendor_id, fn ($party) => $party->where('hotel_vendor_id', $payment->hotel_vendor_id)))
            ->sum('base_amount');

        return max(round($payable - (float) $allocated, 2), 0);
    }

    private function paymentPartyKey(GroupPayment $payment): string
    {
        if ($payment->agent_id) {
            return 'agent:'.$payment->agent_id;
        }

        if ($payment->visa_vendor_id) {
            return 'visa:'.$payment->visa_vendor_id;
        }

        return $payment->transport_vendor_id
            ? 'transport:'.$payment->transport_vendor_id
            : 'hotel:'.$payment->hotel_vendor_id;
    }

    /** @return array{id:string,party_key:string,group_number:string,name:string,outstanding_amount:float} */
    private function allocationOption(VisaGroup $group, string $partyKey, float $outstanding): array
    {
        return [
            'id' => $group->id,
            'party_key' => $partyKey,
            'group_number' => $group->group_number,
            'name' => $group->name,
            'outstanding_amount' => round($outstanding, 2),
        ];
    }

    private function calculateGroupFinancials(array $data, float $paid): array
    {
        $visaSale = round((float) ($data['visa_sale_amount'] ?? 0), 2);
        $transport = round((float) ($data['transport_amount'] ?? 0), 2);
        $hotel = round((float) ($data['hotel_amount'] ?? 0), 2);
        $discount = round((float) ($data['discount_amount'] ?? 0), 2);
        $visaCost = round((float) ($data['visa_cost_amount'] ?? 0), 2);
        $transportCost = round((float) ($data['transport_cost_amount'] ?? 0), 2);
        $hotelCost = round((float) ($data['hotel_cost_amount'] ?? 0), 2);
        $cost = round($visaCost + $transportCost + $hotelCost, 2);
        $receivable = max(round($visaSale + $transport + $hotel - $discount, 2), 0);

        return [
            'visa_sale_amount' => $visaSale,
            'transport_amount' => $transport,
            'hotel_amount' => $hotel,
            'discount_amount' => $discount,
            'visa_cost_amount' => $visaCost,
            'transport_cost_amount' => $transportCost,
            'hotel_cost_amount' => $hotelCost,
            'total_receivable' => $receivable,
            'balance' => max(round($receivable - $paid, 2), 0),
            'profit' => round($receivable - $cost, 2),
        ];
    }

    public function applyVoucherHotelAccounting(Voucher $voucher, VisaGroup $group): void
    {
        if ($voucher->status !== Voucher::STATUS_APPROVED || $voucher->billing_voucher_id || $voucher->hotel_sale_transaction_id || $voucher->hotel_cost_transaction_id) {
            return;
        }

        $group->update([
            'hotel_amount' => round((float) $group->hotel_amount + (float) $voucher->hotel_sale_amount, 2),
            'hotel_cost_amount' => round((float) $group->hotel_cost_amount + (float) $voucher->hotel_cost_amount, 2),
        ]);
        $this->recalculateGroup($group->fresh());
        $this->recalculateAgent($group->agent_id);

        collect($voucher->hotel_stays)
            ->filter(fn (array $stay) => ! empty($stay['hotel_vendor_id']) && (float) ($stay['total_cost_amount'] ?? 0) > 0)
            ->groupBy('hotel_vendor_id')
            ->each(function ($stays, string $vendorId) use ($group) {
                $vendor = HotelVendor::where('company_id', $group->company_id)->find($vendorId);
                if (! $vendor) {
                    return;
                }
                $addedCost = (float) $stays->sum(fn (array $stay) => (float) $stay['total_cost_amount']);
                $vendor->update(['total_cost' => round((float) $vendor->total_cost + $addedCost, 2), 'balance' => round((float) $vendor->balance + $addedCost, 2)]);
            });

        $company = $this->company($group->company_id);
        if ((float) $voucher->hotel_sale_amount > 0) {
            $transaction = $this->glPostingService->postBalancedTransaction([
                'company_id' => $group->company_id, 'transaction_number' => $this->transactionNumber('UHS', $voucher->id),
                'transaction_type' => 'umrah_hotel_sale', 'date' => Carbon::today(), 'currency' => $company->base_currency, 'base_currency' => $company->base_currency,
                'description' => "Hotel sale: {$voucher->voucher_number}", 'reference_type' => 'umrah.vouchers', 'reference_id' => $voucher->id,
                'metadata' => ['agent_id' => $group->agent_id, 'visa_group_id' => $group->id, 'voucher_number' => $voucher->voucher_number],
            ], [
                ['account_id' => $this->accountId($company, 'ar'), 'type' => 'debit', 'amount' => (float) $voucher->hotel_sale_amount, 'description' => "Hotel receivable {$voucher->voucher_number}"],
                ['account_id' => $this->accountId($company, 'hotel_revenue'), 'type' => 'credit', 'amount' => (float) $voucher->hotel_sale_amount, 'description' => "Hotel revenue {$voucher->voucher_number}"],
            ]);
            $voucher->update(['hotel_sale_transaction_id' => $transaction->id]);
        }
        if ((float) $voucher->hotel_cost_amount > 0) {
            $transaction = $this->glPostingService->postBalancedTransaction([
                'company_id' => $group->company_id, 'transaction_number' => $this->transactionNumber('UHC', $voucher->id),
                'transaction_type' => 'umrah_hotel_cost', 'date' => Carbon::today(), 'currency' => $company->base_currency, 'base_currency' => $company->base_currency,
                'description' => "Hotel cost: {$voucher->voucher_number}", 'reference_type' => 'umrah.vouchers', 'reference_id' => $voucher->id,
                'metadata' => ['agent_id' => $group->agent_id, 'visa_group_id' => $group->id, 'voucher_number' => $voucher->voucher_number],
            ], [
                ['account_id' => $this->accountId($company, 'hotel_cost'), 'type' => 'debit', 'amount' => (float) $voucher->hotel_cost_amount, 'description' => "Hotel cost {$voucher->voucher_number}"],
                ['account_id' => $this->accountId($company, 'ap'), 'type' => 'credit', 'amount' => (float) $voucher->hotel_cost_amount, 'description' => "Hotel payable {$voucher->voucher_number}"],
            ]);
            $voucher->update(['hotel_cost_transaction_id' => $transaction->id]);
        }
    }

    public function reverseVoucherHotelAccounting(Voucher $voucher, string $reason): void
    {
        if ($voucher->billing_voucher_id) {
            return;
        }

        $group = VisaGroup::where('company_id', $voucher->company_id)->lockForUpdate()->findOrFail($voucher->visa_group_id);
        foreach ([$voucher->hotel_sale_transaction_id, $voucher->hotel_cost_transaction_id] as $transactionId) {
            $transaction = $transactionId ? Transaction::where('company_id', $voucher->company_id)->find($transactionId) : null;
            if ($transaction) {
                $this->postingService->reverseTransaction($transaction, $reason, Carbon::today());
            }
        }
        $group->update([
            'hotel_amount' => max(round((float) $group->hotel_amount - (float) $voucher->hotel_sale_amount, 2), 0),
            'hotel_cost_amount' => max(round((float) $group->hotel_cost_amount - (float) $voucher->hotel_cost_amount, 2), 0),
        ]);
        $this->recalculateGroup($group->fresh());
        $this->recalculateAgent($group->agent_id);
        collect($voucher->hotel_stays)->pluck('hotel_vendor_id')->filter()->unique()
            ->each(fn (string $vendorId) => $this->recalculateHotelVendor($vendorId));
    }

    public function reversePayment(GroupPayment $payment, string $reason, ?string $userId): GroupPayment
    {
        return DB::transaction(function () use ($payment, $reason, $userId) {
            $payment = GroupPayment::where('company_id', $payment->company_id)->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status !== GroupPayment::STATUS_POSTED) {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            }
            $groupIds = [];
            foreach ($payment->allAllocations()->whereNull('reversed_at')->lockForUpdate()->get() as $allocation) {
                $transaction = $allocation->transaction_id ? Transaction::find($allocation->transaction_id) : null;
                $reversal = $transaction ? $this->postingService->reverseTransaction($transaction, $reason, Carbon::today()) : null;
                $allocation->update(['reversed_at' => now(), 'reversed_by_user_id' => $userId, 'reversal_reason' => $reason, 'reversal_transaction_id' => $reversal?->id]);
                $groupIds[] = $allocation->visa_group_id;
            }
            $transaction = $payment->transaction_id ? Transaction::find($payment->transaction_id) : null;
            $reversal = $transaction ? $this->postingService->reverseTransaction($transaction, $reason, Carbon::today()) : null;
            $payment->update(['status' => GroupPayment::STATUS_REVERSED, 'reversed_at' => now(), 'reversed_by_user_id' => $userId, 'reversal_reason' => $reason, 'reversal_transaction_id' => $reversal?->id]);
            VisaGroup::where('company_id', $payment->company_id)->whereIn('id', array_unique($groupIds))->get()
                ->each(fn (VisaGroup $group) => $this->recalculateGroup($group));
            if ($payment->agent_id) {
                $this->recalculateAgent($payment->agent_id);
            }
            if ($payment->visa_vendor_id) {
                $this->recalculateVendor($payment->visa_vendor_id);
            }
            if ($payment->transport_vendor_id) {
                $this->recalculateVendor($payment->transport_vendor_id);
            }
            if ($payment->hotel_vendor_id) {
                $this->recalculateHotelVendor($payment->hotel_vendor_id);
            }

            return $payment->fresh();
        });
    }

    public function recalculateHotelVendor(string $vendorId): void
    {
        $vendor = HotelVendor::find($vendorId);
        if (! $vendor) {
            return;
        }
        $cost = Voucher::where('company_id', $vendor->company_id)->where('status', Voucher::STATUS_APPROVED)
            ->whereNull('superseded_at')->whereNull('billing_voucher_id')->get(['hotel_stays'])
            ->sum(fn (Voucher $voucher) => collect($voucher->hotel_stays)->where('hotel_vendor_id', $vendor->id)->sum('total_cost_amount'));
        $paid = GroupPayment::where('company_id', $vendor->company_id)->where('hotel_vendor_id', $vendor->id)
            ->where('direction', GroupPayment::DIRECTION_SENT)->where('status', GroupPayment::STATUS_POSTED)->sum('base_amount');
        $vendor->update(['total_cost' => round((float) $cost, 2), 'total_paid' => round((float) $paid, 2), 'balance' => round((float) $cost - (float) $paid, 2)]);
    }

    private function passengerFinancialContribution(VisaGroup $group, array $passenger): array
    {
        if (($passenger['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT) === Passenger::SERVICE_TRANSPORT_ONLY) {
            return ['visa_sale' => 0.0, 'visa_cost' => 0.0, 'bus_deduction' => 0.0, 'mandatory_transport_cost' => 0.0, 'transport_sale' => round((float) ($passenger['transport_charge_amount'] ?? 0), 2)];
        }
        $vendor = $group->vendor_id ? VisaVendor::where('company_id', $group->company_id)->find($group->vendor_id) : null;
        if (! $vendor) {
            return ['visa_sale' => 0.0, 'visa_cost' => 0.0, 'bus_deduction' => 0.0, 'mandatory_transport_cost' => 0.0, 'transport_sale' => 0.0];
        }
        $pricing = $this->calculateVisaPricingFromVendor($vendor, [$passenger], optional($group->travel_date)->toDateString(), 1);
        $deduction = min($pricing['cost'], (float) $group->included_bus_cost_per_passenger);
        $mandatoryTransportCost = $group->transport_mode === VisaGroup::TRANSPORT_STANDARD_BUS ? $deduction : 0.0;

        return ['visa_sale' => $pricing['sale'], 'visa_cost' => round($pricing['cost'] - $deduction, 2), 'bus_deduction' => $deduction, 'mandatory_transport_cost' => $mandatoryTransportCost, 'transport_sale' => 0.0];
    }

    private function applyServiceDefaults(string $companyId, array $data): array
    {
        $data['transport_required'] = true;
        $data['transport_mode'] = $data['transport_mode'] ?? VisaGroup::TRANSPORT_STANDARD_BUS;
        $data['resolved_transport_items'] = [];
        $data['transport_amount'] = $this->transportOnlyPassengerCharges($data['passengers'] ?? []);
        $data['transport_cost_amount'] = 0;
        $data['included_bus_cost_deduction'] = 0;
        $data['mandatory_transport_cost_amount'] = 0;

        if (! empty($data['vendor_id'])) {
            $vendor = VisaVendor::where('company_id', $companyId)->find($data['vendor_id']);

            if ($vendor) {
                $pricing = $this->calculateVisaPricingFromVendor($vendor, $data['passengers'] ?? [], $data['travel_date'] ?? null, (int) ($data['passenger_count'] ?? 0));
                $data['visa_sale_amount'] = $pricing['sale'];
                $data['visa_cost_amount'] = $pricing['cost'];
                $data['included_bus_cost_per_passenger'] = (float) $vendor->included_bus_cost_amount;

                $replacement = $this->transportPricing->separateIncludedBusCost(
                    $pricing['cost'],
                    (float) $vendor->included_bus_cost_amount,
                    $pricing['passenger_count'],
                );
                $data['included_bus_cost_deduction'] = $replacement['deduction'];
                $data['visa_cost_amount'] = $replacement['adjusted_visa_cost'];

                if ($data['transport_mode'] === VisaGroup::TRANSPORT_STANDARD_BUS) {
                    $transportVendor = VisaVendor::where('company_id', $companyId)
                        ->where('is_active', true)
                        ->where(fn ($query) => $query->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orWhere('provides_mandatory_transport', true))
                        ->find($data['mandatory_transport_vendor_id'] ?? null);
                    if (! $transportVendor) {
                        throw ValidationException::withMessages(['mandatory_transport_vendor_id' => 'Configure the provider responsible for mandatory bus transport.']);
                    }
                    $data['mandatory_transport_cost_amount'] = $replacement['deduction'];
                    $data['transport_cost_amount'] = $replacement['deduction'];
                }
            }
        }

        if ($data['transport_mode'] === VisaGroup::TRANSPORT_SPECIALIZED) {
            $items = $this->resolveTransportItems($companyId, $data['transport_items'] ?? [], $data['passengers'] ?? [], (int) ($data['passenger_count'] ?? 0));
            $data['resolved_transport_items'] = $items;
            $data['transport_amount'] = round((float) $data['transport_amount'] + array_sum(array_column($items, 'total_sale_amount')), 2);
            $data['transport_cost_amount'] = round(array_sum(array_column($items, 'total_cost_amount')), 2);
        }

        return $data;
    }

    private function transportOnlyPassengerCharges(array $passengers): float
    {
        return round((float) collect($passengers)
            ->filter(fn (array $passenger) => trim((string) ($passenger['full_name'] ?? '')) !== '')
            ->filter(fn (array $passenger) => ($passenger['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT) === Passenger::SERVICE_TRANSPORT_ONLY)
            ->sum(fn (array $passenger) => (float) ($passenger['transport_charge_amount'] ?? 0)), 2);
    }

    private function resolveTransportItems(string $companyId, array $items, array $passengers, int $fallbackPassengerCount): array
    {
        $namedPassengerCount = count(array_filter($passengers, fn (array $passenger) => trim((string) ($passenger['full_name'] ?? '')) !== ''));
        $groupPassengerCount = max($namedPassengerCount, $fallbackPassengerCount);
        $resolved = [];

        foreach ($items as $item) {
            $fare = TransportFare::where('company_id', $companyId)
                ->where('is_active', true)
                ->with(['transportVendor', 'service', 'sector', 'package'])
                ->find($item['transport_fare_id']);

            if (! $fare || ! $fare->service || ! $fare->transportVendor || $fare->transportVendor->vendor_type !== VisaVendor::TYPE_TRANSPORT_PROVIDER) {
                throw ValidationException::withMessages(['transport_items' => 'A selected transport fare is no longer available.']);
            }

            $quantity = max((int) ($item['quantity'] ?? 1), 1);
            $passengerCount = max((int) ($item['passenger_count'] ?? $groupPassengerCount), 1);
            $isHajjTerminal = ($item['terminal'] ?? 'standard') === 'hajj';
            $totals = $this->transportPricing->fareTotals($fare, $quantity, $passengerCount, $isHajjTerminal);

            $resolved[] = [
                'transport_fare_id' => $fare->id,
                'transport_vendor_id' => $fare->transport_vendor_id,
                'transport_service_id' => $fare->transport_service_id,
                'transport_sector_id' => $fare->transport_sector_id,
                'transport_package_id' => $fare->transport_package_id,
                'driver_id' => $item['driver_id'] ?? $fare->service->driver_id,
                'description' => $fare->name,
                'scheduled_at' => $item['scheduled_at'] ?? null,
                'terminal' => $isHajjTerminal ? 'hajj' : 'standard',
                'charging_basis' => $fare->charging_basis,
                'quantity' => $quantity,
                'passenger_count' => $passengerCount,
                'unit_sale_amount' => (float) $fare->sale_amount,
                'unit_cost_amount' => (float) $fare->cost_amount,
                'surcharge_sale_amount' => $totals['surcharge_sale_amount'],
                'surcharge_cost_amount' => $totals['surcharge_cost_amount'],
                'total_sale_amount' => $totals['total_sale_amount'],
                'total_cost_amount' => $totals['total_cost_amount'],
                'notes' => $item['notes'] ?? null,
                'pax_capacity' => $fare->service->pax_capacity,
            ];
        }

        return $resolved;
    }

    private function calculateVisaPricingFromVendor(VisaVendor $vendor, array $passengers, ?string $travelDate, int $passengerCount): array
    {
        $sale = 0.0;
        $cost = 0.0;
        $pricedPassengers = 0;
        $namedPassengers = 0;

        foreach ($passengers as $passenger) {
            if (! trim((string) ($passenger['full_name'] ?? ''))) {
                continue;
            }

            $namedPassengers++;

            if (($passenger['service_type'] ?? Passenger::SERVICE_VISA_TRANSPORT) === Passenger::SERVICE_TRANSPORT_ONLY) {
                continue;
            }

            $band = $this->ageBand($passenger['date_of_birth'] ?? null, $travelDate, $passenger['imported_age'] ?? null);
            $sale += (float) $vendor->getAttribute("{$band}_retail_amount");
            $cost += (float) $vendor->getAttribute("{$band}_cost_amount");
            $pricedPassengers++;
        }

        if ($namedPassengers === 0) {
            $pricedPassengers = max($passengerCount, 0);
            $sale = (float) $vendor->adult_retail_amount * $pricedPassengers;
            $cost = (float) $vendor->adult_cost_amount * $pricedPassengers;
        }

        return [
            'sale' => round($sale, 2),
            'cost' => round($cost, 2),
            'passenger_count' => $pricedPassengers,
        ];
    }

    private function ageBand(?string $dateOfBirth, ?string $travelDate, mixed $importedAge = null): string
    {
        if (empty($dateOfBirth)) {
            $age = is_numeric($importedAge) ? (int) $importedAge : null;

            return $age !== null && $age < 12 ? 'child' : 'adult';
        }

        $birthDate = Carbon::parse($dateOfBirth)->startOfDay();
        $referenceDate = empty($travelDate) ? now()->startOfDay() : Carbon::parse($travelDate)->startOfDay();
        $age = $birthDate->diffInYears($referenceDate);

        if ($age < 12) {
            return 'child';
        }

        return 'adult';
    }

    private function defaultGroupName(string $companyId, string $agentId, int $passengerCount): string
    {
        $agentName = Agent::where('company_id', $companyId)
            ->whereKey($agentId)
            ->value('name') ?: 'Umrah Group';

        return sprintf('%s - %d pax - %s', $agentName, $passengerCount, now()->format('Ymd His'));
    }

    private function nextNumber(string $companyId, $query, string $column, string $prefix): string
    {
        $latest = $query
            ->where('company_id', $companyId)
            ->where($column, 'like', "{$prefix}-%")
            ->orderByDesc($column)
            ->value($column);

        $next = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%05d', $prefix, $next);
    }

    private function postGroupSale(VisaGroup $group): void
    {
        if ($group->sale_transaction_id || (float) $group->total_receivable <= 0) {
            return;
        }

        $company = $this->company($group->company_id);
        $arAccountId = $this->accountId($company, 'ar');
        $visaRevenueAccountId = $this->accountId($company, 'visa_revenue');
        $transportRevenueAccountId = $this->accountId($company, 'transport_revenue');

        [$netVisaRevenue, $netTransportRevenue] = $this->netRevenueAmounts($group);

        $entries = [[
            'account_id' => $arAccountId,
            'type' => 'debit',
            'amount' => (float) $group->total_receivable,
            'description' => "Agent receivable for {$group->group_number}",
        ]];

        if ($netVisaRevenue > 0) {
            $entries[] = [
                'account_id' => $visaRevenueAccountId,
                'type' => 'credit',
                'amount' => $netVisaRevenue,
                'description' => "Visa revenue for {$group->group_number}",
            ];
        }

        if ($netTransportRevenue > 0) {
            $entries[] = [
                'account_id' => $transportRevenueAccountId,
                'type' => 'credit',
                'amount' => $netTransportRevenue,
                'description' => "Transport revenue for {$group->group_number}",
            ];
        }

        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $group->company_id,
            'transaction_number' => $this->transactionNumber('UVS', $group->id),
            'transaction_type' => 'umrah_group_sale',
            'date' => Carbon::today(),
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => "Umrah group sale: {$group->group_number} - {$group->name}",
            'reference_type' => 'umrah.visa_groups',
            'reference_id' => $group->id,
            'metadata' => [
                'agent_id' => $group->agent_id,
                'group_number' => $group->group_number,
                'visa_sale_amount' => (float) $group->visa_sale_amount,
                'transport_amount' => (float) $group->transport_amount,
                'discount_amount' => (float) $group->discount_amount,
            ],
        ], $entries);

        $group->update(['sale_transaction_id' => $transaction->id]);
    }

    private function postGroupCost(VisaGroup $group): void
    {
        $visaCost = (float) $group->visa_cost_amount;
        $transportCost = (float) $group->transport_cost_amount;
        $totalCost = round($visaCost + $transportCost, 2);

        if ($group->cost_transaction_id || $totalCost <= 0) {
            return;
        }

        $company = $this->company($group->company_id);
        $visaCostAccountId = $this->accountId($company, 'visa_cost');
        $transportCostAccountId = $this->accountId($company, 'transport_cost');
        $apAccountId = $this->accountId($company, 'ap');

        $entries = [];

        if ($visaCost > 0) {
            $entries[] = [
                'account_id' => $visaCostAccountId,
                'type' => 'debit',
                'amount' => $visaCost,
                'description' => "Visa cost for {$group->group_number}",
            ];
        }

        if ($transportCost > 0) {
            $entries[] = [
                'account_id' => $transportCostAccountId,
                'type' => 'debit',
                'amount' => $transportCost,
                'description' => "Transport cost for {$group->group_number}",
            ];
        }

        $entries[] = [
            'account_id' => $apAccountId,
            'type' => 'credit',
            'amount' => $totalCost,
            'description' => "Vendor payable for {$group->group_number}",
        ];

        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $group->company_id,
            'transaction_number' => $this->transactionNumber('UVC', $group->id),
            'transaction_type' => 'umrah_visa_cost',
            'date' => Carbon::today(),
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => "Umrah visa cost: {$group->group_number} - {$group->name}",
            'reference_type' => 'umrah.visa_groups',
            'reference_id' => $group->id,
            'metadata' => [
                'agent_id' => $group->agent_id,
                'vendor_id' => $group->vendor_id,
                'mandatory_transport_vendor_id' => $group->mandatory_transport_vendor_id,
                'group_number' => $group->group_number,
                'visa_cost_amount' => $visaCost,
                'transport_cost_amount' => $transportCost,
                'mandatory_transport_cost_amount' => (float) $group->mandatory_transport_cost_amount,
                'transport_supplier_costs' => $group->transportItems()
                    ->whereNotNull('transport_vendor_id')
                    ->selectRaw('transport_vendor_id, SUM(total_cost_amount) AS total_cost')
                    ->groupBy('transport_vendor_id')
                    ->pluck('total_cost', 'transport_vendor_id')
                    ->map(fn ($amount) => (float) $amount)
                    ->all(),
            ],
        ], $entries);

        $group->update(['cost_transaction_id' => $transaction->id]);
    }

    private function postAgentPayment(GroupPayment $payment): void
    {
        if ($payment->transaction_id) {
            return;
        }

        $company = $this->company($payment->company_id);
        $depositAccountId = $payment->account_id ?: $this->accountId($company, 'bank_or_cash');
        $advanceAccountId = $this->accountId($company, 'agent_advances');

        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $payment->company_id,
            'transaction_number' => $this->transactionNumber('UPY', $payment->id),
            'transaction_type' => 'umrah_agent_payment',
            'date' => $payment->payment_date,
            'currency' => $payment->currency,
            'base_currency' => $company->base_currency,
            'exchange_rate' => $payment->exchange_rate,
            'description' => "Agent payment {$payment->payment_number}",
            'reference_type' => 'umrah.group_payments',
            'reference_id' => $payment->id,
            'metadata' => [
                'agent_id' => $payment->agent_id,
                'payment_number' => $payment->payment_number,
                'method' => $payment->method,
            ],
        ], [
            [
                'account_id' => $depositAccountId,
                'type' => 'debit',
                'amount' => (float) $payment->amount,
                'description' => "Payment received {$payment->payment_number}",
            ],
            [
                'account_id' => $advanceAccountId,
                'type' => 'credit',
                'amount' => (float) $payment->amount,
                'description' => "Agent advance {$payment->payment_number}",
            ],
        ]);

        $payment->update(['transaction_id' => $transaction->id]);
    }

    private function postVendorPayment(GroupPayment $payment): void
    {
        if ($payment->transaction_id) {
            return;
        }

        $company = $this->company($payment->company_id);
        $cashAccountId = $payment->account_id ?: $this->accountId($company, 'bank_or_cash');
        $payee = $payment->visaVendor?->name ?: $payment->transportVendor?->name ?: $payment->hotelVendor?->name ?: 'Vendor';
        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $payment->company_id,
            'transaction_number' => $this->transactionNumber('UVP', $payment->id),
            'transaction_type' => 'umrah_vendor_payment',
            'date' => $payment->payment_date,
            'currency' => $payment->currency,
            'base_currency' => $company->base_currency,
            'exchange_rate' => $payment->exchange_rate,
            'description' => "Vendor payment {$payment->payment_number}: {$payee}",
            'reference_type' => 'umrah.group_payments',
            'reference_id' => $payment->id,
            'metadata' => [
                'visa_vendor_id' => $payment->visa_vendor_id,
                'transport_vendor_id' => $payment->transport_vendor_id,
                'hotel_vendor_id' => $payment->hotel_vendor_id,
                'payment_number' => $payment->payment_number,
                'method' => $payment->method,
            ],
        ], [
            ['account_id' => $this->accountId($company, 'vendor_advances'), 'type' => 'debit', 'amount' => (float) $payment->amount, 'description' => "Advance paid to {$payee}"],
            ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => (float) $payment->amount, 'description' => "Payment sent {$payment->payment_number}"],
        ]);

        $payment->update(['transaction_id' => $transaction->id]);
    }

    private function postPaymentAllocation(PaymentAllocation $allocation): void
    {
        if ($allocation->transaction_id) {
            return;
        }

        $payment = $allocation->payment;
        $company = $this->company($allocation->company_id);
        $received = $payment->direction === GroupPayment::DIRECTION_RECEIVED;
        $transaction = $this->glPostingService->postBalancedTransaction([
            'company_id' => $allocation->company_id,
            'transaction_number' => $this->transactionNumber('UAL', $allocation->id),
            'transaction_type' => 'umrah_payment_allocation',
            'date' => $payment->payment_date,
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => "Allocate {$payment->payment_number} to {$allocation->group->group_number}",
            'reference_type' => 'umrah.payment_allocations',
            'reference_id' => $allocation->id,
            'metadata' => ['group_payment_id' => $payment->id, 'visa_group_id' => $allocation->visa_group_id],
        ], $received ? [
            ['account_id' => $this->accountId($company, 'agent_advances'), 'type' => 'debit', 'amount' => (float) $allocation->base_amount, 'description' => 'Apply agent advance'],
            ['account_id' => $this->accountId($company, 'ar'), 'type' => 'credit', 'amount' => (float) $allocation->base_amount, 'description' => 'Reduce group receivable'],
        ] : [
            ['account_id' => $this->accountId($company, 'ap'), 'type' => 'debit', 'amount' => (float) $allocation->base_amount, 'description' => 'Reduce vendor payable'],
            ['account_id' => $this->accountId($company, 'vendor_advances'), 'type' => 'credit', 'amount' => (float) $allocation->base_amount, 'description' => 'Apply vendor advance'],
        ]);

        $allocation->update(['transaction_id' => $transaction->id]);
    }

    /**
     * @return array{0:float,1:float}
     */
    private function netRevenueAmounts(VisaGroup $group): array
    {
        $visa = (float) $group->visa_sale_amount;
        $transport = (float) $group->transport_amount;
        $discount = (float) $group->discount_amount;

        $netVisa = max(round($visa - min($discount, $visa), 2), 0);
        $remainingDiscount = max(round($discount - $visa, 2), 0);
        $netTransport = max(round($transport - $remainingDiscount, 2), 0);

        return [$netVisa, $netTransport];
    }

    public function postGroupFinancialAdjustment(VisaGroup $group, array $before, string $reason): void
    {
        $company = $this->company($group->company_id);
        [$oldVisaRevenue, $oldTransportRevenue] = $this->netRevenueFromValues($before);
        [$newVisaRevenue, $newTransportRevenue] = $this->netRevenueAmounts($group);
        $saleDeltas = [
            ['account_id' => $this->accountId($company, 'ar'), 'delta' => (float) $group->total_receivable - (float) $before['total_receivable'], 'positive_type' => 'debit', 'description' => "Receivable adjustment for {$group->group_number}"],
            ['account_id' => $this->accountId($company, 'visa_revenue'), 'delta' => $newVisaRevenue - $oldVisaRevenue, 'positive_type' => 'credit', 'description' => "Visa revenue adjustment for {$group->group_number}"],
            ['account_id' => $this->accountId($company, 'transport_revenue'), 'delta' => $newTransportRevenue - $oldTransportRevenue, 'positive_type' => 'credit', 'description' => "Transport revenue adjustment for {$group->group_number}"],
        ];
        $this->postAdjustmentTransaction($group, 'UGA', 'umrah_group_sale_adjustment', $reason, $saleDeltas);

        $costDeltas = [
            ['account_id' => $this->accountId($company, 'visa_cost'), 'delta' => (float) $group->visa_cost_amount - (float) $before['visa_cost_amount'], 'positive_type' => 'debit', 'description' => "Visa cost adjustment for {$group->group_number}"],
            ['account_id' => $this->accountId($company, 'transport_cost'), 'delta' => (float) $group->transport_cost_amount - (float) $before['transport_cost_amount'], 'positive_type' => 'debit', 'description' => "Transport cost adjustment for {$group->group_number}"],
            ['account_id' => $this->accountId($company, 'ap'), 'delta' => ((float) $group->visa_cost_amount + (float) $group->transport_cost_amount) - ((float) $before['visa_cost_amount'] + (float) $before['transport_cost_amount']), 'positive_type' => 'credit', 'description' => "Payable adjustment for {$group->group_number}"],
        ];
        $this->postAdjustmentTransaction($group, 'UGC', 'umrah_group_cost_adjustment', $reason, $costDeltas);
    }

    private function postAdjustmentTransaction(VisaGroup $group, string $prefix, string $type, string $reason, array $deltas): void
    {
        $entries = collect($deltas)
            ->filter(fn (array $line) => abs(round((float) $line['delta'], 2)) >= 0.01)
            ->map(function (array $line) {
                $positive = (float) $line['delta'] > 0;
                $type = $positive
                    ? $line['positive_type']
                    : ($line['positive_type'] === 'debit' ? 'credit' : 'debit');

                return [
                    'account_id' => $line['account_id'],
                    'type' => $type,
                    'amount' => abs(round((float) $line['delta'], 2)),
                    'description' => $line['description'],
                ];
            })
            ->values()
            ->all();

        if ($entries === []) {
            return;
        }

        $company = $this->company($group->company_id);
        $this->glPostingService->postBalancedTransaction([
            'company_id' => $group->company_id,
            'transaction_number' => $this->transactionNumber($prefix, (string) Str::uuid()),
            'transaction_type' => $type,
            'date' => Carbon::today(),
            'currency' => $company->base_currency,
            'base_currency' => $company->base_currency,
            'description' => "{$reason}: {$group->group_number}",
            'reference_type' => 'umrah.visa_groups',
            'reference_id' => $group->id,
            'metadata' => ['group_number' => $group->group_number, 'reason' => $reason],
        ], $entries);
    }

    private function netRevenueFromValues(array $values): array
    {
        $visa = (float) ($values['visa_sale_amount'] ?? 0);
        $transport = (float) ($values['transport_amount'] ?? 0);
        $discount = (float) ($values['discount_amount'] ?? 0);
        $netVisa = max(round($visa - min($discount, $visa), 2), 0);
        $remainingDiscount = max(round($discount - $visa, 2), 0);

        return [$netVisa, max(round($transport - $remainingDiscount, 2), 0)];
    }

    private function accountId(Company $company, string $role): string
    {
        $query = Account::where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        $accountId = match ($role) {
            'ar' => $company->ar_account_id ?: (clone $query)->where('subtype', 'accounts_receivable')->orderBy('code')->value('id'),
            'ap' => $company->ap_account_id ?: (clone $query)->where('subtype', 'accounts_payable')->orderBy('code')->value('id'),
            'visa_revenue' => (clone $query)->where('code', '4100')->value('id')
                ?: $company->income_account_id
                ?: (clone $query)->where('type', 'revenue')->orderBy('code')->value('id'),
            'transport_revenue' => (clone $query)->where('code', '4110')->value('id')
                ?: (clone $query)->where('type', 'revenue')->orderBy('code')->value('id'),
            'hotel_revenue' => (clone $query)->where('code', '4120')->value('id') ?: (clone $query)->where('type', 'revenue')->orderBy('code')->value('id'),
            'visa_cost' => (clone $query)->where('code', '5100')->value('id')
                ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id')
                ?: $company->expense_account_id,
            'transport_cost' => (clone $query)->where('code', '5110')->value('id')
                ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id')
                ?: $company->expense_account_id,
            'hotel_cost' => (clone $query)->where('code', '5120')->value('id') ?: (clone $query)->where('type', 'cogs')->orderBy('code')->value('id') ?: $company->expense_account_id,
            'bank_or_cash' => $company->bank_account_id
                ?: (clone $query)->whereIn('subtype', ['bank', 'cash'])->orderByRaw("CASE WHEN subtype = 'bank' THEN 0 ELSE 1 END")->orderBy('code')->value('id'),
            'agent_advances' => (clone $query)->where('code', '2200')->value('id')
                ?: (clone $query)->where('code', '2270')->value('id'),
            'vendor_advances' => (clone $query)->where('code', '1160')->value('id')
                ?: (clone $query)->where('type', 'asset')->where('subtype', 'other_current_asset')->orderBy('code')->value('id'),
            default => null,
        };

        if (! $accountId) {
            throw ValidationException::withMessages([
                'accounts' => "Required accounting account is missing for {$role}. Review the Umrah company chart of accounts.",
            ]);
        }

        return $accountId;
    }

    private function company(string $companyId): Company
    {
        return Company::findOrFail($companyId);
    }

    private function transactionNumber(string $prefix, string $id): string
    {
        return $prefix.'-'.strtoupper(substr(str_replace('-', '', $id), -12));
    }
}
