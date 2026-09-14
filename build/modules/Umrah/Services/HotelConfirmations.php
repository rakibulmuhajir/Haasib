<?php

namespace App\Modules\Umrah\Services;

use App\Constants\Permissions;
use App\Models\User;
use App\Modules\Umrah\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HotelConfirmations
{
    public function __construct(private HotelStayIdentity $identity, private TravelAccessService $access) {}

    public function canManage(Voucher $voucher, User $user): bool
    {
        return ! $this->access->isAgentMember($voucher->company_id, $user)
            && $user->hasCompanyPermission(Permissions::UMRAH_VOUCHER_UPDATE)
            && in_array($voucher->status, [Voucher::STATUS_DRAFT, Voucher::STATUS_APPROVED], true)
            && ! $voucher->superseded_at && ! $voucher->superseded_by_voucher_id;
    }

    public function rows(Voucher $voucher, bool $private = false): array
    {
        return array_map(function (array $stay) use ($voucher, $private) {
            $saved = ($voucher->hotel_confirmations ?? [])[$stay['stay_id']] ?? [];
            $revision = $this->revision($stay);
            $current = ($saved['revision'] ?? null) === $revision;
            $company = ($stay['source'] ?? 'self') === 'company';
            $status = ! $company ? 'agent_arranged' : ($saved !== [] && ! $current ? 'reconfirm' : ($saved['status'] ?? ($stay['stay_revision'] ? 'pending' : 'not_recorded')));
            $row = [
                'stay_id' => $stay['stay_id'], 'revision' => $revision,
                'version' => count($saved['history'] ?? []),
                'hotel_name' => $stay['hotel_name'] ?? '', 'city' => $stay['city'] ?? '',
                'check_in_date' => $stay['check_in_date'] ?? null, 'check_out_date' => $stay['check_out_date'] ?? null,
                'room_type' => $stay['room_type'] ?? null, 'room_count' => $stay['room_count'] ?? null,
                'status' => $status,
                'status_label' => ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled — replacement needed', 'reconfirm' => 'Needs reconfirmation', 'not_recorded' => 'Not recorded', 'agent_arranged' => 'Agent-arranged'][$status],
                'brn' => $company && $current ? ($saved['brn'] ?? null) : null,
                'confirmation_number' => $company && $current ? ($saved['confirmation_number'] ?? null) : null,
                'updated_at' => $company && $current ? ($saved['updated_at'] ?? null) : null,
            ];
            if ($private) {
                $row['internal_note'] = $current ? ($saved['internal_note'] ?? null) : null;
                $row['updated_by_name'] = $saved['updated_by_name'] ?? null;
                $row['history'] = array_reverse($saved['history'] ?? []);
                $row['cancellation_reason'] = $current ? ($saved['cancellation_reason'] ?? null) : null;
                $row['supplier_acknowledgement'] = $current ? ($saved['supplier_acknowledgement'] ?? null) : null;
            }

            return $row;
        }, $this->identity->project($voucher));
    }

    public function persist(string $companyId, string $voucherId, string $userId, array $data): void
    {
        DB::transaction(function () use ($companyId, $voucherId, $userId, $data) {
            $voucher = Voucher::where('company_id', $companyId)->lockForUpdate()->find($voucherId);
            $user = User::findOrFail($userId);
            if (! $voucher || ! $this->canManage($voucher, $user)) {
                throw ValidationException::withMessages(['status' => 'This voucher is not available for hotel confirmation updates.']);
            }
            $row = collect($this->rows($voucher, true))->firstWhere('stay_id', $data['stay_id']);
            if (! $row || $row['status'] === 'agent_arranged') {
                throw ValidationException::withMessages(['status' => 'Choose a company-arranged hotel stay.']);
            }
            if ($row['revision'] !== $data['revision'] || $row['version'] !== (int) $data['version']) {
                throw ValidationException::withMessages(['status' => 'This stay was changed by another update. Reload the page before saving.']);
            }
            if ($data['status'] === 'confirmed' && (blank($row['hotel_name']) || blank($row['check_in_date']) || blank($row['check_out_date']) || blank($row['room_type']) || (int) $row['room_count'] < 1)) {
                throw ValidationException::withMessages(['status' => 'Complete the hotel, dates and room arrangement before confirming.']);
            }
            $all = $voucher->hotel_confirmations ?? [];
            if ($data['status'] === 'cancelled' && (blank($data['cancellation_reason'] ?? null) || blank($data['supplier_acknowledgement'] ?? null))) {
                throw ValidationException::withMessages(['cancellation_reason' => 'Record the reason and supplier acknowledgement before cancelling a booking.']);
            }
            $history = $all[$row['stay_id']]['history'] ?? [];
            $entry = [
                'revision' => $row['revision'], 'status' => $data['status'],
                'brn' => $data['brn'] ?? null, 'confirmation_number' => $data['confirmation_number'] ?? null,
                'internal_note' => $data['internal_note'] ?? null,
                'cancellation_reason' => $data['status'] === 'cancelled' ? $data['cancellation_reason'] : null,
                'supplier_acknowledgement' => $data['status'] === 'cancelled' ? $data['supplier_acknowledgement'] : null,
                'updated_by_user_id' => $userId, 'updated_by_name' => $user->name,
                'updated_at' => now()->toIso8601String(),
            ];
            $history[] = $entry;
            $all[$row['stay_id']] = [...$entry, 'history' => $history];
            $voucher->hotel_confirmations = $all;
            $voucher->save();
        });
    }

    private function revision(array $stay): string
    {
        return hash('sha256', ($stay['stay_revision'] ?? 'legacy').':'.$this->identity->fingerprint($stay));
    }
}
