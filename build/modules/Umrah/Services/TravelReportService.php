<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Passenger;
use App\Modules\Umrah\Models\PaymentAllocation;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TravelReportService
{
    public const REPORTS = [
        'group-profitability' => ['title' => 'Group Profitability', 'description' => 'Revenue, direct costs, collections, and gross contribution by trip.', 'date_basis' => 'Service date'],
        'agent-statement' => ['title' => 'Agent Statement', 'description' => 'Group charges, receipt allocations, advances, and closing receivable.', 'date_basis' => 'Posting and payment date'],
        'receivable-aging' => ['title' => 'Receivable Aging', 'description' => 'Outstanding agent balances grouped by age.', 'date_basis' => 'Service date as of report end'],
        'vendor-aging' => ['title' => 'Vendor Payable Aging', 'description' => 'Outstanding visa, transport, and hotel supplier costs.', 'date_basis' => 'Service date as of report end'],
        'advances' => ['title' => 'Advances and Allocations', 'description' => 'Unallocated and partially allocated agent receipts and supplier payments.', 'date_basis' => 'Payment date'],
        'passenger-status' => ['title' => 'Passenger and Visa Status', 'description' => 'Passenger identity, visa status, service type, and travel date.', 'date_basis' => 'Travel date'],
        'departure-manifest' => ['title' => 'Departure Manifest', 'description' => 'Passenger manifest grouped by departing flight.', 'date_basis' => 'Onward departure'],
        'hotel-rooming' => ['title' => 'Hotel Rooming List', 'description' => 'Hotel stays, room requirements, and passenger names.', 'date_basis' => 'Check-in date'],
        'transport-dispatch' => ['title' => 'Transport Dispatch', 'description' => 'Scheduled vehicles, routes, passenger loads, and drivers.', 'date_basis' => 'Transport schedule'],
        'voucher-control' => ['title' => 'Voucher Control', 'description' => 'Drafts, approvals, cancellations, and approaching service deadlines.', 'date_basis' => 'Departure or first check-in'],
    ];

    public function __construct(private TravelAccessService $access) {}

    public function build(Company $company, ?User $user, string $report, array $filters, bool $forPdf = false): array
    {
        $isAgent = $this->access->isAgentMember($company->id, $user);
        $linkedAgent = $isAgent ? $this->access->linkedAgent($company->id, $user) : null;
        if ($isAgent) {
            $filters['agent_id'] = $linkedAgent?->id ?: '__none__';
        }

        $result = match ($report) {
            'group-profitability' => $this->groupProfitability($company, $filters),
            'agent-statement' => $this->agentStatement($company, $filters),
            'receivable-aging' => $this->receivableAging($company, $filters),
            'vendor-aging' => $this->vendorAging($company, $filters),
            'advances' => $this->advances($company, $filters),
            'passenger-status' => $this->passengerStatus($company, $filters),
            'departure-manifest' => $this->departureManifest($company, $filters),
            'hotel-rooming' => $this->hotelRooming($company, $filters, $isAgent),
            'transport-dispatch' => $this->transportDispatch($company, $filters),
            'voucher-control' => $this->voucherControl($company, $filters),
        };

        $rows = collect($result['rows'])->values();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = max((int) ($filters['page'] ?? 1), 1);
        $result['rows'] = $forPdf ? $rows->all() : $rows->forPage($page, $perPage)->values()->all();
        $result['pagination'] = $forPdf ? null : [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $rows->count(),
            'last_page' => max((int) ceil($rows->count() / $perPage), 1),
        ];

        return [
            ...self::REPORTS[$report],
            'key' => $report,
            'filters' => $filters,
            'filter_definitions' => $this->filterDefinitions($company, $report, $isAgent),
            ...$result,
        ];
    }

    private function groupProfitability(Company $company, array $filters): array
    {
        $groups = $this->filteredGroups($company, $filters, true)
            ->filter(fn (VisaGroup $group) => $this->matchesGroupFilters($group, $filters));

        $rows = $groups->map(function (VisaGroup $group): array {
            $allocated = $this->receivedAllocationsForGroup($group);
            $revenue = (float) $group->visa_sale_amount + (float) $group->transport_amount + (float) $group->hotel_amount - (float) $group->discount_amount;
            $cost = (float) $group->visa_cost_amount + (float) $group->transport_cost_amount + (float) $group->hotel_cost_amount;

            return [
                'href' => '/umrah/groups/'.$group->id,
                'group' => $group->group_number,
                'name' => $group->name,
                'date' => $this->groupServiceDate($group)?->toDateString(),
                'agent' => $group->agent?->name,
                'pax' => (int) $group->passenger_count,
                'visa_revenue' => (float) $group->visa_sale_amount,
                'visa_cost' => (float) $group->visa_cost_amount,
                'transport_revenue' => (float) $group->transport_amount,
                'transport_cost' => (float) $group->transport_cost_amount,
                'hotel_revenue' => (float) $group->hotel_amount,
                'hotel_cost' => (float) $group->hotel_cost_amount,
                'discount' => (float) $group->discount_amount,
                'revenue' => round($revenue, 2),
                'cost' => round($cost, 2),
                'gross_contribution' => round($revenue - $cost, 2),
                'allocated' => $allocated,
                'balance' => round($revenue - $allocated, 2),
            ];
        })->sortByDesc('date')->values();

        return [
            'summary' => $this->moneySummary($company, [
                'Groups' => $rows->count(),
                'Revenue' => $rows->sum('revenue'),
                'Direct cost' => $rows->sum('cost'),
                'Gross contribution' => $rows->sum('gross_contribution'),
                'Allocated receipts' => $rows->sum('allocated'),
                'Period group balance' => $rows->sum('balance'),
            ], ['Groups']),
            'columns' => [
                $this->column('group', 'Group'), $this->column('date', 'Service Date', 'date'), $this->column('agent', 'Agent'), $this->column('pax', 'Pax', 'number'),
                $this->column('visa_revenue', 'Visa Revenue', 'money'), $this->column('visa_cost', 'Visa Cost', 'money'),
                $this->column('transport_revenue', 'Transport Revenue', 'money'), $this->column('transport_cost', 'Transport Cost', 'money'),
                $this->column('hotel_revenue', 'Hotel Revenue', 'money'), $this->column('hotel_cost', 'Hotel Cost', 'money'),
                $this->column('discount', 'Discount', 'money'), $this->column('revenue', 'Total Revenue', 'money'),
                $this->column('cost', 'Direct Cost', 'money'), $this->column('gross_contribution', 'Gross Contribution', 'money'),
                $this->column('allocated', 'Allocated', 'money'), $this->column('balance', 'Balance', 'money'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function agentStatement(Company $company, array $filters): array
    {
        $start = CarbonImmutable::parse($filters['start'])->startOfDay();
        $end = CarbonImmutable::parse($filters['end'])->endOfDay();
        $agentId = $filters['agent_id'] ?? null;
        $groups = VisaGroup::where('company_id', $company->id)
            ->when($agentId, fn ($query) => $query->where('agent_id', $agentId))
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->with(['agent:id,name', 'saleTransaction:id,transaction_date,posting_date'])
            ->get();

        $events = collect();
        foreach ($groups as $group) {
            $date = $group->saleTransaction?->posting_date ?? $group->saleTransaction?->transaction_date ?? $group->created_at;
            $events->push([
                'date' => CarbonImmutable::parse($date)->toDateString(), 'sort_at' => CarbonImmutable::parse($date), 'type' => 'charge',
                'party' => $group->agent?->name, 'reference' => $group->group_number, 'description' => $group->name,
                'charge' => (float) $group->total_receivable, 'receipt' => 0.0, 'advance' => 0.0,
            ]);
        }

        $payments = GroupPayment::where('company_id', $company->id)
            ->where('direction', GroupPayment::DIRECTION_RECEIVED)
            ->when($agentId, fn ($query) => $query->where('agent_id', $agentId))
            ->with(['agent:id,name', 'allAllocations.group:id,group_number'])
            ->get();
        foreach ($payments as $payment) {
            $allocated = (float) $payment->allAllocations->sum('base_amount');
            $advance = max((float) $payment->base_amount - $allocated, 0);
            $events->push([
                'date' => $payment->payment_date->toDateString(), 'sort_at' => CarbonImmutable::parse($payment->payment_date),
                'type' => $payment->status === GroupPayment::STATUS_REVERSED ? 'reversal' : 'allocation',
                'party' => $payment->agent?->name, 'reference' => $payment->payment_number,
                'description' => $payment->status === GroupPayment::STATUS_REVERSED ? 'Reversed receipt' : 'Receipt'.($allocated > 0 ? ' allocated to groups' : ''),
                'charge' => 0.0, 'receipt' => $payment->status === GroupPayment::STATUS_POSTED ? $allocated : 0.0,
                'advance' => $payment->status === GroupPayment::STATUS_POSTED ? $advance : 0.0,
            ]);
        }

        $opening = $events->filter(fn ($row) => $row['sort_at']->lt($start))->sum(fn ($row) => $row['charge'] - $row['receipt']);
        $running = round((float) $opening, 2);
        $rows = $events->filter(fn ($row) => $row['sort_at']->betweenIncluded($start, $end))
            ->when($filters['transaction_type'] ?? null, fn (Collection $rows, string $type) => $rows->where('type', $type))
            ->sortBy([['sort_at', 'asc'], ['reference', 'asc']])
            ->map(function (array $row) use (&$running): array {
                $running = round($running + $row['charge'] - $row['receipt'], 2);
                unset($row['sort_at']);

                return [...$row, 'balance' => $running];
            })->values();

        return [
            'summary' => $this->moneySummary($company, [
                'Opening receivable' => $opening,
                'Charges' => $rows->sum('charge'),
                'Allocated receipts' => $rows->sum('receipt'),
                'Available advances' => $payments->where('status', GroupPayment::STATUS_POSTED)->sum(fn ($payment) => max((float) $payment->base_amount - (float) $payment->allocations->sum('base_amount'), 0)),
                'Closing receivable' => $running,
            ]),
            'columns' => [
                $this->column('date', 'Date', 'date'), $this->column('party', 'Agent'), $this->column('reference', 'Reference'),
                $this->column('description', 'Description'), $this->column('type', 'Type', 'status'),
                $this->column('charge', 'Charge', 'money'), $this->column('receipt', 'Allocated Receipt', 'money'),
                $this->column('advance', 'Advance', 'money'), $this->column('balance', 'Receivable', 'money'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function receivableAging(Company $company, array $filters): array
    {
        $asOf = CarbonImmutable::parse($filters['end'])->endOfDay();
        $groups = $this->filteredGroups($company, [...$filters, 'start' => '1900-01-01'], true)
            ->filter(fn (VisaGroup $group) => ($date = $this->groupServiceDate($group)) && $date->lte($asOf));

        $rows = $groups->map(function (VisaGroup $group) use ($asOf): array {
            $date = $this->groupServiceDate($group);
            $allocated = (float) $group->paymentAllocations
                ->filter(fn (PaymentAllocation $allocation) => $allocation->payment?->direction === GroupPayment::DIRECTION_RECEIVED && $allocation->payment->payment_date->lte($asOf))
                ->sum('base_amount');
            $balance = max(round((float) $group->total_receivable - $allocated, 2), 0);
            $age = max($date?->diffInDays($asOf) ?? 0, 0);

            return [
                'href' => '/umrah/groups/'.$group->id, 'agent' => $group->agent?->name, 'group' => $group->group_number,
                'date' => $date?->toDateString(), 'receivable' => (float) $group->total_receivable, 'allocated' => $allocated,
                'balance' => $balance, 'age' => $age, 'bucket' => $this->ageBucket($age),
            ];
        })->filter(fn ($row) => $row['balance'] > 0)
            ->when($filters['payment_status'] ?? null, fn (Collection $rows, string $status) => $rows->filter(fn ($row) => match ($status) {
                'paid' => $row['balance'] <= 0, 'partially_paid' => $row['allocated'] > 0 && $row['balance'] > 0, 'unpaid' => $row['allocated'] <= 0,
            }))->sortByDesc('age')->values();

        return [
            'summary' => $this->moneySummary($company, [
                'Outstanding' => $rows->sum('balance'),
                'Current' => $rows->where('bucket', 'Current')->sum('balance'),
                '1-30 days' => $rows->where('bucket', '1-30')->sum('balance'),
                '31-60 days' => $rows->where('bucket', '31-60')->sum('balance'),
                '61-90 days' => $rows->where('bucket', '61-90')->sum('balance'),
                'Over 90 days' => $rows->where('bucket', '90+')->sum('balance'),
            ]),
            'columns' => [
                $this->column('agent', 'Agent'), $this->column('group', 'Group'), $this->column('date', 'Service Date', 'date'),
                $this->column('receivable', 'Receivable', 'money'), $this->column('allocated', 'Allocated', 'money'),
                $this->column('balance', 'Balance', 'money'), $this->column('age', 'Age', 'number'), $this->column('bucket', 'Bucket', 'status'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function vendorAging(Company $company, array $filters): array
    {
        $asOf = CarbonImmutable::parse($filters['end'])->endOfDay();
        $groups = $this->filteredGroups($company, [...$filters, 'start' => '1900-01-01'], true)
            ->filter(fn (VisaGroup $group) => ($date = $this->groupServiceDate($group)) && $date->lte($asOf));
        $rows = collect();

        foreach ($groups as $group) {
            $date = $this->groupServiceDate($group);
            if ($group->vendor && (float) $group->visa_cost_amount > 0) {
                $rows->push($this->vendorAgingRow($group, $date, 'visa', $group->vendor, (float) $group->visa_cost_amount, $asOf));
            }
            if ($group->mandatoryTransportVendor && (float) $group->mandatory_transport_cost_amount > 0) {
                $rows->push($this->vendorAgingRow($group, $date, 'transport', $group->mandatoryTransportVendor, (float) $group->mandatory_transport_cost_amount, $asOf));
            }
            $group->transportItems->whereNotNull('transport_vendor_id')->groupBy('transport_vendor_id')->each(function (Collection $items) use ($rows, $group, $date, $asOf): void {
                $rows->push($this->vendorAgingRow($group, $date, 'transport', $items->first()->transportVendor, (float) $items->sum('total_cost_amount'), $asOf));
            });
            $group->vouchers->where('status', Voucher::STATUS_APPROVED)->whereNull('billing_voucher_id')->whereNull('superseded_at')->each(function (Voucher $voucher) use ($rows, $group, $date, $asOf): void {
                collect($voucher->hotel_stays ?? [])->where('source', 'company')->whereNotNull('hotel_vendor_id')->groupBy('hotel_vendor_id')->each(function (Collection $stays) use ($rows, $group, $date, $asOf): void {
                    $vendor = HotelVendor::withTrashed()->find($stays->first()['hotel_vendor_id']);
                    if ($vendor) {
                        $rows->push($this->vendorAgingRow($group, $date, 'hotel', $vendor, (float) $stays->sum('total_cost_amount'), $asOf));
                    }
                });
            });
        }

        $rows = $rows->groupBy(fn ($row) => $row['vendor_type'].'|'.$row['vendor_id'].'|'.$row['group'])
            ->map(function (Collection $supplierGroupRows): array {
                $row = $supplierGroupRows->first();
                $row['cost'] = round((float) $supplierGroupRows->sum('cost'), 2);
                $row['allocated'] = round((float) $supplierGroupRows->max('allocated'), 2);
                $row['balance'] = max(round($row['cost'] - $row['allocated'], 2), 0);

                return $row;
            })->values()
            ->filter(fn ($row) => $row['balance'] > 0)
            ->when($filters['vendor_type'] ?? null, fn (Collection $items, string $type) => $items->where('vendor_type', $type))
            ->when($filters['visa_vendor_id'] ?? null, fn (Collection $items, string $id) => $items->where('vendor_id', $id))
            ->when($filters['transport_vendor_id'] ?? null, fn (Collection $items, string $id) => $items->where('vendor_id', $id))
            ->when($filters['hotel_vendor_id'] ?? null, fn (Collection $items, string $id) => $items->where('vendor_id', $id))
            ->sortByDesc('age')->values();

        return [
            'summary' => $this->moneySummary($company, [
                'Outstanding' => $rows->sum('balance'), 'Current' => $rows->where('bucket', 'Current')->sum('balance'),
                '1-30 days' => $rows->where('bucket', '1-30')->sum('balance'), '31-60 days' => $rows->where('bucket', '31-60')->sum('balance'),
                '61-90 days' => $rows->where('bucket', '61-90')->sum('balance'), 'Over 90 days' => $rows->where('bucket', '90+')->sum('balance'),
            ]),
            'columns' => [
                $this->column('vendor_type', 'Type', 'status'), $this->column('vendor', 'Supplier'), $this->column('group', 'Group'),
                $this->column('date', 'Service Date', 'date'), $this->column('cost', 'Cost', 'money'), $this->column('allocated', 'Allocated', 'money'),
                $this->column('balance', 'Payable', 'money'), $this->column('age', 'Age', 'number'), $this->column('bucket', 'Bucket', 'status'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function advances(Company $company, array $filters): array
    {
        $payments = GroupPayment::where('company_id', $company->id)
            ->where('status', GroupPayment::STATUS_POSTED)
            ->whereBetween('payment_date', [$filters['start'], $filters['end']])
            ->with(['agent:id,name', 'visaVendor:id,name', 'transportVendor:id,name', 'hotelVendor:id,name', 'allocations:id,group_payment_id,base_amount'])
            ->get();

        $rows = $payments->map(function (GroupPayment $payment): array {
            $allocated = (float) $payment->allocations->sum('base_amount');
            $available = max(round((float) $payment->base_amount - $allocated, 2), 0);
            $party = $payment->agent ?? $payment->visaVendor ?? $payment->transportVendor ?? $payment->hotelVendor;
            $state = $allocated <= 0 ? 'unallocated' : ($available > 0 ? 'partially_allocated' : 'allocated');

            return [
                'href' => '/umrah/payments/'.$payment->id, 'payment' => $payment->payment_number,
                'date' => $payment->payment_date->toDateString(), 'direction' => $payment->direction,
                'party' => $party?->name, 'currency_amount' => (float) $payment->amount, 'currency' => $payment->currency,
                'exchange_rate' => $payment->exchange_rate ? (float) $payment->exchange_rate : null, 'base_amount' => (float) $payment->base_amount,
                'allocated' => $allocated, 'available' => $available, 'state' => $state,
                'age' => $payment->payment_date->diffInDays(now()),
            ];
        })->when($filters['allocation_state'] ?? null, fn (Collection $items, string $state) => $items->where('state', $state))
            ->sortByDesc('date')->values();

        return [
            'summary' => $this->moneySummary($company, [
                'Agent advances' => $rows->where('direction', GroupPayment::DIRECTION_RECEIVED)->sum('available'),
                'Supplier advances' => $rows->where('direction', GroupPayment::DIRECTION_SENT)->sum('available'),
                'Allocated' => $rows->sum('allocated'), 'Available' => $rows->sum('available'),
            ]),
            'columns' => [
                $this->column('payment', 'Payment'), $this->column('date', 'Date', 'date'), $this->column('direction', 'Direction', 'status'),
                $this->column('party', 'Party'), $this->column('currency_amount', 'Original Amount', 'number'), $this->column('currency', 'Currency'),
                $this->column('exchange_rate', 'Rate', 'number'), $this->column('base_amount', 'Base Amount', 'money'),
                $this->column('allocated', 'Allocated', 'money'), $this->column('available', 'Available', 'money'),
                $this->column('state', 'Allocation', 'status'), $this->column('age', 'Age', 'number'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function passengerStatus(Company $company, array $filters): array
    {
        $query = Passenger::where('company_id', $company->id)
            ->whereHas('group', fn ($group) => $group->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->when($filters['agent_id'] ?? null, fn ($agent) => $agent->where('agent_id', $filters['agent_id'])))
            ->with(['group.agent:id,name', 'group.vendor:id,name']);
        $passengers = $query->get()->filter(function (Passenger $passenger) use ($filters): bool {
            $date = $passenger->group?->travel_date;

            return $date && $date->betweenIncluded($filters['start'], $filters['end'])
                && (! ($filters['nationality'] ?? null) || $passenger->nationality === $filters['nationality'])
                && (! ($filters['status'] ?? null) || $passenger->visa_status === $filters['status'])
                && (! ($filters['service_type'] ?? null) || $passenger->service_type === $filters['service_type']);
        });
        $rows = $passengers->map(fn (Passenger $passenger) => [
            'group' => $passenger->group?->group_number, 'agent' => $passenger->group?->agent?->name, 'passenger' => $passenger->full_name,
            'passport' => $passenger->passport_number, 'age' => $passenger->date_of_birth?->age ?? $passenger->imported_age,
            'nationality' => $passenger->nationality, 'service' => Passenger::SERVICE_TYPES[$passenger->service_type] ?? $passenger->service_type,
            'visa_status' => $passenger->visa_status, 'vendor' => $passenger->group?->vendor?->name,
            'travel_date' => $passenger->group?->travel_date?->toDateString(),
        ])->sortBy('passenger')->values();

        return [
            'summary' => [
                $this->summaryItem('Passengers', $rows->count(), 'number'),
                $this->summaryItem('Approved', $rows->where('visa_status', Passenger::STATUS_APPROVED)->count(), 'number'),
                $this->summaryItem('Delivered', $rows->where('visa_status', Passenger::STATUS_DELIVERED)->count(), 'number'),
                $this->summaryItem('Transport only', $passengers->where('service_type', Passenger::SERVICE_TRANSPORT_ONLY)->count(), 'number'),
            ],
            'columns' => [
                $this->column('group', 'Group'), $this->column('agent', 'Agent'), $this->column('passenger', 'Passenger'), $this->column('passport', 'Passport'),
                $this->column('age', 'Age', 'number'), $this->column('nationality', 'Nationality'), $this->column('service', 'Service'),
                $this->column('visa_status', 'Visa Status', 'status'), $this->column('travel_date', 'Travel Date', 'date'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function departureManifest(Company $company, array $filters): array
    {
        $vouchers = Voucher::where('company_id', $company->id)
            ->where('status', '!=', Voucher::STATUS_CANCELLED)->whereNull('superseded_at')
            ->when($filters['agent_id'] ?? null, fn ($query) => $query->where('agent_id', $filters['agent_id']))
            ->whereBetween('onward_departure_at', [CarbonImmutable::parse($filters['start'])->startOfDay(), CarbonImmutable::parse($filters['end'])->endOfDay()])
            ->when($filters['airline'] ?? null, fn ($query, $airline) => $query->where('onward_airline', $airline))
            ->when($filters['flight_number'] ?? null, fn ($query, $flight) => $query->where('onward_flight_number', 'ilike', '%'.$flight.'%'))
            ->with(['agent:id,name', 'group:id,group_number,transport_required', 'passengers:id,full_name,passport_number,nationality'])
            ->get();
        $rows = $vouchers->flatMap(fn (Voucher $voucher) => $voucher->passengers->map(fn (Passenger $passenger) => [
            'departure' => $voucher->onward_departure_at?->toIso8601String(), 'airline' => $voucher->onward_airline,
            'flight' => $voucher->onward_flight_number, 'route' => trim($voucher->onward_departure_city.' - '.$voucher->onward_arrival_city, ' -'),
            'passenger' => $passenger->full_name, 'passport' => $passenger->passport_number, 'nationality' => $passenger->nationality,
            'group' => $voucher->group?->group_number, 'agent' => $voucher->agent?->name,
            'transport' => $voucher->service_bundle === Voucher::SERVICE_HOTEL ? 'No' : 'Yes',
        ]))->sortBy([['departure', 'asc'], ['passenger', 'asc']])->values();

        return [
            'summary' => [
                $this->summaryItem('Passengers', $rows->count(), 'number'), $this->summaryItem('Flights', $vouchers->unique(fn ($voucher) => $voucher->onward_airline.'|'.$voucher->onward_flight_number.'|'.$voucher->onward_departure_at)->count(), 'number'),
                $this->summaryItem('Groups', $vouchers->pluck('visa_group_id')->unique()->count(), 'number'),
            ],
            'columns' => [
                $this->column('departure', 'Departure', 'datetime'), $this->column('airline', 'Airline'), $this->column('flight', 'Flight'), $this->column('route', 'Route'),
                $this->column('passenger', 'Passenger'), $this->column('passport', 'Passport'), $this->column('nationality', 'Nationality'),
                $this->column('group', 'Group'), $this->column('agent', 'Agent'), $this->column('transport', 'Transport', 'status'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function hotelRooming(Company $company, array $filters, bool $isAgent): array
    {
        $vouchers = Voucher::where('company_id', $company->id)
            ->where('status', '!=', Voucher::STATUS_CANCELLED)->whereNull('superseded_at')
            ->when($filters['agent_id'] ?? null, fn ($query) => $query->where('agent_id', $filters['agent_id']))
            ->with(['agent:id,name', 'group:id,group_number', 'passengers:id,full_name'])
            ->get();
        $rows = collect();
        foreach ($vouchers as $voucher) {
            foreach ($voucher->hotel_stays ?? [] as $stay) {
                $checkIn = $stay['check_in_date'] ?? null;
                if (! $checkIn || $checkIn < $filters['start'] || $checkIn > $filters['end']) {
                    continue;
                }
                if (($filters['city'] ?? null) && ($stay['city'] ?? null) !== $filters['city']) {
                    continue;
                }
                if (($filters['source'] ?? null) && ($stay['source'] ?? null) !== $filters['source']) {
                    continue;
                }
                if (($filters['hotel'] ?? null) && ! str_contains(mb_strtolower((string) ($stay['hotel_name'] ?? '')), mb_strtolower($filters['hotel']))) {
                    continue;
                }
                $row = [
                    'hotel' => $stay['hotel_name'] ?? '-', 'city' => $stay['city'] ?? '-', 'source' => $stay['source'] ?? 'self',
                    'check_in' => $checkIn, 'check_out' => $stay['check_out_date'] ?? null, 'nights' => (int) ($stay['night_count'] ?? 0),
                    'room_type' => $stay['room_type'] ?? '-', 'rooms' => (int) ($stay['room_count'] ?? 0),
                    'passengers' => $voucher->passengers->pluck('full_name')->join(', '), 'group' => $voucher->group?->group_number,
                    'agent' => $voucher->agent?->name, 'notes' => $stay['notes'] ?? null,
                ];
                if (! $isAgent) {
                    $row['sale'] = (float) ($stay['total_retail_amount'] ?? 0);
                    $row['cost'] = (float) ($stay['total_cost_amount'] ?? 0);
                }
                $rows->push($row);
            }
        }
        $columns = [
            $this->column('hotel', 'Hotel'), $this->column('city', 'City'), $this->column('source', 'Source', 'status'),
            $this->column('check_in', 'Check-in', 'date'), $this->column('check_out', 'Checkout', 'date'), $this->column('nights', 'Nights', 'number'),
            $this->column('room_type', 'Room'), $this->column('rooms', 'Rooms', 'number'), $this->column('passengers', 'Passengers'),
            $this->column('group', 'Group'), $this->column('agent', 'Agent'), $this->column('notes', 'Notes'),
        ];
        if (! $isAgent) {
            $columns[] = $this->column('sale', 'Hotel Revenue', 'money');
            $columns[] = $this->column('cost', 'Hotel Cost', 'money');
        }

        return [
            'summary' => [
                $this->summaryItem('Stays', $rows->count(), 'number'), $this->summaryItem('Rooms', $rows->sum('rooms'), 'number'),
                $this->summaryItem('Company supplied', $rows->where('source', 'company')->count(), 'number'), $this->summaryItem('Self arranged', $rows->where('source', 'self')->count(), 'number'),
            ],
            'columns' => $columns, 'rows' => $rows->sortBy('check_in')->values()->all(),
        ];
    }

    private function transportDispatch(Company $company, array $filters): array
    {
        $items = GroupTransportItem::where('company_id', $company->id)
            ->whereBetween('scheduled_at', [CarbonImmutable::parse($filters['start'])->startOfDay(), CarbonImmutable::parse($filters['end'])->endOfDay()])
            ->whereHas('group', fn ($query) => $query->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->when($filters['agent_id'] ?? null, fn ($agent) => $agent->where('agent_id', $filters['agent_id'])))
            ->with(['group.agent:id,name', 'service:id,name,vehicle_type,pax_capacity', 'sector:id,name', 'package:id,name', 'driver:id,name,phone'])
            ->get();
        $rows = $items->map(fn (GroupTransportItem $item) => [
            'schedule' => $item->scheduled_at?->toIso8601String(), 'route' => $item->sector?->name ?? $item->package?->name ?? $item->description,
            'vehicle' => $item->service?->name ?? $item->service?->vehicle_type, 'quantity' => (int) $item->quantity,
            'capacity' => $item->service?->pax_capacity, 'passengers' => (int) $item->passenger_count,
            'driver' => $item->driver?->name, 'phone' => $item->driver?->phone, 'terminal' => $item->terminal,
            'group' => $item->group?->group_number, 'agent' => $item->group?->agent?->name, 'notes' => $item->notes,
        ])->sortBy('schedule')->values();

        return [
            'summary' => [
                $this->summaryItem('Dispatches', $rows->count(), 'number'), $this->summaryItem('Vehicles', $rows->sum('quantity'), 'number'),
                $this->summaryItem('Passengers', $rows->sum('passengers'), 'number'), $this->summaryItem('Hajj Terminal', $rows->where('terminal', 'hajj')->count(), 'number'),
            ],
            'columns' => [
                $this->column('schedule', 'Schedule', 'datetime'), $this->column('route', 'Route'), $this->column('vehicle', 'Vehicle'),
                $this->column('quantity', 'Qty', 'number'), $this->column('capacity', 'Capacity', 'number'), $this->column('passengers', 'Pax', 'number'),
                $this->column('driver', 'Driver'), $this->column('phone', 'Contact'), $this->column('terminal', 'Terminal', 'status'),
                $this->column('group', 'Group'), $this->column('agent', 'Agent'), $this->column('notes', 'Notes'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function voucherControl(Company $company, array $filters): array
    {
        $vouchers = Voucher::where('company_id', $company->id)->whereNull('superseded_at')
            ->when($filters['agent_id'] ?? null, fn ($query) => $query->where('agent_id', $filters['agent_id']))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with(['agent:id,name,voucher_cutoff_hours', 'group:id,group_number', 'createdBy:id,name'])
            ->get();
        $rows = $vouchers->map(function (Voucher $voucher): array {
            $starts = $this->access->voucherTravelStartsAt($voucher);
            $cutoff = max((int) ($voucher->agent?->voucher_cutoff_hours ?? 0), 0);
            $cutoffAt = $starts?->subHours($cutoff);
            $cutoffStatus = $voucher->status !== Voucher::STATUS_DRAFT ? 'safe' : ($starts?->isPast() ? 'overdue' : ($cutoffAt?->isPast() ? 'approaching' : 'safe'));

            return [
                'href' => '/umrah/vouchers/'.$voucher->id, 'voucher' => $voucher->voucher_number, 'title' => $voucher->title,
                'agent' => $voucher->agent?->name, 'group' => $voucher->group?->group_number, 'status' => $voucher->status,
                'creator' => $voucher->createdBy?->name, 'service_start' => $starts?->toIso8601String(),
                'cutoff' => $cutoffAt?->toIso8601String(), 'cutoff_status' => $cutoffStatus,
            ];
        })->filter(fn ($row) => ! $row['service_start'] || substr($row['service_start'], 0, 10) >= $filters['start'] && substr($row['service_start'], 0, 10) <= $filters['end'])
            ->when($filters['cutoff_status'] ?? null, fn (Collection $items, string $status) => $items->where('cutoff_status', $status))
            ->sortBy('service_start')->values();

        return [
            'summary' => [
                $this->summaryItem('Vouchers', $rows->count(), 'number'), $this->summaryItem('Draft', $rows->where('status', Voucher::STATUS_DRAFT)->count(), 'number'),
                $this->summaryItem('Approved', $rows->where('status', Voucher::STATUS_APPROVED)->count(), 'number'),
                $this->summaryItem('Approaching cutoff', $rows->where('cutoff_status', 'approaching')->count(), 'number'),
                $this->summaryItem('Overdue drafts', $rows->where('cutoff_status', 'overdue')->count(), 'number'),
            ],
            'columns' => [
                $this->column('voucher', 'Voucher'), $this->column('agent', 'Agent'), $this->column('group', 'Group'), $this->column('status', 'Status', 'status'),
                $this->column('creator', 'Created By'), $this->column('service_start', 'Service Start', 'datetime'),
                $this->column('cutoff', 'Deadline', 'datetime'), $this->column('cutoff_status', 'Cutoff', 'status'),
            ],
            'rows' => $rows->all(),
        ];
    }

    private function filteredGroups(Company $company, array $filters, bool $withFinancialRelations): Collection
    {
        $relations = ['agent:id,name', 'vendor:id,name', 'mandatoryTransportVendor:id,name', 'vouchers'];
        if ($withFinancialRelations) {
            $relations = [...$relations, 'transportItems.transportVendor:id,name', 'paymentAllocations.payment'];
        }

        return VisaGroup::where('company_id', $company->id)
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->when($filters['agent_id'] ?? null, fn ($query, $id) => $query->where('agent_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['visa_vendor_id'] ?? null, fn ($query, $id) => $query->where('vendor_id', $id))
            ->with($relations)->get()
            ->filter(fn (VisaGroup $group) => ($date = $this->groupServiceDate($group)) && $date->betweenIncluded($filters['start'], $filters['end']));
    }

    private function matchesGroupFilters(VisaGroup $group, array $filters): bool
    {
        if (($filters['transport_vendor_id'] ?? null)
            && $group->mandatory_transport_vendor_id !== $filters['transport_vendor_id']
            && ! $group->transportItems->contains('transport_vendor_id', $filters['transport_vendor_id'])) {
            return false;
        }
        $balance = round((float) $group->total_receivable - $this->receivedAllocationsForGroup($group), 2);

        return match ($filters['payment_status'] ?? null) {
            'paid' => $balance <= 0,
            'partially_paid' => $balance > 0 && $balance < (float) $group->total_receivable,
            'unpaid' => $balance >= (float) $group->total_receivable,
            default => true,
        };
    }

    private function groupServiceDate(VisaGroup $group): ?CarbonImmutable
    {
        if ($group->travel_date) {
            return CarbonImmutable::parse($group->travel_date)->startOfDay();
        }
        $starts = $group->vouchers->map(fn (Voucher $voucher) => $this->access->voucherTravelStartsAt($voucher))->filter();

        return $starts->sort()->first();
    }

    private function receivedAllocationsForGroup(VisaGroup $group): float
    {
        return round((float) $group->paymentAllocations
            ->filter(fn (PaymentAllocation $allocation) => $allocation->payment?->direction === GroupPayment::DIRECTION_RECEIVED && $allocation->payment->status === GroupPayment::STATUS_POSTED)
            ->sum('base_amount'), 2);
    }

    private function vendorAgingRow(VisaGroup $group, ?CarbonImmutable $date, string $type, object $vendor, float $cost, CarbonImmutable $asOf): array
    {
        $allocated = (float) $group->paymentAllocations->filter(function (PaymentAllocation $allocation) use ($type, $vendor, $asOf): bool {
            $payment = $allocation->payment;
            if (! $payment || $payment->direction !== GroupPayment::DIRECTION_SENT || $payment->status !== GroupPayment::STATUS_POSTED || $payment->payment_date->gt($asOf)) {
                return false;
            }

            return match ($type) {
                'visa' => $payment->visa_vendor_id === $vendor->id,
                'transport' => $payment->transport_vendor_id === $vendor->id,
                'hotel' => $payment->hotel_vendor_id === $vendor->id,
            };
        })->sum('base_amount');
        $age = max($date?->diffInDays($asOf) ?? 0, 0);

        return [
            'vendor_id' => $vendor->id, 'vendor_type' => $type, 'vendor' => $vendor->name, 'group' => $group->group_number,
            'date' => $date?->toDateString(), 'cost' => round($cost, 2), 'allocated' => round($allocated, 2),
            'balance' => max(round($cost - $allocated, 2), 0), 'age' => $age, 'bucket' => $this->ageBucket($age),
        ];
    }

    private function ageBucket(int $age): string
    {
        return match (true) {
            $age <= 0 => 'Current', $age <= 30 => '1-30', $age <= 60 => '31-60', $age <= 90 => '61-90', default => '90+'
        };
    }

    private function filterDefinitions(Company $company, string $report, bool $isAgent): array
    {
        $definitions = [];
        if (! $isAgent && in_array($report, ['group-profitability', 'agent-statement', 'receivable-aging', 'passenger-status', 'departure-manifest', 'hotel-rooming', 'transport-dispatch', 'voucher-control'], true)) {
            $definitions[] = $this->selectFilter('agent_id', 'Agent', Agent::where('company_id', $company->id)->orderBy('name')->pluck('name', 'id')->all());
        }
        if ($report === 'group-profitability') {
            $definitions[] = $this->selectFilter('visa_vendor_id', 'Visa Vendor', VisaVendor::where('company_id', $company->id)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->pluck('name', 'id')->all());
            $definitions[] = $this->selectFilter('transport_vendor_id', 'Transport Vendor', VisaVendor::where('company_id', $company->id)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->pluck('name', 'id')->all());
            $definitions[] = $this->selectFilter('payment_status', 'Payment', ['paid' => 'Paid', 'partially_paid' => 'Partially paid', 'unpaid' => 'Unpaid']);
        }
        if ($report === 'agent-statement') {
            $definitions[] = $this->selectFilter('transaction_type', 'Transaction', ['charge' => 'Charge', 'allocation' => 'Allocation', 'advance' => 'Advance', 'reversal' => 'Reversal']);
        }
        if ($report === 'vendor-aging') {
            $definitions[] = $this->selectFilter('vendor_type', 'Supplier Type', ['visa' => 'Visa', 'transport' => 'Transport', 'hotel' => 'Hotel']);
        }
        if ($report === 'advances') {
            $definitions[] = $this->selectFilter('allocation_state', 'Allocation', ['allocated' => 'Allocated', 'partially_allocated' => 'Partially allocated', 'unallocated' => 'Unallocated']);
        }
        if ($report === 'passenger-status') {
            $definitions[] = $this->selectFilter('status', 'Visa Status', Passenger::STATUSES);
            $definitions[] = $this->selectFilter('service_type', 'Service', Passenger::SERVICE_TYPES);
            $definitions[] = ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'text'];
        }
        if ($report === 'departure-manifest') {
            $definitions[] = $this->selectFilter('airline', 'Airline', Voucher::AIRLINES);
            $definitions[] = ['key' => 'flight_number', 'label' => 'Flight #', 'type' => 'text'];
        }
        if ($report === 'hotel-rooming') {
            $definitions[] = $this->selectFilter('city', 'City', ['Makkah' => 'Makkah', 'Madinah' => 'Madinah']);
            $definitions[] = $this->selectFilter('source', 'Source', ['company' => 'Company', 'self' => 'Self']);
            $definitions[] = ['key' => 'hotel', 'label' => 'Hotel', 'type' => 'text'];
        }
        if ($report === 'voucher-control') {
            $definitions[] = $this->selectFilter('status', 'Status', Voucher::STATUSES);
            $definitions[] = $this->selectFilter('cutoff_status', 'Cutoff', ['safe' => 'Safe', 'approaching' => 'Approaching', 'overdue' => 'Overdue']);
        }

        return $definitions;
    }

    private function selectFilter(string $key, string $label, array $options): array
    {
        return ['key' => $key, 'label' => $label, 'type' => 'select', 'options' => collect($options)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values()->all()];
    }

    private function column(string $key, string $label, string $type = 'text'): array
    {
        return compact('key', 'label', 'type');
    }

    private function summaryItem(string $label, float|int $value, string $type): array
    {
        return compact('label', 'value', 'type');
    }

    private function moneySummary(Company $company, array $values, array $numberKeys = []): array
    {
        return collect($values)->map(fn ($value, $label) => $this->summaryItem($label, $value, in_array($label, $numberKeys, true) ? 'number' : 'money'))->values()->all();
    }
}
