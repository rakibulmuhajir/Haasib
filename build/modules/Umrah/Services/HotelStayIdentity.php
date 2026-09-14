<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\Voucher;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

class HotelStayIdentity
{
    public function project(Voucher $voucher): array
    {
        return $this->legacy($voucher->id, $voucher->hotel_stays ?? []);
    }

    private function legacy(string $voucherId, array $stays): array
    {
        return array_map(function (array $stay, int $index) use ($voucherId) {
            $stay['stay_id'] ??= Uuid::uuid5(Uuid::NAMESPACE_URL, "haasib:{$voucherId}:hotel:{$index}")->toString();
            // Null distinguishes pre-feature stays from new pending bookings.
            $stay['stay_revision'] ??= null;

            return $stay;
        }, array_values($stays), array_keys(array_values($stays)));
    }

    public function normalize(Voucher $voucher): void
    {
        $old = $this->legacy($voucher->id, $voucher->getOriginal('hotel_stays') ?? []);
        $incoming = array_values($voucher->hotel_stays ?? []);
        $used = [];
        $matches = [];
        // Match explicit IDs first, then exact reservations before positional fallback.
        foreach ($incoming as $i => $stay) {
            foreach ($old as $j => $previous) {
                if (! isset($used[$j]) && isset($stay['stay_id']) && $stay['stay_id'] === $previous['stay_id']) {
                    $matches[$i] = $j;
                    $used[$j] = true;
                    break;
                }
            }
        }
        foreach ($incoming as $i => $stay) {
            if (isset($matches[$i])) {
                continue;
            }
            foreach ($old as $j => $previous) {
                if (! isset($used[$j]) && $this->fingerprint($stay) === $this->fingerprint($previous)) {
                    $matches[$i] = $j;
                    $used[$j] = true;
                    break;
                }
            }
        }
        foreach ($incoming as $i => &$stay) {
            if (! isset($matches[$i]) && isset($old[$i]) && ! isset($used[$i])) {
                $matches[$i] = $i;
                $used[$i] = true;
            }
            $previous = isset($matches[$i]) ? $old[$matches[$i]] : null;
            $stay['stay_id'] = $previous['stay_id'] ?? (string) Str::uuid();
            $stay['stay_revision'] = $previous && $this->fingerprint($stay) === $this->fingerprint($previous)
                ? $previous['stay_revision'] : (string) Str::uuid();
        }
        unset($stay);
        $voucher->hotel_stays = $incoming;
    }

    public function fingerprint(array $stay): string
    {
        $values = [];
        foreach (['source', 'hotel_id', 'hotel_name', 'city', 'hotel_vendor_id', 'check_in_date', 'check_out_date', 'room_type', 'room_count', 'beds_per_room'] as $field) {
            $values[$field] = trim((string) ($stay[$field] ?? ''));
        }

        return hash('sha256', json_encode($values));
    }
}
