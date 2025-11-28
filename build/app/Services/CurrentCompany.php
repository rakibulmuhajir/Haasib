<?php

namespace App\Services;

use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

class CurrentCompany
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;

        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function getOrFail(): Company
    {
        if (!$this->company) {
            abort(500, 'No company context set.');
        }

        return $this->company;
    }

    public function exists(): bool
    {
        return $this->company !== null;
    }

    public function id(): ?string
    {
        return $this->company?->id;
    }

    public function clear(): void
    {
        $this->company = null;
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
