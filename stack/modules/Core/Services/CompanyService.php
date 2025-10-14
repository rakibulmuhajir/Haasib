<?php

namespace Modules\Core\Services;

use App\Models\Company;
use App\Models\User;
use App\Support\ServiceContext;
use App\Traits\AuditLogging;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * CompanyService - Handles company-related business logic
 *
 * This service follows the Haasib Constitution principles, particularly:
 * - RBAC Integrity: Respects seeded role/permission catalog
 * - Tenancy & RLS Safety: Enforces company scoping
 * - Audit, Idempotency & Observability: Logs all company operations
 * - Module Governance: Part of the Core module
 *
 * @link https://github.com/Haasib/haasib/blob/main/.specify/memory/constitution.md
 */
class CompanyService
{
    use AuditLogging;

    /**
     * Create a new company
     *
     * @param  array  $companyData  Company data to create
     * @param  ServiceContext  $context  The service context
     * @return Company The created company
     */
    public function createCompany(array $companyData, ServiceContext $context): Company
    {
        // Validate required fields
        if (empty($companyData['name']) || empty($companyData['slug'])) {
            throw new \InvalidArgumentException('Name and slug are required');
        }

        // Check if company with slug already exists
        if (Company::where('slug', $companyData['slug'])->exists()) {
            throw new \InvalidArgumentException('Company with this slug already exists');
        }

        $company = new Company($companyData);

        if (! $company->save()) {
            throw new \RuntimeException('Failed to create company: validation failed');
        }

        $this->logAudit('company.created', [
            'company_id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
        ], $context);

        return $company->fresh();
    }

    /**
     * Update company information
     *
     * @param  Company  $company  The company to update
     * @param  array  $data  The update data
     * @param  ServiceContext  $context  The service context
     * @return Company The updated company
     */
    public function updateCompany(Company $company, array $data, ServiceContext $context): Company
    {
        $originalData = $company->getOriginal();

        $company->update($data);

        $this->logAudit('company.updated', [
            'company_id' => $company->id,
            'name' => $company->name,
        ], $context, [
            'updated_fields' => array_keys($data),
            'original_data' => $originalData,
        ]);

        return $company->fresh();
    }

    /**
     * Activate a company
     *
     * @param  Company  $company  The company to activate
     * @param  ServiceContext  $context  The service context
     * @return bool True if activation was successful
     */
    public function activateCompany(Company $company, ServiceContext $context): bool
    {
        $company->is_active = true;
        $result = $company->save();

        $this->logAudit('company.activated', [
            'company_id' => $company->id,
            'name' => $company->name,
        ], $context);

        return $result;
    }

    /**
     * Deactivate a company
     *
     * @param  Company  $company  The company to deactivate
     * @param  ServiceContext  $context  The service context
     * @return bool True if deactivation was successful
     */
    public function deactivateCompany(Company $company, ServiceContext $context): bool
    {
        // Check if deactivation is allowed
        // For example, ensure there are no active operations
        $this->validateCanDeactivate($company);

        $company->is_active = false;
        $result = $company->save();

        $this->logAudit('company.deactivated', [
            'company_id' => $company->id,
            'name' => $company->name,
        ], $context);

        return $result;
    }

    /**
     * Validate if a company can be deactivated
     *
     * @param  Company  $company  The company to validate
     *
     * @throws \Exception If company cannot be deactivated
     */
    private function validateCanDeactivate(Company $company): void
    {
        // Add any validation logic here
        // For example, check if there are any ongoing operations
        // that would be affected by deactivation
    }

    /**
     * Get company by slug
     *
     * @param  string  $slug  The company slug
     * @param  ServiceContext  $context  The service context
     * @return Company|null The company or null if not found
     */
    public function getCompanyBySlug(string $slug, ServiceContext $context): ?Company
    {
        $company = Company::where('slug', $slug)->first();

        if ($company) {
            $this->logAudit('company.viewed_by_slug', [
                'company_slug' => $slug,
                'company_id' => $company->id,
            ], $context);
        }

        return $company;
    }

    /**
     * Get all companies with pagination
     *
     * @param  int  $perPage  Number of results per page
     * @param  ServiceContext  $context  The service context
     * @param  bool  $activeOnly  Whether to return only active companies
     * @return LengthAwarePaginator The companies
     */
    public function getAllCompanies(int $perPage, ServiceContext $context, bool $activeOnly = true): LengthAwarePaginator
    {
        $query = Company::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $companies = $query->paginate($perPage);

        $this->logAudit('company.list_viewed', [
            'total' => $companies->total(),
            'active_only' => $activeOnly,
        ], $context);

        return $companies;
    }

    /**
     * Search companies
     *
     * @param  string  $query  Search query
     * @param  int  $perPage  Number of results per page
     * @param  ServiceContext  $context  The service context
     * @param  bool  $activeOnly  Whether to return only active companies
     * @return LengthAwarePaginator The search results
     */
    public function searchCompanies(string $query, int $perPage, ServiceContext $context, bool $activeOnly = true): LengthAwarePaginator
    {
        $queryBuilder = Company::where(function ($q) use ($query) {
            $q->where('name', 'ILIKE', "%{$query}%")
                ->orWhere('slug', 'ILIKE', "%{$query}%")
                ->orWhere('description', 'ILIKE', "%{$query}%");
        });

        if ($activeOnly) {
            $queryBuilder->where('is_active', true);
        }

        $results = $queryBuilder->paginate($perPage);

        $this->logAudit('company.search_performed', [
            'query' => $query,
            'results_count' => $results->total(),
            'active_only' => $activeOnly,
        ], $context);

        return $results;
    }

    /**
     * Get companies for a specific user
     *
     * @param  User  $user  The user to get companies for
     * @param  ServiceContext  $context  The service context
     * @param  bool  $activeOnly  Whether to return only active companies
     * @return \Illuminate\Database\Eloquent\Collection The user's companies
     */
    public function getUserCompanies(User $user, ServiceContext $context, bool $activeOnly = true)
    {
        $query = $user->companies();

        if ($activeOnly) {
            $query->where('companies.is_active', true);
        }

        $companies = $query->withPivot('role', 'is_active')
            ->orderBy('company_user.created_at')
            ->get();

        $this->logAudit('user.companies_viewed', [
            'user_id' => $user->id,
            'count' => $companies->count(),
            'active_only' => $activeOnly,
        ], $context);

        return $companies;
    }

    /**
     * Get company statistics
     *
     * @param  ServiceContext  $context  The service context
     * @return array Statistics about companies
     */
    public function getCompanyStatistics(ServiceContext $context): array
    {
        $user = $context->getUser();
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;

        $totalCompanies = Company::count();
        $activeCompanies = Company::where('is_active', true)->count();
        $inactiveCompanies = $totalCompanies - $activeCompanies;

        $stats = [
            'total_companies' => $totalCompanies,
            'active_companies' => $activeCompanies,
            'inactive_companies' => $inactiveCompanies,
            'recently_created' => Company::where('created_at', '>=', now()->subDays(30))->count(),
            'can_manage_all' => $isSuperAdmin,
        ];

        $this->logAudit('company.statistics_viewed', $stats, $context);

        return $stats;
    }

    /**
     * Add a user to a company
     *
     * @param  Company  $company  The company to add user to
     * @param  User  $user  The user to add
     * @param  string  $role  The role to assign
     * @param  ServiceContext  $context  The service context
     * @return bool True if user was added successfully
     */
    public function addUserToCompany(Company $company, User $user, string $role, ServiceContext $context): bool
    {
        // Validate role
        $validRoles = ['owner', 'admin', 'manager', 'accountant', 'employee', 'viewer'];
        if (! in_array($role, $validRoles)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        // Check if user is already in the company
        $existingPivot = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingPivot) {
            // Update role if user is already in company
            return $this->updateUserRoleInCompany($company, $user, $role, $context);
        }

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

        $this->logAudit('company.user_added', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $role,
        ], $context);

        return true;
    }

    /**
     * Update user role in company
     *
     * @param  Company  $company  The company context
     * @param  User  $user  The user whose role to update
     * @param  string  $role  The new role
     * @param  ServiceContext  $context  The service context
     * @return bool True if role was updated successfully
     */
    public function updateUserRoleInCompany(Company $company, User $user, string $role, ServiceContext $context): bool
    {
        // Validate role
        $validRoles = ['owner', 'admin', 'manager', 'accountant', 'employee', 'viewer'];
        if (! in_array($role, $validRoles)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }

        $result = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->update([
                'role' => $role,
                'updated_at' => now(),
            ]);

        $this->logAudit('company.user_role_updated', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'new_role' => $role,
        ], $context);

        return $result > 0;
    }

    /**
     * Remove a user from a company
     *
     * @param  Company  $company  The company context
     * @param  User  $user  The user to remove
     * @param  ServiceContext  $context  The service context
     * @return bool True if user was removed successfully
     */
    public function removeUserFromCompany(Company $company, User $user, ServiceContext $context): bool
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

        $this->logAudit('company.user_removed', [
            'company_id' => $company->id,
            'user_id' => $user->id,
        ], $context);

        return $result > 0;
    }

    /**
     * Get users in a company
     *
     * @param  Company  $company  The company to get users for
     * @param  ServiceContext  $context  The service context
     * @param  string|null  $role  Role to filter by
     * @param  bool  $activeOnly  Whether to return only active users
     * @return \Illuminate\Database\Eloquent\Collection The company users
     */
    public function getCompanyUsers(Company $company, ServiceContext $context, ?string $role = null, bool $activeOnly = true)
    {
        $query = $company->users();

        if ($role) {
            $query->wherePivot('role', $role);
        }

        if ($activeOnly) {
            $query->wherePivot('is_active', true)
                ->whereNull('company_user.left_at');
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
     * Transfer company ownership
     *
     * @param  Company  $company  The company to transfer
     * @param  User  $newOwner  The new owner user
     * @param  ServiceContext  $context  The service context
     * @return bool True if ownership was transferred
     */
    public function transferCompanyOwnership(Company $company, User $newOwner, ServiceContext $context): bool
    {
        // Check if current user has permission to transfer ownership
        $currentUser = $context->getUser();
        if (! $currentUser || ! $currentUser->isOwnerOfCompany($company)) {
            throw new \Exception('Only company owner can transfer ownership');
        }

        // Check if new owner is already in company
        $newOwnerInCompany = DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $newOwner->id)
            ->first();

        if (! $newOwnerInCompany) {
            throw new \Exception('New owner must be a member of the company');
        }

        // Change old owner's role to admin, and new owner's role to owner
        DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $currentUser->id)
            ->update([
                'role' => 'admin',
                'updated_at' => now(),
            ]);

        DB::table('company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $newOwner->id)
            ->update([
                'role' => 'owner',
                'updated_at' => now(),
            ]);

        $this->logAudit('company.ownership_transferred', [
            'company_id' => $company->id,
            'old_owner_id' => $currentUser->id,
            'new_owner_id' => $newOwner->id,
        ], $context);

        return true;
    }
}
