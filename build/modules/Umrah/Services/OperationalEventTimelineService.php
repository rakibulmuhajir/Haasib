<?php

namespace App\Modules\Umrah\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupTransportItem;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalEventTimelineService
{
    public const EVENT_TYPES = [
        'airport_arrival' => 'Airport arrivals',
        'airport_departure' => 'Airport departures',
        'hotel_check_in' => 'Hotel check-ins',
        'hotel_check_out' => 'Hotel check-outs',
        'city_transfer' => 'City transfers',
        'transport_pickup' => 'Transport pickups',
    ];

    public const PERIODS = [
        'today' => 'Today',
        'tonight' => 'Tonight',
        'tomorrow' => 'Tomorrow',
        'next_7_days' => 'Next 7 days',
        'custom' => 'Custom dates',
    ];

    private const TRANSPORT_BUNDLES = [
        Voucher::SERVICE_VISA_TRANSPORT,
        Voucher::SERVICE_VISA_TRANSPORT_HOTEL,
        Voucher::SERVICE_TRANSPORT,
        Voucher::SERVICE_TRANSPORT_HOTEL,
    ];

    public function __construct(private TravelAccessService $access, private VoucherServiceOrigins $origins) {}

    /**
     * Build a read-only, role-shaped operational projection.
     *
     * @return array<string, mixed>
     */
    public function build(Company $company, ?User $user, array $filters): array
    {
        $role = $this->access->companyRole($company->id, $user) ?? 'unknown';
        $profile = (string) config(
            "umrah.operations.presentation_by_role.{$role}",
            config('umrah.operations.default_presentation', 'summary'),
        );
        $showsDetails = in_array($profile, ['operational', 'agent_operational'], true);
        $linkedAgent = $role === 'agent'
            ? $this->access->linkedAgent($company->id, $user)
            : null;
        $agentId = $role === 'agent'
            ? $linkedAgent?->id
            : ($filters['agent_id'] ?? null);
        $denyUnlinkedAgent = $role === 'agent' && $linkedAgent === null;

        $events = $this->voucherEvents($company, $user, $agentId, $showsDetails, $denyUnlinkedAgent)
            ->concat($this->transportEvents($company, $agentId, $showsDetails, $denyUnlinkedAgent))
            ->filter(fn (array $event): bool => $this->matchesPeriod($event, $filters))
            ->sortBy([
                ['scheduled_date', 'asc'],
                ['scheduled_time', 'asc'],
                ['type', 'asc'],
            ])
            ->values();

        $summary = $this->movementSummary($events, $showsDetails);
        $visibleEvents = $events
            ->when(
                ($filters['event_type'] ?? 'all') !== 'all',
                fn (Collection $items) => $items->where('type', $filters['event_type']),
            )
            ->when(
                ($filters['readiness'] ?? 'all') !== 'all',
                fn (Collection $items) => $items->where('readiness', $filters['readiness']),
            )
            ->values();

        return [
            'profile' => $profile,
            'shows_details' => $showsDetails,
            'period_label' => $this->periodLabel($filters),
            'filters' => [
                'period' => $filters['period'],
                'date' => $filters['date'],
                'start' => $filters['start'] ?? null,
                'end' => $filters['end'] ?? null,
                'event_type' => $filters['event_type'],
                'readiness' => $filters['readiness'],
                'agent_id' => $agentId,
            ],
            'periods' => self::PERIODS,
            'event_types' => self::EVENT_TYPES,
            'readiness_options' => [
                'all' => 'All readiness',
                'ready' => 'Ready',
                'needs_attention' => 'Needs attention',
                'self_arranged' => 'Self-arranged',
            ],
            'summary' => $summary,
            'events' => $showsDetails
                ? $visibleEvents
                    ->map(fn (array $event): array => $this->withResolutionActions($event, $user, $role))
                    ->map($this->withoutInternalFields(...))
                    ->all()
                : [],
            'matching_events' => $showsDetails ? $visibleEvents->count() : null,
            'agents' => $showsDetails && $role !== 'agent'
                ? $this->agentOptions($company->id)
                : [],
        ];
    }

    private function voucherEvents(
        Company $company,
        ?User $user,
        ?string $agentId,
        bool $showsDetails,
        bool $denyUnlinkedAgent,
    ): Collection {
        $query = Voucher::query()
            ->where('company_id', $company->id)
            ->where('status', Voucher::STATUS_APPROVED)
            ->whereNull('superseded_at')
            ->whereNull('superseded_by_voucher_id')
            ->whereHas('group', fn (Builder $group) => $group->where('status', '!=', VisaGroup::STATUS_CANCELLED))
            ->with([
                'agent',
                'group.agent',
                'group.driver',
                'group.transportService.driver',
                'group.transportItems.sector',
                'group.transportItems.service.driver',
                'group.transportItems.driver',
                'group.transportItems.transportVendor',
            ]);

        if ($denyUnlinkedAgent) {
            $query->whereRaw('1 = 0');
        } elseif ($agentId) {
            $query->where('agent_id', $agentId);
        } else {
            $this->access->scopeAgentRecords($query, $company->id, $user);
        }

        $query->with('passengers')->withCount('passengers');

        return $query->get()->flatMap(function (Voucher $voucher) use ($showsDetails, $company, $user): Collection {
            if (! $this->origins->hasExternalSources($voucher)) {
                return $this->eventsFromVoucher($voucher, $showsDetails);
            }
            // A hotel stay is one party booking: do not multiply its rooms by
            // the number of original purchase groups. Movement checks, however,
            // must use the transport each passenger actually bought.
            $hotels = $this->eventsFromVoucher($voucher, $showsDetails)
                ->filter(fn (array $event) => in_array($event['type'], ['hotel_check_in', 'hotel_check_out'], true));
            $movements = $this->origins->segments($voucher)->flatMap(function (array $segment) use ($voucher, $showsDetails, $company, $user): Collection {
                $part = clone $voucher;
                $part->setRelation('group', $segment['group']);
                $part->setRelation('passengers', $segment['passengers']);
                $part->passengers_count = $segment['passengers']->count();

                return $this->eventsFromVoucher($part, $showsDetails)
                    ->reject(fn (array $event) => in_array($event['type'], ['hotel_check_in', 'hotel_check_out'], true))
                    ->map(function (array $event) use ($segment, $voucher, $company, $user): array {
                        $event['event_key'] .= ':origin:'.($segment['group']?->id ?? 'missing');
                        if ($this->access->isAgentMember($company->id, $user) && $segment['group']?->agent_id !== $voucher->agent_id) {
                            $event['group'] = null;
                        }

                        return $event;
                    });
            });

            return $hotels->concat($movements);
        });
    }

    private function eventsFromVoucher(Voucher $voucher, bool $showsDetails): Collection
    {
        $events = collect();
        $includesHotel = Voucher::bundleIncludesHotel($voucher->service_bundle);
        $hotelOnly = $voucher->service_bundle === Voucher::SERVICE_HOTEL;
        $stays = $includesHotel
            ? collect($voucher->hotel_stays ?? [])
                ->filter(fn (mixed $stay): bool => is_array($stay))
                ->sortBy('check_in_date')
                ->values()
            : collect();
        $firstStay = $stays->first();
        $lastStay = $stays->last();
        $base = $this->voucherBase($voucher, $showsDetails);

        if (! $hotelOnly && $voucher->onward_arrival_at) {
            $arrivalAt = $this->wallClockDateTime($voucher->onward_arrival_at);
            $readiness = $this->voucherMovementReadiness($voucher, 'arrival', $arrivalAt, $firstStay, $showsDetails);
            $airport = $voucher->onward_arrival_city;

            $events->push([
                ...$base,
                'event_key' => "voucher:{$voucher->id}:airport_arrival",
                'type' => 'airport_arrival',
                'type_label' => self::EVENT_TYPES['airport_arrival'],
                'scheduled_at' => $arrivalAt,
                'scheduled_date' => substr($arrivalAt, 0, 10),
                'scheduled_time' => substr($arrivalAt, 11, 5),
                'is_all_day' => false,
                'origin' => $this->placeName($voucher->onward_departure_city),
                'destination' => $this->placeName($airport),
                'location' => $this->placeName($airport),
                'headline' => trim(($voucher->onward_airline ?? '').' '.($voucher->onward_flight_number ?? '')) ?: 'Scheduled arrival',
                'airport' => $airport,
                'flight' => $this->flightLabel($voucher->onward_airline, $voucher->onward_flight_number),
                'hotel' => $firstStay['hotel_name'] ?? null,
                'city' => $firstStay['city'] ?? $this->placeName($airport),
                ...$readiness,
                '_timezone' => $this->timezoneFor($airport),
            ]);
        }

        if (! $hotelOnly && $voucher->return_departure_at) {
            $departureAt = $this->wallClockDateTime($voucher->return_departure_at);
            $readiness = $this->voucherMovementReadiness($voucher, 'departure', $departureAt, $lastStay, $showsDetails);
            $airport = $voucher->return_departure_city;

            $events->push([
                ...$base,
                'event_key' => "voucher:{$voucher->id}:airport_departure",
                'type' => 'airport_departure',
                'type_label' => self::EVENT_TYPES['airport_departure'],
                'scheduled_at' => $departureAt,
                'scheduled_date' => substr($departureAt, 0, 10),
                'scheduled_time' => substr($departureAt, 11, 5),
                'is_all_day' => false,
                'origin' => $this->placeName($airport),
                'destination' => $this->placeName($voucher->return_arrival_city),
                'location' => $this->placeName($airport),
                'headline' => trim(($voucher->return_airline ?? '').' '.($voucher->return_flight_number ?? '')) ?: 'Scheduled departure',
                'airport' => $airport,
                'flight' => $this->flightLabel($voucher->return_airline, $voucher->return_flight_number),
                'hotel' => is_array($lastStay) ? ($lastStay['hotel_name'] ?? null) : null,
                'city' => is_array($lastStay) ? ($lastStay['city'] ?? null) : null,
                ...$readiness,
                '_timezone' => $this->timezoneFor($airport),
            ]);
        }

        foreach ($stays as $index => $stay) {
            $previous = $index > 0 ? $stays->get($index - 1) : null;
            $next = $index < $stays->count() - 1 ? $stays->get($index + 1) : null;
            $city = $stay['city'] ?? null;
            $hotel = $stay['hotel_name'] ?? null;
            $hotelReadiness = $this->hotelReadiness($stay);

            if (! empty($stay['check_in_date'])) {
                $date = (string) $stay['check_in_date'];
                $events->push([
                    ...$base,
                    'event_key' => "voucher:{$voucher->id}:hotel_check_in:{$index}",
                    'type' => 'hotel_check_in',
                    'type_label' => self::EVENT_TYPES['hotel_check_in'],
                    'scheduled_at' => $date,
                    'scheduled_date' => $date,
                    'scheduled_time' => null,
                    'is_all_day' => true,
                    'origin' => is_array($previous)
                        ? ($previous['city'] ?? null)
                        : $this->placeName($voucher->onward_departure_city),
                    'destination' => $city,
                    'location' => $city,
                    'headline' => $hotel ? "{$hotel} check-in" : 'Hotel check-in',
                    'airport' => null,
                    'flight' => null,
                    'hotel' => $hotel,
                    'city' => $city,
                    'room_type' => $stay['room_type'] ?? null,
                    'room_count' => (int) ($stay['room_count'] ?? 0),
                    ...$hotelReadiness,
                    '_timezone' => $this->timezoneFor($city),
                ]);
            }

            if (! empty($stay['check_out_date'])) {
                $date = (string) $stay['check_out_date'];
                $events->push([
                    ...$base,
                    'event_key' => "voucher:{$voucher->id}:hotel_check_out:{$index}",
                    'type' => 'hotel_check_out',
                    'type_label' => self::EVENT_TYPES['hotel_check_out'],
                    'scheduled_at' => $date,
                    'scheduled_date' => $date,
                    'scheduled_time' => null,
                    'is_all_day' => true,
                    'origin' => $city,
                    'destination' => is_array($next)
                        ? ($next['city'] ?? null)
                        : $this->airportDestinationLabel($voucher->return_departure_city),
                    'location' => $city,
                    'headline' => $hotel ? "{$hotel} check-out" : 'Hotel check-out',
                    'airport' => null,
                    'flight' => null,
                    'hotel' => $hotel,
                    'city' => $city,
                    'room_type' => $stay['room_type'] ?? null,
                    'room_count' => (int) ($stay['room_count'] ?? 0),
                    ...$hotelReadiness,
                    '_timezone' => $this->timezoneFor($city),
                ]);

                $nextCity = is_array($next) ? ($next['city'] ?? null) : null;
                $isIntercityMove = collect([
                    $this->canonicalSaudiCity($city),
                    $this->canonicalSaudiCity($nextCity),
                ])->sort()->values()->all() === ['Madinah', 'Makkah'];
                $hasScheduledMove = $isIntercityMove && $this->matchingCityTransferItem(
                    $voucher,
                    $date,
                    (string) ($next['check_in_date'] ?? $date),
                    $city,
                    $nextCity,
                );

                if (! $hotelOnly && $isIntercityMove && ! $hasScheduledMove) {
                    $readiness = $this->inferredCityTransferReadiness($voucher);
                    $origin = $this->canonicalSaudiCity($city);
                    $destination = $this->canonicalSaudiCity($nextCity);

                    $events->push([
                        ...$base,
                        'event_key' => "voucher:{$voucher->id}:city_transfer:{$index}",
                        'type' => 'city_transfer',
                        'type_label' => self::EVENT_TYPES['city_transfer'],
                        'scheduled_at' => $date,
                        'scheduled_date' => $date,
                        'scheduled_time' => null,
                        'is_all_day' => true,
                        'origin' => $origin,
                        'destination' => $destination,
                        'location' => $origin,
                        'headline' => "{$origin} → {$destination}",
                        'airport' => null,
                        'flight' => null,
                        'hotel' => $next['hotel_name'] ?? null,
                        'city' => $origin,
                        ...$readiness,
                        '_timezone' => $this->timezoneFor($origin),
                    ]);
                }
            }
        }

        // Keep purchased rooms visible even when everyone has joined another
        // party, but an empty party cannot arrive, depart or change cities.
        return $voucher->passengers_count === 0
            ? $events->filter(fn (array $event) => in_array($event['type'], ['hotel_check_in', 'hotel_check_out'], true))
            : $events;
    }

    private function voucherBase(Voucher $voucher, bool $showsDetails): array
    {
        $passengerCount = $showsDetails
            ? $voucher->passengers->count()
            : (int) $voucher->passengers_count;
        $base = [
            'passenger_count' => $passengerCount,
            'movement_scope' => 'voucher',
        ];

        if (! $showsDetails) {
            return $base;
        }

        return [
            ...$base,
            'voucher' => [
                'id' => $voucher->id,
                'number' => $voucher->voucher_number,
                'title' => $voucher->title,
            ],
            'group' => $voucher->group ? [
                'id' => $voucher->group->id,
                'number' => $voucher->group->group_number,
                'name' => $voucher->group->name,
            ] : null,
            'agent' => $voucher->agent ? [
                'id' => $voucher->agent->id,
                'name' => $voucher->agent->name,
            ] : null,
            'passengers' => $voucher->passengers->map(fn ($passenger) => [
                'id' => $passenger->id,
                'name' => $passenger->full_name,
                'passport' => $passenger->passport_number,
                'nationality' => $passenger->nationality,
            ])->values()->all(),
            'source_href' => "/umrah/vouchers/{$voucher->id}",
        ];
    }

    private function transportEvents(
        Company $company,
        ?string $agentId,
        bool $showsDetails,
        bool $denyUnlinkedAgent,
    ): Collection {
        $with = ['sector', 'service.driver', 'driver', 'transportVendor', 'group.agent', 'group.driver', 'group.transportItems.sector'];
        $activeVoucher = fn ($query) => $query->where('status', Voucher::STATUS_APPROVED)
            ->whereNull('superseded_at')->whereNull('superseded_by_voucher_id');
        $with['group.passengers'] = fn ($query) => $query->whereHas('voucherPassengers.voucher', $activeVoucher)
            ->with(['voucherPassengers.voucher' => $activeVoucher]);

        $query = GroupTransportItem::query()
            ->where('company_id', $company->id)
            ->whereNotNull('scheduled_at')
            ->whereHas('group', fn (Builder $group) => $group
                ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
                ->where('transport_mode', '!=', VisaGroup::TRANSPORT_NONE))
            ->with($with);

        if ($denyUnlinkedAgent) {
            $query->whereRaw('1 = 0');
        } elseif ($agentId) {
            $query->whereHas('group', fn (Builder $group) => $group->where('agent_id', $agentId));
        }

        return $query->get()->map(function (GroupTransportItem $item) use ($showsDetails): array {
            $origin = $item->sector?->origin;
            $destination = $item->sector?->destination;
            $originCity = $this->canonicalSaudiCity($origin);
            $destinationCity = $this->canonicalSaudiCity($destination);
            $type = collect([$originCity, $destinationCity])->sort()->values()->all() === ['Madinah', 'Makkah']
                ? 'city_transfer'
                : 'transport_pickup';
            $scheduledAt = $this->wallClockDateTime($item->scheduled_at);
            $passengers = ($item->group?->passengers ?? collect())->filter(fn ($passenger) => $passenger->voucherPassengers
                ->contains(fn ($assignment) => $assignment->voucher && $this->voucherMatchesPickup($assignment->voucher, $item)))->unique('id');
            $ambiguous = $passengers->isNotEmpty() && ($item->group?->transportItems ?? collect())
                ->contains(fn (GroupTransportItem $other) => $other->id !== $item->id
                    && $other->scheduled_at?->format('Y-m-d') === $item->scheduled_at?->format('Y-m-d')
                    && $other->sector?->origin === $item->sector?->origin
                    && $other->sector?->destination === $item->sector?->destination);
            if ($ambiguous) {
                $passengers = collect();
            }
            $passengerCount = (int) ($item->passenger_count ?: $passengers->count());
            $capacity = $item->service?->pax_capacity
                ? (int) $item->service->pax_capacity * max((int) $item->quantity, 1)
                : null;
            $driver = $item->driver ?? $item->service?->driver ?? $item->group?->driver;
            $issues = [];

            if ($ambiguous) {
                $issues[] = 'Multiple pickups match this itinerary; confirm passenger allocation';
            } elseif ($passengers->isEmpty()) {
                $issues[] = 'No approved passenger itinerary matches this pickup';
            } elseif ($passengerCount !== $passengers->count()) {
                $issues[] = 'Scheduled headcount differs from itinerary-matched passengers';
            }

            if (! $item->service) {
                $issues[] = 'Vehicle is not assigned';
            }
            if (! $driver && ! $item->service?->driver_name) {
                $issues[] = 'Driver is not assigned';
            }
            if ($capacity !== null && $passengerCount > $capacity) {
                $issues[] = 'Capacity is short by '.($passengerCount - $capacity).' seats';
            }

            $event = [
                'event_key' => "transport:{$item->id}",
                'type' => $type,
                'type_label' => self::EVENT_TYPES[$type],
                'scheduled_at' => $scheduledAt,
                'scheduled_date' => substr($scheduledAt, 0, 10),
                'scheduled_time' => substr($scheduledAt, 11, 5),
                'is_all_day' => false,
                'origin' => $origin,
                'destination' => $destination,
                'location' => $origin,
                'headline' => $item->sector?->name ?? $item->description ?? 'Transport pickup',
                'airport' => null,
                'flight' => null,
                'hotel' => null,
                'city' => $originCity,
                'passenger_count' => $passengerCount,
                'movement_scope' => 'group',
                'readiness' => $issues === [] ? 'ready' : 'needs_attention',
                'readiness_label' => $issues === [] ? 'Ready' : 'Needs attention',
                'readiness_issues' => $issues,
                '_timezone' => $this->timezoneFor($origin),
            ];

            if (! $showsDetails) {
                return $event;
            }

            return [
                ...$event,
                'voucher' => null,
                'group' => $item->group ? [
                    'id' => $item->group->id,
                    'number' => $item->group->group_number,
                    'name' => $item->group->name,
                ] : null,
                'agent' => $item->group?->agent ? [
                    'id' => $item->group->agent->id,
                    'name' => $item->group->agent->name,
                ] : null,
                'passengers' => $passengers->map(fn ($passenger) => [
                    'id' => $passenger->id, 'name' => $passenger->full_name,
                    'passport' => $passenger->passport_number, 'nationality' => $passenger->nationality,
                ])->values()->all(),
                'transport' => $this->transportDetails($item),
                'source_href' => $item->group ? "/umrah/groups/{$item->group->id}" : null,
            ];
        });
    }

    private function voucherMovementReadiness(
        Voucher $voucher,
        string $direction,
        string $scheduledAt,
        mixed $stay,
        bool $showsDetails,
    ): array {
        $issues = [];
        $passengerCount = $voucher->relationLoaded('passengers')
            ? $voucher->passengers->count()
            : (int) $voucher->passengers_count;

        if ($passengerCount === 0) {
            $issues[] = 'No passengers are assigned';
        }
        if (Voucher::bundleIncludesHotel($voucher->service_bundle)
            && (! is_array($stay) || empty($stay['city']))) {
            $issues[] = $direction === 'arrival' ? 'First stay is missing' : 'Final stay is missing';
        }

        $companyTransport = ($voucher->service_bundle !== Voucher::SERVICE_HOTEL)
            && $voucher->group
            && $voucher->group->transport_mode !== VisaGroup::TRANSPORT_NONE;

        if (! $companyTransport) {
            return [
                'readiness' => $issues === [] ? 'self_arranged' : 'needs_attention',
                'readiness_label' => $issues === [] ? 'Self-arranged' : 'Needs attention',
                'readiness_issues' => $issues,
                'transport' => null,
            ];
        }

        $transport = $this->matchingTransportItem($voucher, $direction, $scheduledAt, $stay);
        if (! $transport) {
            $issues[] = 'Transport is not scheduled';
        } else {
            $service = $transport->service ?? $voucher->group?->transportService;
            $driver = $transport->driver ?? $service?->driver ?? $voucher->group?->driver;
            if (! $service) {
                $issues[] = 'Vehicle is not assigned';
            }
            if (! $driver && ! $service?->driver_name) {
                $issues[] = 'Driver is not assigned';
            }
        }

        return [
            'readiness' => $issues === [] ? 'ready' : 'needs_attention',
            'readiness_label' => $issues === [] ? 'Ready' : 'Needs attention',
            'readiness_issues' => $issues,
            'transport' => $showsDetails && $transport ? $this->transportDetails($transport) : null,
        ];
    }

    private function transportDetails(GroupTransportItem $item): array
    {
        $driver = $item->driver ?? $item->service?->driver ?? $item->group?->driver;
        $capacity = $item->service?->pax_capacity
            ? (int) $item->service->pax_capacity * max((int) $item->quantity, 1)
            : null;

        return [
            'provider' => $item->transportVendor?->name,
            'vehicle' => $item->service?->name ?? $item->service?->vehicle_type,
            'quantity' => (int) $item->quantity,
            'capacity' => $capacity,
            'driver' => $driver?->name ?? $item->service?->driver_name,
            'driver_phone' => $driver?->phone ?? $item->service?->driver_contact,
            'terminal' => $item->terminal,
        ];
    }

    private function matchingTransportItem(Voucher $voucher, string $direction, string $scheduledAt, mixed $stay): ?GroupTransportItem
    {
        $date = substr($scheduledAt, 0, 10);
        $airport = $direction === 'arrival' ? $voucher->onward_arrival_city : $voucher->return_departure_city;
        $city = is_array($stay) ? ($stay['city'] ?? null) : null;

        return $voucher->group?->transportItems
            ->filter(fn (GroupTransportItem $item): bool => $item->scheduled_at?->format('Y-m-d') === $date)
            ->filter(fn (GroupTransportItem $item): bool => $this->transportDirectionScore($item, $direction, $airport, $city) > 0)
            ->sortBy(fn (GroupTransportItem $item): int => $this->transportDirectionScore($item, $direction, $airport, $city))
            ->last();
    }

    private function voucherMatchesPickup(Voucher $voucher, GroupTransportItem $item): bool
    {
        $date = $item->scheduled_at?->format('Y-m-d');
        $stays = collect($voucher->hotel_stays ?? [])->filter(fn ($stay) => is_array($stay))
            ->sortBy('check_in_date')->values();
        if ($voucher->service_bundle !== Voucher::SERVICE_HOTEL) {
            foreach ([['arrival', $voucher->onward_arrival_at, $voucher->onward_arrival_city, $stays->first()],
                ['departure', $voucher->return_departure_at, $voucher->return_departure_city, $stays->last()]] as [$direction, $flightAt, $airport, $stay]) {
                if ($flightAt?->format('Y-m-d') === $date
                    && $this->transportDirectionScore($item, $direction, $airport, $stay['city'] ?? null) > 0) {
                    return true;
                }
            }
        }
        foreach ($stays as $index => $stay) {
            $next = $stays->get($index + 1);
            if (! $next || empty($stay['check_out_date']) || empty($next['check_in_date'])) {
                continue;
            }
            if ($date >= min($stay['check_out_date'], $next['check_in_date'])
                && $date <= max($stay['check_out_date'], $next['check_in_date'])
                && $this->canonicalSaudiCity($item->sector?->origin) !== null
                && $this->canonicalSaudiCity($item->sector?->destination) !== null
                && $this->canonicalSaudiCity($item->sector?->origin) === $this->canonicalSaudiCity($stay['city'] ?? null)
                && $this->canonicalSaudiCity($item->sector?->destination) === $this->canonicalSaudiCity($next['city'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function matchingCityTransferItem(
        Voucher $voucher,
        string $startDate,
        string $endDate,
        ?string $origin,
        ?string $destination,
    ): ?GroupTransportItem {
        $originCity = $this->canonicalSaudiCity($origin);
        $destinationCity = $this->canonicalSaudiCity($destination);

        return $voucher->group?->transportItems
            ->filter(function (GroupTransportItem $item) use ($startDate, $endDate): bool {
                $scheduledDate = $item->scheduled_at?->format('Y-m-d');

                return $scheduledDate !== null
                    && $scheduledDate >= min($startDate, $endDate)
                    && $scheduledDate <= max($startDate, $endDate);
            })
            ->first(fn (GroupTransportItem $item): bool => $this->canonicalSaudiCity($item->sector?->origin) === $originCity
                && $this->canonicalSaudiCity($item->sector?->destination) === $destinationCity);
    }

    private function inferredCityTransferReadiness(Voucher $voucher): array
    {
        $passengerCount = $voucher->relationLoaded('passengers')
            ? $voucher->passengers->count()
            : (int) $voucher->passengers_count;
        $issues = $passengerCount === 0 ? ['No passengers are assigned'] : [];
        $companyTransport = ($voucher->service_bundle !== Voucher::SERVICE_HOTEL)
            && $voucher->group
            && $voucher->group->transport_mode !== VisaGroup::TRANSPORT_NONE;

        if (! $companyTransport) {
            return [
                'readiness' => $issues === [] ? 'self_arranged' : 'needs_attention',
                'readiness_label' => $issues === [] ? 'Self-arranged' : 'Needs attention',
                'readiness_issues' => $issues,
            ];
        }

        $issues[] = 'Transport is not scheduled';

        return [
            'readiness' => 'needs_attention',
            'readiness_label' => 'Needs attention',
            'readiness_issues' => $issues,
        ];
    }

    private function transportDirectionScore(GroupTransportItem $item, string $direction, ?string $airport, ?string $city): int
    {
        $origin = mb_strtolower((string) $item->sector?->origin);
        $destination = mb_strtolower((string) $item->sector?->destination);
        $airportNeedle = mb_strtolower((string) $airport);
        $airportCity = mb_strtolower((string) $this->placeName($airport));
        $cityNeedle = mb_strtolower((string) $city);
        $originMatchesAirport = $airportNeedle !== '' && (str_contains($origin, $airportNeedle) || str_contains($origin, $airportCity));
        $destinationMatchesAirport = $airportNeedle !== '' && (str_contains($destination, $airportNeedle) || str_contains($destination, $airportCity));
        $originMatchesCity = $cityNeedle !== '' && str_contains($origin, $cityNeedle);
        $destinationMatchesCity = $cityNeedle !== '' && str_contains($destination, $cityNeedle);

        return match ($direction) {
            'arrival' => $originMatchesAirport && $destinationMatchesCity ? 2 : ($originMatchesAirport ? 1 : 0),
            default => $originMatchesCity && $destinationMatchesAirport ? 2 : ($destinationMatchesAirport ? 1 : 0),
        };
    }

    private function hotelReadiness(array $stay): array
    {
        if (($stay['source'] ?? 'self') === 'self') {
            return [
                'readiness' => 'self_arranged',
                'readiness_label' => 'Self-arranged',
                'readiness_issues' => [],
            ];
        }

        $issues = [];
        if (blank($stay['hotel_name'] ?? null)) {
            $issues[] = 'Hotel is not selected';
        }
        if (blank($stay['room_type'] ?? null) || (int) ($stay['room_count'] ?? 0) < 1) {
            $issues[] = 'Room arrangement is incomplete';
        }

        return [
            'readiness' => $issues === [] ? 'ready' : 'needs_attention',
            'readiness_label' => $issues === [] ? 'Ready' : 'Needs attention',
            'readiness_issues' => $issues,
        ];
    }

    private function movementSummary(Collection $events, bool $showsDetails): array
    {
        $cards = [
            $this->summaryCard('moving_in', 'Moving in', $events->where('type', 'airport_arrival')->sum('passenger_count'), 'airport_arrival'),
            $this->summaryCard('moving_out', 'Moving out', $events->where('type', 'airport_departure')->sum('passenger_count'), 'airport_departure'),
            $this->summaryCard('makkah_to_madinah', 'Makkah → Madinah', $this->routePassengerCount($events, 'Makkah', 'Madinah'), 'city_transfer'),
            $this->summaryCard('madinah_to_makkah', 'Madinah → Makkah', $this->routePassengerCount($events, 'Madinah', 'Makkah'), 'city_transfer'),
        ];

        if ($showsDetails) {
            $cards[] = $this->summaryCard(
                'needs_attention',
                'Needs attention',
                $events->where('readiness', 'needs_attention')->count(),
                null,
                'needs_attention',
            );
        }

        return $cards;
    }

    private function routePassengerCount(Collection $events, string $origin, string $destination): int
    {
        return (int) $events
            ->where('type', 'city_transfer')
            ->filter(fn (array $event): bool => $this->canonicalSaudiCity($event['origin']) === $origin
                && $this->canonicalSaudiCity($event['destination']) === $destination)
            ->sum('passenger_count');
    }

    private function summaryCard(string $key, string $label, int|float $value, ?string $eventType, ?string $readiness = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => (int) $value,
            'event_type' => $eventType,
            'readiness' => $readiness,
        ];
    }

    private function matchesPeriod(array $event, array $filters): bool
    {
        $period = $filters['period'];
        $zone = $event['_timezone'];
        $today = CarbonImmutable::now($zone)->startOfDay();
        $scheduledDate = $event['scheduled_date'];

        if ($period === 'custom') {
            return $scheduledDate >= $filters['start'] && $scheduledDate <= $filters['end'];
        }
        if ($period === 'today') {
            return $scheduledDate === $today->toDateString();
        }
        if ($period === 'tomorrow') {
            return $scheduledDate === $today->addDay()->toDateString();
        }
        if ($period === 'next_7_days') {
            return $scheduledDate >= $today->toDateString()
                && $scheduledDate <= $today->addDays(6)->toDateString();
        }

        $now = CarbonImmutable::now($zone);
        $start = $now->hour < 6
            ? $now->subDay()->setTime(18, 0)
            : $now->setTime(18, 0);
        $end = $start->addHours(12);

        if ($event['is_all_day']) {
            return $scheduledDate === $start->toDateString();
        }

        $scheduled = CarbonImmutable::parse($event['scheduled_at'], $zone);

        return $scheduled->betweenIncluded($start, $end);
    }

    private function periodLabel(array $filters): string
    {
        if ($filters['period'] === 'custom') {
            return "{$filters['start']} to {$filters['end']}";
        }

        return self::PERIODS[$filters['period']];
    }

    private function agentOptions(string $companyId): array
    {
        return Agent::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderByName()
            ->get()
            ->map(fn (Agent $agent) => ['value' => $agent->id, 'label' => $agent->name])
            ->all();
    }

    private function wallClockDateTime(mixed $value): string
    {
        return CarbonImmutable::parse($value)->format('Y-m-d\TH:i:s');
    }

    private function flightLabel(?string $airline, ?string $flight): ?string
    {
        $label = trim(($airline ?? '').' '.($flight ?? ''));

        return $label !== '' ? $label : null;
    }

    private function placeName(?string $place): ?string
    {
        if (! $place) {
            return null;
        }

        return Voucher::AIRPORT_CITIES[$place] ?? $place;
    }

    private function airportDestinationLabel(?string $airport): ?string
    {
        $place = $this->placeName($airport);

        return $place ? "{$place} airport" : null;
    }

    private function canonicalSaudiCity(?string $place): ?string
    {
        $normalized = mb_strtolower((string) $this->placeName($place));

        return match (true) {
            str_contains($normalized, 'makkah'), str_contains($normalized, 'mecca') => 'Makkah',
            str_contains($normalized, 'madinah'), str_contains($normalized, 'medina') => 'Madinah',
            str_contains($normalized, 'jeddah') => 'Jeddah',
            default => null,
        };
    }

    private function timezoneFor(?string $place): string
    {
        $code = mb_strtoupper((string) $place);

        if (in_array($code, ['KHI', 'LHE', 'ISB', 'PEW', 'SKT', 'MUX', 'UET', 'FSD'], true)) {
            return 'Asia/Karachi';
        }

        return (string) config('umrah.operations.operational_timezone', 'Asia/Riyadh');
    }

    /**
     * Point an operations warning at an existing, authorized correction flow.
     * Approved voucher facts go through amendment; transport dispatch stays on
     * the group workflow. Owners never reach this method because their profile
     * contains totals only, and agent users keep their read-only scoped view.
     */
    private function withResolutionActions(array $event, ?User $user, string $role): array
    {
        $event['resolution_actions'] = [];

        if ($role === 'agent' && is_array($event['transport'] ?? null)) {
            unset($event['transport']['driver'], $event['transport']['driver_phone']);
        }

        if ($event['readiness'] !== 'needs_attention' || ! $user || $role === 'agent') {
            return $event;
        }

        $issues = collect($event['readiness_issues']);
        $transportIssues = $issues->contains(
            fn (string $issue): bool => in_array($issue, [
                'Transport is not scheduled',
                'Vehicle is not assigned',
                'Driver is not assigned',
            ], true) || str_starts_with($issue, 'Capacity is short by '),
        );

        if ($transportIssues
            && ! empty($event['group']['id'])
            && $user->hasCompanyPermission(Permissions::UMRAH_GROUP_UPDATE)) {
            $event['resolution_actions'][] = [
                'key' => 'group_transport',
                'label' => 'Review group transport',
                'description' => 'Open the group transport section and correct the available assignment details.',
                'href' => "/umrah/groups/{$event['group']['id']}/edit?focus=transport&from=operations",
            ];
        }

        $voucherIssues = $issues->contains(
            fn (string $issue): bool => in_array($issue, [
                'No passengers are assigned',
                'First stay is missing',
                'Final stay is missing',
                'Hotel is not selected',
                'Room arrangement is incomplete',
            ], true),
        );

        if ($voucherIssues
            && ! empty($event['voucher']['id'])
            && $user->hasCompanyPermission(Permissions::UMRAH_VOUCHER_UPDATE)) {
            $event['resolution_actions'][] = [
                'key' => 'voucher_amendment',
                'label' => 'Amend voucher',
                'description' => 'Create a draft amendment while keeping the approved voucher intact.',
                'href' => "/umrah/vouchers/{$event['voucher']['id']}?workflow=amend&from=operations",
            ];
        }

        return $event;
    }

    private function withoutInternalFields(array $event): array
    {
        unset($event['_timezone']);

        return $event;
    }
}
