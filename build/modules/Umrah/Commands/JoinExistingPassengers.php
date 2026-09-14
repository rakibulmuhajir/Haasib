<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\ExistingPassengerJoinService;

final class JoinExistingPassengers
{
    public function __construct(public readonly Voucher $voucher, public readonly array $passengerIds) {}

    public function handle(ExistingPassengerJoinService $service): array
    {
        return $service->join($this->voucher, $this->passengerIds);
    }
}
