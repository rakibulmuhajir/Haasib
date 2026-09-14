<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Services\HotelConfirmations;

readonly class SaveHotelConfirmation
{
    public function __construct(public string $companyId, public string $voucherId, public string $userId, public array $data) {}

    public function handle(HotelConfirmations $service): void
    {
        $service->persist($this->companyId, $this->voucherId, $this->userId, $this->data);
    }
}
