<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class AuthService
{
    public function canAccessCompany(User $user, Company $company, ?string $permission = null): bool
    {
        // Check if user is super admin
        if ($user->isSuperAdmin()) {
            return true;
        }
        
        // Check if user has access to the company through the pivot table
        $hasAccess = $user->companies()->where('companies.id', $company->id)->exists();
        
        // If specific permission is requested, we could implement that check here
        // For now, if they have access to the company, they have all permissions
        
        return $hasAccess;
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