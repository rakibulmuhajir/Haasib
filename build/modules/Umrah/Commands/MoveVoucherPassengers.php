<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\VoucherPassengerAssignmentService;

final class MoveVoucherPassengers
{
    public function __construct(
        public readonly Voucher $source,
        public readonly Voucher $target,
        public readonly array $passengerIds,
        public readonly bool $allowApproved = false,
        public readonly ?string $reason = null,
    ) {}

    public function handle(VoucherPassengerAssignmentService $assignments): array
    {
        return $assignments->move($this->source, $this->target, $this->passengerIds, $this->allowApproved, $this->reason);
    }
}
