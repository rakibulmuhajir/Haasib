<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class AuthService
{
    public function canAccessCompany(User $user, Company $company): bool
    {
        return $user->canAccessCompany($company->id);
    }

    public function getUserPermissions(User $user, Company $company): array
    {
        // For now, return basic permissions based on user role
        // This should be replaced with proper RBAC implementation
        return [];
    }

    public function getUserRole(User $user, Company $company): ?string
    {
        $pivot = $user->companies()
            ->where('company_id', $company->id)
            ->first()?->pivot;
            
        return $pivot?->role ?? 'member';
    }
}