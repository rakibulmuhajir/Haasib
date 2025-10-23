<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Company $company): bool
    {
        // Users can view companies they belong to
        return $user->companies()
            ->where('auth.companies.id', $company->id)
            ->where('auth.company_user.is_active', true)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Super admins can create companies
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Company $company): bool
    {
        Log::info('🔐 [CompanyPolicy DEBUG] Starting authorization check', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_system_role' => $user->system_role,
            'company_id' => $company->id,
            'company_name' => $company->name,
        ]);

        // Super admins can update any company (check both possible values)
        if ($user->system_role === 'superadmin' || $user->system_role === 'system_owner') {
            Log::info('✅ [CompanyPolicy DEBUG] Super admin access granted', [
                'user_system_role' => $user->system_role,
            ]);

            return true;
        }

        Log::info('🔍 [CompanyPolicy DEBUG] User is not super admin, checking company membership', [
            'user_system_role' => $user->system_role,
        ]);

        try {
            // Check if user belongs to this company with appropriate role using direct pivot query
            Log::info('🔍 [CompanyPolicy DEBUG] Querying company user relationship directly', [
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);

            // Query the pivot table directly instead of relying on the relationship
            $companyUserPivot = DB::table('auth.company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->first();

            Log::info('📊 [CompanyPolicy DEBUG] Direct pivot query result', [
                'pivot_found' => ! is_null($companyUserPivot),
                'pivot_data' => $companyUserPivot ? [
                    'user_id' => $companyUserPivot->user_id,
                    'company_id' => $companyUserPivot->company_id,
                    'role' => $companyUserPivot->role,
                    'is_active' => $companyUserPivot->is_active,
                    'created_at' => $companyUserPivot->created_at,
                ] : null,
            ]);

            if (! $companyUserPivot) {
                Log::warning('❌ [CompanyPolicy DEBUG] No active company user relationship found in pivot table', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ]);

                return false;
            }

            $userRole = $companyUserPivot->role;

            Log::info('👤 [CompanyPolicy DEBUG] User role extracted', [
                'user_role' => $userRole,
            ]);

            if (! $userRole) {
                Log::warning('❌ [CompanyPolicy DEBUG] No role found in pivot', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'pivot' => $companyUser->pivot,
                ]);

                return false;
            }

            // Only owners and admins can update company details
            $allowedRoles = ['owner', 'admin'];
            $hasPermission = in_array($userRole, $allowedRoles);

            Log::info($hasPermission ? '✅ [CompanyPolicy DEBUG] Access granted' : '❌ [CompanyPolicy DEBUG] Access denied', [
                'user_role' => $userRole,
                'allowed_roles' => $allowedRoles,
                'has_permission' => $hasPermission,
            ]);

            return $hasPermission;

        } catch (\Exception $e) {
            // Log error but deny access on exceptions
            Log::error('💥 [CompanyPolicy DEBUG] Exception occurred during authorization', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'error' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Company $company): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Company $company): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return false;
    }
}
