<?php

namespace App\Modules\Umrah\Commands;

use App\Modules\Umrah\Services\OperationViews;

readonly class SaveOperationView
{
    public function __construct(public string $companyId, public string $userId, public array $data, public ?string $deleteId = null) {}

    public function handle(OperationViews $views): void
    {
        $views->persist($this->companyId, $this->userId, $this->data, $this->deleteId);
    }
}
