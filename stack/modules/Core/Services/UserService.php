<?php

namespace Modules\Core\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * UserService - Handles user-related business logic
 *
 * This service follows the Haasib Constitution principles, particularly:
 * - RBAC Integrity: Respects seeded role/permission catalog
 * - Tenancy & RLS Safety: Enforces company scoping
 * - Audit, Idempotency & Observability: Logs all user operations
 * - Module Governance: Part of the Core module
 *
 * @link https://github.com/Haasib/haasib/blob/main/.specify/memory/constitution.md
 */
class UserService
{
    use AuditLogging;

    /**
     * Create a user with temporary password
     *
     * @param  array  $userData  User data to create
     * @param  ServiceContext  $context  The service context
     * @param  bool  $mustChangePassword  Whether user must change password on first login
     * @return User The created user
     */
    public function createUser(array $userData, ServiceContext $context, bool $mustChangePassword = true): User
    {
        // Validate required fields
        if (empty($userData['email']) || empty($userData['name'])) {
            throw new \InvalidArgumentException('Email and name are required');
        }

        // Validate email format
        if (! filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        // Check if user already exists
        if (User::where('email', $userData['email'])->exists()) {
            throw new \InvalidArgumentException('User with this email already exists');
        }

        // Hash the password if provided, otherwise create with temporary password
        if (! empty($userData['password'])) {
            $userData['password'] = Hash::make($userData['password']);
        } else {
            $userData['password'] = Hash::make('temp123456'); // Temporary default
        }

        // Set if user needs to change password
        $userData['must_change_password'] = $mustChangePassword;

        $user = new User($userData);

        if (! $user->save()) {
            throw new \RuntimeException('Failed to create user: validation failed');
        }

        $this->logAudit('user.created', [
            'user_id' => $user->id,
            'email' => $user->email,
        ], $context);

        return $user->fresh();
    }

    /**
     * Update user profile
     *
     * @param  User  $user  The user to update
     * @param  array  $data  The update data
     * @param  ServiceContext  $context  The service context
     * @return User The updated user
     */
    public function updateProfile(User $user, array $data, ServiceContext $context): User
    {
        $originalData = $user->getOriginal();

        $user->update($data);

        $this->logAudit('user.updated', [
            'user_id' => $user->id,
            'email' => $user->email,
        ], $context, [
            'updated_fields' => array_keys($data),
        ]);

        return $user->fresh();
    }

    /**
     * Change user password
     *
     * @param  User  $user  The user whose password to change
     * @param  string  $newPassword  The new password
     * @param  ServiceContext  $context  The service context
     * @return bool True if password was changed successfully
     */
    public function changePassword(User $user, string $newPassword, ServiceContext $context): bool
    {
        $user->password = Hash::make($newPassword);
        $user->must_change_password = false;
        $result = $user->save();

        $this->logAudit('user.password_changed', [
            'user_id' => $user->id,
        ], $context);

        return $result;
    }

    /**
     * Activate a user
     *
     * @param  User  $user  The user to activate
     * @param  ServiceContext  $context  The service context
     * @return bool True if activation was successful
     */
    public function activateUser(User $user, ServiceContext $context): bool
    {
        $user->is_active = true;
        $result = $user->save();

        $this->logAudit('user.activated', [
            'user_id' => $user->id,
            'email' => $user->email,
        ], $context);

        return $result;
    }

    /**
     * Deactivate a user
     *
     * @param  User  $user  The user to deactivate
     * @param  ServiceContext  $context  The service context
     * @return bool True if deactivation was successful
     */
    public function deactivateUser(User $user, ServiceContext $context): bool
    {
        // Prevent deactivating the only super admin
        if ($user->isSuperAdmin()) {
            $superAdminCount = User::where('system_role', 'superadmin')->count();
            if ($superAdminCount <= 1) {
                throw new \Exception('Cannot deactivate the only super admin');
            }
        }

        $user->is_active = false;
        $result = $user->save();

        $this->logAudit('user.deactivated', [
            'user_id' => $user->id,
            'email' => $user->email,
        ], $context);

        return $result;
    }

    /**
     * Invite user to company
     *
     * @param  string  $email  The email to invite
     * @param  Company  $company  The company to invite to
     * @param  string  $role  The role to assign
     * @param  ServiceContext  $context  The service context
     * @return array [User, string status] The invited user and status string
     */
    public function inviteToCompany(string $email, Company $company, string $role, ServiceContext $context): array
    {
        // Validate role
        $validRoles = ['owner', 'admin', 'manager', 'accountant', 'employee', 'viewer'];
        if (! in_array($role, $validRoles)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        // Check if user exists
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Create new user with temporary password
            $userData = [
                'name' => $email,
                'email' => $email,
                'password' => 'temp123456',
                'must_change_password' => true,
            ];

            $user = $this->createUser($userData, $context, true);
            $wasCreated = true;
        } else {
            $wasCreated = false;
        }

        // Add user to company if not already added
        $existingPivot = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $existingPivot) {
            DB::table('auth.company_user')->insert([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => $role,
                'is_active' => true,
                'invited_by_user_id' => $context->getActingUser()?->id,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Update the role if user is already in the company
            DB::table('auth.company_user')
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->update([
                    'role' => $role,
                    'updated_at' => now(),
                ]);
        }

        $this->logAudit(
            $wasCreated ? 'user.created_and_invited' : 'user.invited',
            [
                'user_id' => $user->id,
                'email' => $email,
                'company_id' => $company->id,
                'role' => $role,
            ],
            $context
        );

        return [$user, $wasCreated ? 'created' : 'existing'];
    }

    /**
     * Remove user from company
     *
     * @param  User  $user  The user to remove
     * @param  Company  $company  The company to remove from
     * @param  ServiceContext  $context  The service context
     * @return bool True if removal was successful
     */
    public function removeFromCompany(User $user, Company $company, ServiceContext $context): bool
    {
        // Check if user is owner (we'll need to implement this check differently)
        // Note: isOwnerOfCompany method may not exist, so we'll implement the check directly
        $companyUser = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if ($companyUser && $companyUser->role === 'owner') {
            $ownerCount = DB::table('auth.company_user')
                ->where('company_id', $company->id)
                ->where('role', 'owner')
                ->where('is_active', true)
                ->count();

            if ($ownerCount <= 1) {
                throw new \Exception('Cannot remove the last owner of the company');
            }
        }

        $result = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->update([
                'is_active' => false,
                'joined_at' => DB::raw('joined_at'), // Preserve original joined_at
                'updated_at' => now(),
            ]);

        $this->logAudit('user.removed_from_company', [
            'user_id' => $user->id,
            'email' => $user->email,
            'company_id' => $company->id,
            'role' => $user->getRoleInCompany($company),
        ], $context);

        return $result > 0;
    }

    /**
     * Change user role in company
     *
     * @param  User  $user  The user whose role to change
     * @param  Company  $company  The company context
     * @param  string  $newRole  The new role
     * @param  ServiceContext  $context  The service context
     * @return bool True if role change was successful
     */
    public function changeCompanyRole(User $user, Company $company, string $newRole, ServiceContext $context): bool
    {
        // Validate role
        $validRoles = ['owner', 'admin', 'manager', 'accountant', 'employee', 'viewer'];
        if (! in_array($newRole, $validRoles)) {
            throw new \InvalidArgumentException("Invalid role: {$newRole}");
        }

        $oldRole = $user->getRoleInCompany($company);

        $result = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->update([
                'role' => $newRole,
                'updated_at' => now(),
            ]);

        $this->logAudit('user.role_changed', [
            'user_id' => $user->id,
            'email' => $user->email,
            'company_id' => $company->id,
            'old_role' => $oldRole,
            'new_role' => $newRole,
        ], $context);

        return $result > 0;
    }

    /**
     * Get user with their companies
     *
     * @param  string  $userId  The user ID
     * @param  ServiceContext  $context  The service context
     * @return User|null The user with companies or null
     */
    public function getUserWithCompanies(string $userId, ServiceContext $context): ?User
    {
        $user = User::with(['companies', 'companyUsers'])->find($userId);

        $this->logAudit('user.viewed_with_companies', [
            'user_id' => $userId,
        ], $context);

        return $user;
    }

    /**
     * Get user's permissions for a company
     *
     * @param  User  $user  The user to check
     * @param  Company  $company  The company context
     * @param  ServiceContext  $context  The service context
     * @return array The user's permissions
     */
    public function getUserPermissions(User $user, Company $company, ServiceContext $context): array
    {
        if ($user->isSuperAdmin()) {
            return ['*']; // All permissions for super admin
        }

        $companyUser = $user->companies()
            ->where('auth.companies.id', $company->id)
            ->first();

        if (! $companyUser) {
            return []; // No permissions if user doesn't belong to company
        }

        $role = $companyUser->pivot->role;

        $permissions = match ($role) {
            'owner' => ['*'],
            'admin' => [
                'manage_users', 'manage_modules', 'manage_settings',
                'view_reports', 'create_transactions', 'edit_transactions',
                'delete_transactions', 'manage_customers', 'manage_vendors',
                'view_audit_log', 'export_data',
            ],
            'accountant' => [
                'view_reports', 'create_transactions', 'edit_transactions',
                'manage_customers', 'manage_vendors', 'reconcile_accounts',
                'export_data',
            ],
            'viewer' => [
                'view_reports', 'view_transactions',
            ],
            'member' => [
                'view_own_transactions',
            ],
            default => []
        };

        // Add module-based permissions
        $enabledModules = $company->modules()
            ->where('auth.company_modules.is_active', true)
            ->pluck('auth.modules.permissions')
            ->flatten()
            ->unique()
            ->toArray();

        return array_merge($permissions, $enabledModules);
    }

    /**
     * Get user's recent activity
     *
     * @param  User  $user  The user to get activity for
     * @param  int  $limit  Number of activities to return
     * @param  ServiceContext  $context  The service context
     * @return \Illuminate\Database\Eloquent\Collection The recent activities
     */
    public function getRecentActivity(User $user, int $limit, ServiceContext $context)
    {
        $activities = $user->auditEntries()
            ->with('company')
            ->latest()
            ->limit($limit)
            ->get();

        $this->logAudit('user.activity_viewed', [
            'user_id' => $user->id,
            'limit' => $limit,
        ], $context);

        return $activities;
    }

    /**
     * Search users
     *
     * @param  string  $query  Search query
     * @param  Company|null  $company  Company to filter by (if applicable)
     * @param  int  $perPage  Number of results per page
     * @param  ServiceContext  $context  The service context
     * @return LengthAwarePaginator The search results
     */
    public function searchUsers(string $query, ?Company $company, int $perPage, ServiceContext $context): LengthAwarePaginator
    {
        $userQuery = User::where(function ($q) use ($query) {
            $q->where('name', 'ILIKE', "%{$query}%")
                ->orWhere('email', 'ILIKE', "%{$query}%");
        });

        if ($company && (! $context->getActingUser() || ! $context->getActingUser()->isSuperAdmin())) {
            // Only show users from this company
            $userQuery->whereHas('companies', function ($q) use ($company) {
                $q->where('auth.companies.id', $company->id);
            });
        }

        $results = $userQuery->paginate($perPage);

        $this->logAudit('user.search_performed', [
            'query' => $query,
            'company_id' => $company?->id,
            'results_count' => $results->total(),
        ], $context);

        return $results;
    }

    /**
     * Get users by company
     *
     * @param  Company  $company  The company to get users for
     * @param  string|null  $role  Role to filter by (if applicable)
     * @param  bool  $activeOnly  Whether to return only active users
     * @param  ServiceContext  $context  The service context
     * @return \Illuminate\Database\Eloquent\Collection The company users
     */
    public function getCompanyUsers(Company $company, ?string $role, bool $activeOnly, ServiceContext $context)
    {
        $query = $company->users();

        if ($role) {
            $query->wherePivot('role', $role);
        }

        if ($activeOnly) {
            $query->wherePivot('is_active', true);
        }

        $users = $query->get();

        $this->logAudit('company.users_viewed', [
            'company_id' => $company->id,
            'role_filter' => $role,
            'active_only' => $activeOnly,
            'count' => $users->count(),
        ], $context);

        return $users;
    }

    /**
     * Check if email is available
     *
     * @param  string  $email  The email to check
     * @param  string|null  $excludeUserId  User ID to exclude from check (for updates)
     * @param  ServiceContext  $context  The service context
     * @return bool True if email is available
     */
    public function isEmailAvailable(string $email, ?string $excludeUserId, ServiceContext $context): bool
    {
        $query = User::where('email', $email);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        $exists = $query->exists();

        $this->logAudit('user.email_check', [
            'email' => $email,
            'available' => ! $exists,
        ], $context);

        return ! $exists;
    }

    /**
     * Get user statistics
     *
     * @param  ServiceContext  $context  The service context
     * @return array Statistics about users
     */
    public function getUserStatistics(ServiceContext $context): array
    {
        $user = $context->getUser();
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;

        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'super_admins' => User::where('system_role', 'superadmin')->count(),
            'users_with_companies' => User::whereHas('companies')->count(),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'can_manage_all' => $isSuperAdmin,
        ];

        $this->logAudit('user.statistics_viewed', $stats, $context);

        return $stats;
    }
}
