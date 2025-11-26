<?php

namespace App\Services;

use App\Models\Company;
use Spatie\Permission\PermissionRegistrar;

class CurrentCompany
{
    private ?Company $company = null;

    /**
     * Set the current company context.
     */
    public function set(Company $company): void
    {
        $this->company = $company;

        // Set Spatie team context for permission checks
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
    }

    /**
     * Get the current company.
     */
    public function get(): ?Company
    {
        return $this->company;
    }

    /**
     * Get the current company or fail.
     */
    public function getOrFail(): Company
    {
        if (!$this->company) {
            abort(500, 'No company context set.');
        }

        return $this->company;
    }

    /**
     * Check if company context is set.
     */
    public function exists(): bool
    {
        return $this->company !== null;
    }

    /**
     * Get the current company ID.
     */
    public function id(): ?int
    {
        return $this->company?->id;
    }

    /**
     * Clear the current company context.
     */
    public function clear(): void
    {
        $this->company = null;
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
