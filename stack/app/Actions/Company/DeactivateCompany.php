<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyPermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeactivateCompany
{
    public function __construct(
        private CompanyPermissionService $permissionService
    ) {}

    public function execute(Company $company, User $user, ?string $reason = null): array
    {
        // Check permissions
        if (! $this->permissionService->userHasCompanyPermission($user, $company, 'company.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You do not have permission to deactivate this company');
        }

        // Check if company is already inactive
        if (! $company->is_active) {
            return [
                'success' => false,
                'message' => 'Company is already inactive',
                'company' => $company,
            ];
        }

        // Additional check: only system owners or company owners can deactivate
        $userRole = $this->permissionService->getUserRoleInCompany($user, $company);
        if (! in_array($user->system_role, ['system_owner', 'super_admin']) && $userRole !== 'owner') {
            throw new \Illuminate\Auth\Access\AuthorizationException('Only company owners can deactivate companies');
        }

        try {
            DB::beginTransaction();

            // Deactivate the company
            $company->update(['is_active' => false]);

            // Deactivate all users in the company (preserves the relationship data)
            $company->users()->update(['company_user.is_active' => false]);

            // Log deactivation
            Log::info('Company deactivated', [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'deactivated_by' => $user->id,
                'deactivated_by_name' => $user->name,
                'reason' => $reason,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Company deactivated successfully',
                'company' => $company->fresh(['users']),
                'reason' => $reason,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to deactivate company', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to deactivate company: ' . $e->getMessage());
        }
    }
}