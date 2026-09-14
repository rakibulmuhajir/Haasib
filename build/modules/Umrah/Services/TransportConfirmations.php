<?php

namespace App\Modules\Umrah\Services;

use App\Constants\Permissions;
use App\Models\User;
use App\Modules\Umrah\Models\VisaGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransportConfirmations
{
    public function __construct(private TravelAccessService $access) {}

    public function canManage(VisaGroup $group, User $user): bool
    {
        return ! $this->access->isAgentMember($group->company_id, $user)
            && $user->hasCompanyPermission(Permissions::UMRAH_GROUP_UPDATE)
            && ! in_array($group->status, [VisaGroup::STATUS_CANCELLED, VisaGroup::STATUS_CLOSED], true);
    }

    public function rows(VisaGroup $group, bool $private = false): array
    {
        if ($group->transport_mode === VisaGroup::TRANSPORT_NONE) {
            return [];
        }
        $group->loadMissing('transportItems');
        $bookings = $group->transport_mode === VisaGroup::TRANSPORT_STANDARD_BUS
            ? [['id' => 'standard_bus', 'label' => 'Standard bus provider booking', 'date' => null,
                'supplier' => $group->mandatory_transport_vendor_id,
                'revision' => $group->transport_booking_revision,
                'fingerprint' => $group->only(['transport_mode', 'mandatory_transport_vendor_id', 'transport_service_id', 'driver_id', 'transport_quantity', 'transport_pax_capacity'])]]
            : $group->transportItems->map(fn ($item) => [
                'id' => $item->id, 'label' => $item->description, 'date' => $item->scheduled_at?->format('Y-m-d H:i:s'),
                'supplier' => $item->transport_vendor_id, 'revision' => $item->booking_revision,
                'fingerprint' => $item->only(['transport_vendor_id', 'transport_service_id', 'transport_sector_id', 'transport_package_id', 'driver_id', 'description', 'scheduled_at', 'terminal', 'quantity', 'passenger_count']),
            ])->all();

        return array_map(function (array $booking) use ($group, $private) {
            $saved = ($group->transport_confirmations ?? [])[$booking['id']] ?? [];
            $revision = hash('sha256', ($booking['revision'] ?? 'legacy').json_encode($booking['fingerprint']));
            $current = ($saved['revision'] ?? '') === $revision;
            $status = $saved && ! $current ? 'reconfirm' : ($saved['status'] ?? ($booking['revision'] ? 'pending' : 'not_recorded'));
            $row = ['id' => $booking['id'], 'label' => $booking['label'], 'scheduled_at' => $booking['date'],
                'revision' => $revision, 'version' => count($saved['history'] ?? []), 'status' => $status,
                'supplier_assigned' => filled($booking['supplier']),
                'reference' => $current ? ($saved['reference'] ?? null) : null];
            if ($private) {
                $row['internal_note'] = $current ? ($saved['internal_note'] ?? null) : null;
                $row['history'] = array_reverse($saved['history'] ?? []);
            }

            return $row;
        }, $bookings);
    }

    public function persist(string $companyId, string $groupId, User $user, array $data): void
    {
        DB::transaction(function () use ($companyId, $groupId, $user, $data) {
            $group = VisaGroup::where('company_id', $companyId)->lockForUpdate()->find($groupId);
            if (! $group || ! $this->canManage($group, $user)) {
                throw ValidationException::withMessages(['status' => 'This group is not available for transport confirmation updates.']);
            }
            $row = collect($this->rows($group, true))->firstWhere('id', $data['booking_id']);
            if (! $row || $row['revision'] !== $data['revision'] || $row['version'] !== (int) $data['version']) {
                throw ValidationException::withMessages(['status' => 'This booking changed. Reload before saving.']);
            }
            if ($data['status'] === 'confirmed' && ! $row['supplier_assigned']) {
                throw ValidationException::withMessages(['status' => 'Select the transport provider before confirming.']);
            }
            $entry = ['revision' => $row['revision'], 'status' => $data['status'],
                'reference' => $data['reference'] ?? null, 'internal_note' => $data['internal_note'] ?? null,
                'cancellation_reason' => $data['status'] === 'cancelled' ? $data['cancellation_reason'] : null,
                'supplier_acknowledgement' => $data['status'] === 'cancelled' ? $data['supplier_acknowledgement'] : null,
                'updated_by_name' => $user->name, 'updated_by_user_id' => $user->id, 'updated_at' => now()->toIso8601String()];
            $all = $group->transport_confirmations ?? [];
            $history = $all[$row['id']]['history'] ?? [];
            $history[] = $entry;
            $all[$row['id']] = [...$entry, 'history' => $history];
            $group->transport_confirmations = $all;
            $group->save();
        });
    }
}
