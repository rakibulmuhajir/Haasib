<?php

namespace App\Modules\Umrah\Commands;

use App\Models\User;
use App\Modules\Umrah\Services\TransportConfirmations;

readonly class SaveTransportConfirmation
{
    public function __construct(public string $companyId, public string $groupId, public User $user, public array $data) {}

    public function handle(TransportConfirmations $service): void
    {
        $service->persist($this->companyId, $this->groupId, $this->user, $this->data);
    }
}
