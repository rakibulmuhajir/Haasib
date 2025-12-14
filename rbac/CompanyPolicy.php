<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view the company.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company);
    }

    /**
     * Determine whether the user can view company settings.
     */
    public function viewSettings(User $user, Company $company): bool
    {
        if (!$user->belongsToCompany($company)) {
            return false;
        }

        return $user->can('company_settings_view');
    }

    /**
     * Determine whether the user can update company settings.
     */
    public function updateSettings(User $user, Company $company): bool
    {
        if (!$user->belongsToCompany($company)) {
            return false;
        }

        return $user->can('company_settings_update');
    }

    /**
     * Determine whether the user can view members.
     */
    public function viewMembers(User $user, Company $company): bool
    {
        if (!$user->belongsToCompany($company)) {
            return false;
        }

        return $user->can('company_members_view');
    }

    /**
     * Determine whether the user can invite members.
     */
    public function inviteMembers(User $user, Company $company): bool
    {
        if (!$user->belongsToCompany($company)) {
            return false;
        }

        return $user->can('company_members_invite');
    }

    /**
     * Determine whether the user can update member roles.
     */
    public function updateMemberRole(User $user, Company $company): bool
    {
        if (!$user->belongsToCompany($company)) {
            return false;
        }

        return $user->can('company_members_update_role');
    }

    /**
     * Determine whether the user can remove members.
     */
    public function removeMembers(User $user, Company $company): bool
    {
        if (!$user->belongsToCompany($company)) {
            return false;
        }

        return $user->can('company_members_remove');
    }
}
