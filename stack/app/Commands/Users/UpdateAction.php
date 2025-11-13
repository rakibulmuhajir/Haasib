<?php

namespace App\Commands\Users;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Exception;

class UpdateAction extends BaseCommand
{
    public function handle(): User
    {
        return $this->executeInTransaction(function () {
            $adminId = $this->context->getUserId();
            $userId = $this->getValue('id');
            
            if (!$adminId || !$userId) {
                throw new Exception('Invalid service context: missing admin or user ID');
            }

            $admin = User::findOrFail($adminId);
            $user = User::findOrFail($userId);

            // Validate admin permissions and user management capabilities
            $this->validatePermissions($admin, $user);

            // Store original values for audit
            $originalValues = [
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'system_role' => $user->system_role,
                'is_active' => $user->is_active,
            ];

            // Update user basic information
            $user->update([
                'name' => $this->getValue('name'),
                'email' => $this->getValue('email'),
                'username' => $this->getValue('username'),
                'system_role' => $this->getValue('system_role'),
                'is_active' => $this->boolean('is_active'),
            ]);

            // Update password if provided
            if ($this->hasValue('password') && !empty($this->getValue('password'))) {
                $user->update([
                    'password' => Hash::make($this->getValue('password')),
                    'password_changed_at' => now(),
                    'changed_password_by' => $adminId,
                ]);

                $this->audit('user.password_changed', [
                    'user_id' => $user->id,
                    'changed_by_admin_id' => $adminId,
                ]);
            }

            // Update company assignments
            $companies = $this->getValue('companies', []);
            $this->updateCompanyAssignments($user, $companies, $admin);

            // Compare changes for detailed audit
            $changes = $this->getChanges($originalValues, $user);

            $this->audit('user.updated', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'system_role' => $user->system_role,
                'is_active' => $user->is_active,
                'changes' => $changes,
                'companies_updated' => count($companies),
                'updated_by_admin_id' => $adminId,
            ]);

            return $user->load('companies');
        });
    }

    private function validatePermissions(User $admin, User $user): void
    {
        // Cannot modify yourself
        if ($user->id === $admin->id) {
            throw new Exception('You cannot modify your own account through the admin interface');
        }

        // Super admin validation
        if (!$admin->hasRole('super_admin') && 
            $this->getValue('system_role') === 'super_admin') {
            throw new Exception('Only super administrators can modify super administrator role');
        }

        // Admin validation for role hierarchy
        if ($admin->hasRole('admin') && 
            in_array($this->getValue('system_role'), ['admin', 'super_admin'])) {
            throw new Exception('Regular administrators cannot modify admin or super administrator roles');
        }

        // Validate deactivation permissions
        if (!$admin->hasRole('super_admin') && 
            !$this->boolean('is_active', $user->is_active) && 
            $this->getValue('system_role') !== 'guest') {
            throw new Exception('Only super administrators can deactivate non-guest users');
        }
    }

    private function updateCompanyAssignments(User $user, array $companies, User $admin): void
    {
        // Get current company assignments
        $currentAssignments = $user->companies()->get();

        // Remove all current assignments first
        $user->companies()->detach();

        // Log removed assignments
        foreach ($currentAssignments as $company) {
            $this->audit('user.company_removed', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'company_name' => $company->name,
                'removed_by_admin_id' => $admin->id,
            ]);
        }

        // Add new assignments
        if (!empty($companies)) {
            foreach ($companies as $companyData) {
                // Validate company exists
                $company = Company::findOrFail($companyData['company_id']);

                // Validate role assignment permissions
                if ($companyData['role'] === 'owner' && !$admin->hasRole('super_admin')) {
                    throw new Exception('Only super administrators can assign owner role to companies');
                }

                // Prevent non-super admins from having no company assignments
                if (!$admin->hasRole('super_admin') && $user->system_role !== 'super_admin' && 
                    $user->system_role !== 'admin') {
                    // This will be validated at the form request level, but double-check here
                    if (empty($companies)) {
                        throw new Exception('Non-super administrators must belong to at least one company');
                    }
                }

                // Create company assignment
                $user->companies()->attach($company->id, [
                    'role' => $companyData['role'],
                    'is_active' => true,
                    'updated_by' => $admin->id,
                    'updated_at' => now(),
                ]);

                // Log company assignment
                $this->audit('user.company_assigned', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'role' => $companyData['role'],
                    'assigned_by_admin_id' => $admin->id,
                ]);
            }
        }
    }

    private function getChanges(array $original, User $user): array
    {
        $changes = [];
        
        foreach ($original as $key => $value) {
            if ($user->$key !== $value) {
                $changes[$key] = [
                    'from' => $value,
                    'to' => $user->$key,
                ];
            }
        }

        return $changes;
    }
}