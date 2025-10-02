<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Models\Company;

trait HasCompanyContext
{
    /**
     * Act as a user with company context
     */
    protected function actingAsWithCompany(User $user, Company $company)
    {
        // Set session first
        $this->withSession(['current_company_id' => $company->id]);
        
        // Then act as user
        return $this->actingAs($user);
    }
    
    /**
     * Set team context for permission checking
     */
    protected function setTeamContext(Company $company = null)
    {
        setPermissionsTeamId($company?->id);
    }
    
    /**
     * Clear team context
     */
    protected function clearTeamContext()
    {
        setPermissionsTeamId(null);
    }
}