<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Staff-only aggregate: purchase ownership is unchanged; current parties supply travel dates. */
class GroupBookingReadiness
{
    public function __construct(private HotelConfirmations $hotels, private TransportConfirmations $transport) {}

    public function forGroups(string $companyId, Collection $groups): array
    {
        $ids = $groups->pluck('id');
        $vouchers = Voucher::where('company_id', $companyId)
            ->whereIn('status', [Voucher::STATUS_DRAFT, Voucher::STATUS_APPROVED])
            ->whereNull('superseded_at')->whereNull('superseded_by_voucher_id')
            ->where(fn ($q) => $q->whereIn('visa_group_id', $ids)->orWhereHas('voucherPassengers', fn ($p) => $p->whereIn('visa_group_id', $ids)))
            ->with('passengers')->get();
        $sourceIds = $vouchers->flatMap(fn ($v) => $v->passengers->map(fn ($p) => $p->pivot?->visa_group_id ?? $p->visa_group_id))->merge($ids)->unique();
        $sources = VisaGroup::where('company_id', $companyId)->whereIn('id', $sourceIds)->with(['transportItems', 'passengers:id,visa_group_id'])->get()->keyBy('id');
        $now = CarbonImmutable::now('Asia/Riyadh');
        $result = [];
        foreach ($groups as $group) {
            if (in_array($group->status, [VisaGroup::STATUS_CANCELLED, VisaGroup::STATUS_CLOSED], true)) {
                $result[$group->id] = $this->state('grey', 'Closed / cancelled');

                continue;
            }
            $parties = $vouchers->filter(fn ($v) => $v->visa_group_id === $group->id || $v->passengers->contains(fn ($p) => ($p->pivot?->visa_group_id ?? $p->visa_group_id) === $group->id));
            $states = $parties->map(fn ($v) => $this->party($v, $sources, $now));
            $assigned = $parties->flatMap(fn ($v) => $v->passengers->pluck('id'))->unique();
            $unassigned = $sources->get($group->id)?->passengers->whereNotIn('id', $assigned)->count() ?? 0;
            if ($unassigned) {
                $states->push($this->state('grey', "{$unassigned} pax without a current travelling voucher"));
            }
            // An unknown party never hides an urgent known problem or becomes green.
            $result[$group->id] = $states->sortBy(fn ($s) => ['red' => 0, 'orange' => 1, 'grey' => 2, 'green' => 3][$s['colour']])->first()
                ?? $this->state('grey', 'No current travelling voucher');
        }

        return $result;
    }

    private function party(Voucher $voucher, Collection $sources, CarbonImmutable $now): array
    {
        if ($voucher->status === Voucher::STATUS_DRAFT || $voucher->passengers->isEmpty()) {
            return $this->state('grey', 'Travelling voucher needs review');
        }
        $arrivalText = $voucher->onward_arrival_at?->format('Y-m-d H:i:s');
        if (! $arrivalText && $voucher->service_bundle === Voucher::SERVICE_HOTEL) {
            $arrivalText = collect($voucher->hotel_stays ?? [])->pluck('check_in_date')->filter()->sort()->first();
        }
        if (! $arrivalText) {
            return $this->state('grey', 'Arrival date missing');
        }
        $arrival = CarbonImmutable::parse($arrivalText, 'Asia/Riyadh');
        $endText = $voucher->return_departure_at?->format('Y-m-d H:i:s');
        if (! $endText && $voucher->service_bundle === Voucher::SERVICE_HOTEL) {
            $endText = collect($voucher->hotel_stays ?? [])->pluck('check_out_date')->filter()->sort()->last();
        }
        if ($endText && CarbonImmutable::parse($endText, 'Asia/Riyadh')->endOfDay()->lt($now)) {
            return $this->state('grey', 'Journey completed');
        }
        $bookings = collect($this->hotels->rows($voucher))->filter(fn ($row) => $row['status'] !== 'agent_arranged')
            ->filter(fn ($row) => $arrival->gt($now) || empty($row['check_out_date']) || CarbonImmutable::parse($row['check_out_date'], 'Asia/Riyadh')->endOfDay()->gte($now))
            ->map(fn ($row) => ['status' => $row['status'], 'date' => $row['check_in_date'], 'type' => 'hotel'])->values();
        $originals = $voucher->passengers->map(fn ($p) => $p->pivot?->visa_group_id ?? $p->visa_group_id)->unique();
        foreach ($originals as $id) {
            $source = $sources->get($id);
            if (! $source || $source->status === VisaGroup::STATUS_CANCELLED) {
                $bookings->push(['status' => 'not_recorded', 'date' => null, 'type' => 'transport']);

                continue;
            }
            if ($source->transport_mode === VisaGroup::TRANSPORT_NONE) {
                continue;
            }
            $rows = $this->transport->rows($source);
            if (! $rows) {
                $bookings->push(['status' => 'pending', 'date' => null, 'type' => 'transport']);
            }
            foreach ($rows as $row) {
                $bookings->push(['status' => $row['status'], 'date' => $row['scheduled_at'], 'type' => 'transport']);
            }
        }
        $pending = $bookings->whereNotIn('status', ['confirmed', 'not_recorded']);
        if ($pending->isNotEmpty()) {
            $deadline = $arrival;
            if ($arrival->lte($now)) {
                $dates = $pending->pluck('date')->filter()->map(fn ($date) => CarbonImmutable::parse($date, 'Asia/Riyadh'));
                $deadline = $dates->sort()->first() ?? $arrival;
            }
            $hours = $now->diffInHours($deadline, false);
            $colour = $hours <= 72 ? 'red' : 'orange';
            $description = $pending->where('type', 'hotel')->count().' hotel / '.$pending->where('type', 'transport')->count().' transport unresolved';

            return $this->state($colour, $description, $hours <= 0 ? 'Due / overdue' : 'Due in '.(int) ceil($hours).' hours');
        }
        if ($bookings->contains('status', 'not_recorded')) {
            return $this->state('grey', 'Booking confirmation not recorded');
        }

        return $bookings->isEmpty() ? $this->state('grey', 'No company bookings to confirm') : $this->state('green', 'All required bookings confirmed');
    }

    private function state(string $colour, string $label, ?string $detail = null): array
    {
        return compact('colour', 'label', 'detail');
    }
}
