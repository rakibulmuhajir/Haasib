<?php

namespace App\Modules\Umrah\Handlers;

use App\Modules\Umrah\Commands\CreateQuickBooking;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Services\UmrahCoreService;
use Illuminate\Database\QueryException;

final class CreateQuickBookingHandler
{
    public function __construct(private readonly UmrahCoreService $core) {}

    public function handle(CreateQuickBooking $command): VisaGroup
    {
        $data = $command->data;
        $idempotencyKey = (string) $data['idempotency_key'];

        $existing = VisaGroup::where('company_id', $command->companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $data['hotel_info'] = $this->hotelPreferences($command->companyId, $data);
        $data = $this->core->resolveGroupVendors($command->companyId, $data, true);

        try {
            return $this->core->createGroup($command->companyId, $data);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23505') {
                throw $exception;
            }

            $existing = VisaGroup::where('company_id', $command->companyId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return $existing;
        }
    }

    /** @param array<string, mixed> $data */
    private function hotelPreferences(string $companyId, array $data): array
    {
        $hotelIds = array_values(array_filter([
            $data['hotel_makkah_id'] ?? null,
            $data['hotel_madinah_id'] ?? null,
        ]));
        $hotels = Hotel::where('company_id', $companyId)
            ->whereIn('id', $hotelIds)
            ->get(['id', 'name', 'city'])
            ->keyBy('id');

        $makkah = $hotels->get($data['hotel_makkah_id'] ?? '');
        $madinah = $hotels->get($data['hotel_madinah_id'] ?? '');

        return [
            'makkah' => $makkah?->name,
            'madinah' => $madinah?->name,
            'notes' => null,
            'room_type' => $data['room_type'] ?? null,
            'makkah_nights' => (int) ($data['makkah_nights'] ?? 0),
            'madinah_nights' => (int) ($data['madinah_nights'] ?? 0),
            'makkah_hotel_id' => $makkah?->id,
            'madinah_hotel_id' => $madinah?->id,
        ];
    }
}
