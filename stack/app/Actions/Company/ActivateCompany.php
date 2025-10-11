<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyPermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivateCompany
{
    public function __construct(
        private CompanyPermissionService $permissionService
    ) {}

    public function execute(Company $company, User $user): array
    {
        // Check permissions
        if (! $this->permissionService->userHasCompanyPermission($user, $company, 'company.manage')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You do not have permission to activate this company');
        }

        // Check if company is already active
        if ($company->is_active) {
            return [
                'success' => false,
                'message' => 'Company is already active',
                'company' => $company,
            ];
        }

        try {
            DB::beginTransaction();

            // Activate the company
            $company->update(['is_active' => true]);

            // Activate all active users in the company
            $company->users()
                ->where('company_user.is_active', true)
                ->update(['company_user.is_active' => true]);

            // Log activation
            Log::info('Company activated', [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'activated_by' => $user->id,
                'activated_by_name' => $user->name,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Company activated successfully',
                'company' => $company->fresh(['users']),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to activate company', [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to activate company: ' . $e->getMessage());
        }
    }
}