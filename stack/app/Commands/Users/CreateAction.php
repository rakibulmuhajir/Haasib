<?php

namespace App\Commands\Users;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\User;
use App\Models\Company;
use App\Notifications\UserCreated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Exception;

class CreateAction extends BaseCommand
{
    public function handle(): User
    {
        return $this->executeInTransaction(function () {
            $adminId = $this->context->getUserId();
            
            if (!$adminId) {
                throw new Exception('Invalid service context: missing admin user ID');
            }

            $admin = User::findOrFail($adminId);

            // Validate admin permissions
            if (!$admin->hasRole('super_admin')) {
                throw new Exception('Only super administrators can create users');
            }

            // Validate system role assignment
            if (!$admin->hasRole('super_admin') && 
                $this->getValue('system_role') === 'super_admin') {
                throw new Exception('Only super administrators can create other super administrators');
            }

            // Create user
            $user = User::create([
                'id' => Str::uuid(),
                'name' => $this->getValue('name'),
                'email' => $this->getValue('email'),
                'username' => $this->getValue('username'),
                'password' => Hash::make($this->getValue('password')),
                'system_role' => $this->getValue('system_role'),
                'is_active' => $this->boolean('is_active', true),
                'email_verified_at' => now(), // Auto-verify for admin-created users
            ]);

            // Handle company assignments
            $companies = $this->getValue('companies', []);
            if (!empty($companies)) {
                $this->assignCompanies($user, $companies, $admin);
            }

            // Send notification to new user (if email is valid)
            try {
                Notification::send($user, new UserCreated($user, $this->getValue('password')));
            } catch (\Exception $e) {
                Log::warning('Failed to send user creation notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }

            $this->audit('user.created', [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'system_role' => $user->system_role,
                'is_active' => $user->is_active,
                'companies_assigned' => count($companies),
                'created_by_admin_id' => $adminId,
            ]);

            return $user->load('companies');
        });
    }

    private function assignCompanies(User $user, array $companies, User $admin): void
    {
        foreach ($companies as $companyData) {
            // Validate company exists
            $company = Company::findOrFail($companyData['company_id']);

            // Validate role assignment permissions
            if ($companyData['role'] === 'owner' && !$admin->hasRole('super_admin')) {
                throw new Exception('Only super administrators can assign owner role to companies');
            }

            // Check for duplicate assignments
            if ($user->companies()->where('company_id', $company->id)->exists()) {
                throw new Exception("User is already assigned to company: {$company->name}");
            }

            // Create company assignment
            $user->companies()->attach($company->id, [
                'role' => $companyData['role'],
                'is_active' => true,
                'created_by' => $admin->id,
                'created_at' => now(),
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