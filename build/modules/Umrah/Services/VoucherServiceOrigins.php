<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use Illuminate\Support\Collection;

/** Original purchases follow the passenger; the voucher owns the new itinerary. */
class VoucherServiceOrigins
{
    public function segments(Voucher $voucher): Collection
    {
        $voucher->loadMissing('passengers');
        $byGroup = $voucher->passengers->groupBy(fn ($passenger) => $passenger->pivot?->visa_group_id ?? $passenger->visa_group_id);
        $groups = VisaGroup::withTrashed()->where('company_id', $voucher->company_id)
            ->whereIn('id', $byGroup->keys())
            ->with(['vendor', 'mandatoryTransportVendor', 'driver', 'transportService.driver',
                'transportItems.sector', 'transportItems.transportVendor', 'transportItems.driver', 'transportItems.service.driver'])
            ->get()->keyBy('id');

        return $byGroup->map(fn ($passengers, $id) => [
            'group' => $groups->get($id),
            'passengers' => $passengers->values(),
        ])->values();
    }

    public function hasExternalSources(Voucher $voucher): bool
    {
        $voucher->loadMissing('passengers');

        return $voucher->passengers->contains(fn ($passenger) => ($passenger->pivot?->visa_group_id ?? $passenger->visa_group_id) !== $voucher->visa_group_id);
    }

    /** Only travel information, never an original agent's prices or notes. */
    public function printRows(Voucher $voucher): array
    {
        $voucher->loadMissing('passengers');
        $positions = $voucher->passengers->values()->mapWithKeys(fn ($passenger, $index) => [$passenger->id => $index + 1]);

        return $this->segments($voucher)->map(function (array $segment) use ($positions): array {
            $group = $segment['group'];
            $transport = $group && $group->transport_mode !== VisaGroup::TRANSPORT_NONE;
            $providers = $group?->transport_mode === VisaGroup::TRANSPORT_SPECIALIZED
                ? $group->transportItems->map(fn ($item) => $item->transportVendor?->name)->filter()->unique()->implode(', ')
                : $group?->mandatoryTransportVendor?->name;

            return [
                'passenger_numbers' => $segment['passengers']->map(fn ($passenger) => $positions[$passenger->id])->implode(', '),
                'visa_provider' => $group?->includes_visa ? ($group->vendor?->name ?: 'Provider not assigned') : 'Not purchased here',
                'transport_provider' => $transport ? ($providers ?: 'Provider not assigned') : 'Self-arranged',
                'transport_mode' => $transport ? str_replace('_', ' ', $group->transport_mode) : null,
                'transport_items' => ! $transport ? [] : $group->transportItems->map(fn ($item) => [
                    'scheduled_at' => $item->scheduled_at?->format('d M Y H:i'),
                    'provider' => $item->transportVendor?->name,
                    'vehicle' => $item->service?->name ?: $item->service?->vehicle_type,
                    'quantity' => $item->quantity,
                    'route' => $item->sector?->name ?: $item->description,
                    'driver' => $item->driver?->name ?: ($item->service?->driver?->name ?: $item->service?->driver_name),
                    'phone' => $item->driver?->phone ?: ($item->service?->driver?->phone ?: $item->service?->driver_contact),
                ])->all(),
            ];
        })->all();
    }
}
