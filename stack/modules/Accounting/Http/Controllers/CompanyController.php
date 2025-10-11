<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\User;
use App\Services\AuthService;
use Modules\Accounting\Services\CompanyService;
use App\Services\ContextService;
use Modules\Accounting\Services\UserService;

class CompanyController extends Controller
{
    public function __construct(
        private CompanyService $companyService,
        private AuthService $authService,
        private ContextService $contextService,
        private UserService $userService
    ) {}

    /**
     * Get all companies for user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $companies = $this->companyService->getUserCompanies($user, true);

        return $this->success([
            'companies' => $companies->map(function ($company) use ($user) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'country' => $company->country,
                    'currency' => $company->base_currency,
                    'role' => $company->pivot->role,
                    'joined_at' => $company->pivot->joined_at,
                    'is_active' => $company->pivot->is_active,
                    'is_current' => $company->id === $this->contextService->getCurrentCompany($user)?->id,
                    'enabled_modules' => $company->modules()->where('auth.company_modules.is_active', true)->count(),
                ];
            }),
        ]);
    }

    /**
     * Get company details.
     */
    public function show(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = $this->companyService->getCompanyWithRelations($companyId, $user);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        return $this->success([
            'company' => array_merge(
                $company->only(['id', 'name', 'slug', 'country', 'base_currency', 'created_at', 'updated_at']),
                [
                    'statistics' => $this->companyService->getCompanyStatistics($company),
                    'current_user_role' => $this->authService->getUserRole($user, $company),
                    'settings' => $company->settings ?? [],
                    'is_owner' => $this->authService->isOwner($user, $company),
                    'is_admin' => $this->authService->isAdmin($user, $company),
                ]
            ),
        ]);
    }

    /**
     * Create new company.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:auth.companies,slug'],
            'country' => ['required', 'string', 'size:2'],
            'base_currency' => ['required', 'string', 'size:3'],
            'settings' => ['sometimes', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $companyData = $validator->validated();

            // Generate slug if not provided
            if (empty($companyData['slug'])) {
                $companyData['slug'] = $this->generateSlug($companyData['name']);

                // Check if slug is available
                if (! $this->companyService->isSlugAvailable($companyData['slug'])) {
                    $companyData['slug'] .= '-'.time();
                }
            }

            $company = $this->companyService->createCompany(
                $companyData['name'],
                $companyData['country'],
                $companyData['base_currency'],
                $user,
                $companyData['slug'] ?? null,
                $companyData['settings'] ?? []
            );

            // Switch to new company
            $this->contextService->setCurrentCompany($user, $company);

            return $this->success([
                'company' => $company->only(['id', 'name', 'slug', 'country', 'base_currency', 'created_at']),
                'context' => $this->contextService->getContextMetadata($user),
            ], 'Company created successfully', 201);

        } catch (\Exception $e) {
            return $this->error('Failed to create company: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Update company.
     */
    public function update(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'manage_settings')) {
            return $this->error('Access denied', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('auth.companies', 'slug')->ignore($company->id),
            ],
            'country' => ['sometimes', 'string', 'size:2'],
            'base_currency' => ['sometimes', 'string', 'size:3'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $company = $this->companyService->updateCompany($company, $validator->validated());

            return $this->success([
                'company' => $company->only(['id', 'name', 'slug', 'country', 'base_currency', 'updated_at']),
            ], 'Company updated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to update company: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Deactivate company.
     */
    public function deactivate(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->isOwner($user, $company)) {
            return $this->error('Only owners can deactivate companies', [], 403);
        }

        try {
            $this->companyService->deactivateCompany($company, $user);

            // Clear context if this was current company
            if ($this->contextService->getCurrentCompany($user)?->id === $company->id) {
                $this->contextService->clearCurrentCompany();
            }

            return $this->success([], 'Company deactivated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to deactivate company: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Reactivate company.
     */
    public function reactivate(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->isOwner($user, $company)) {
            return $this->error('Only owners can reactivate companies', [], 403);
        }

        try {
            $this->companyService->reactivateCompany($company, $user);

            return $this->success([], 'Company reactivated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to reactivate company: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get company users.
     */
    public function users(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'manage_users')) {
            return $this->error('Access denied', [], 403);
        }

        $role = $request->get('role');
        $users = $this->userService->getCompanyUsers($company, $role, true);

        return $this->success([
            'users' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'is_active' => $user->pivot->is_active,
                    'joined_at' => $user->pivot->joined_at,
                    'invited_by' => $user->pivot->invitedBy?->name,
                    'left_at' => $user->pivot->left_at,
                ];
            }),
        ]);
    }

    /**
     * Invite user to company.
     */
    public function inviteUser(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'manage_users')) {
            return $this->error('Access denied', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'role' => ['required', 'in:owner,admin,accountant,viewer,member'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        try {
            [$invitedUser, $status] = $this->userService->inviteToCompany(
                $request->email,
                $company,
                $request->role,
                $user
            );

            return $this->success([
                'user' => $invitedUser ? [
                    'id' => $invitedUser->id,
                    'name' => $invitedUser->name,
                    'email' => $invitedUser->email,
                ] : null,
                'status' => $status,
            ], 'User invited successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to invite user: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Remove user from company.
     */
    public function removeUser(Request $request, string $companyId, string $userId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'manage_users')) {
            return $this->error('Access denied', [], 403);
        }

        $userToRemove = User::find($userId);

        if (! $userToRemove) {
            return $this->error('User not found', [], 404);
        }

        try {
            $this->userService->removeFromCompany($userToRemove, $company, $user);

            return $this->success([], 'User removed from company successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to remove user: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Change user role in company.
     */
    public function changeUserRole(Request $request, string $companyId, string $userId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'manage_users')) {
            return $this->error('Access denied', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => ['required', 'in:owner,admin,accountant,viewer,member'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        $targetUser = User::find($userId);

        if (! $targetUser) {
            return $this->error('User not found', [], 404);
        }

        try {
            $this->userService->changeCompanyRole($targetUser, $company, $request->role, $user);

            return $this->success([], 'User role changed successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to change user role: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Transfer ownership.
     */
    public function transferOwnership(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->isOwner($user, $company)) {
            return $this->error('Only owners can transfer ownership', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'new_owner_id' => ['required', 'exists:auth.users,id'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        $newOwner = User::find($request->new_owner_id);

        if (! $this->authService->canAccessCompany($newOwner, $company)) {
            return $this->error('New owner must be a member of the company', [], 422);
        }

        try {
            $this->companyService->transferOwnership($company, $newOwner, $user);

            return $this->success([], 'Ownership transferred successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to transfer ownership: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get company settings.
     */
    public function getSettings(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'manage_settings')) {
            return $this->error('Access denied', [], 403);
        }

        return $this->success([
            'settings' => $company->settings ?? [],
        ]);
    }

    /**
     * Update company settings.
     */
    public function updateSettings(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'manage_settings')) {
            return $this->error('Access denied', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'settings' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $this->companyService->updateSettings($company, $request->settings, $user);

            return $this->success([], 'Settings updated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to update settings: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get company statistics.
     */
    public function statistics(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company, 'view_reports')) {
            return $this->error('Access denied', [], 403);
        }

        $stats = $this->companyService->getCompanyStatistics($company);

        return $this->success([
            'statistics' => $stats,
        ]);
    }

    /**
     * Search companies.
     */
    public function search(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'q' => ['required', 'string', 'min:2'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        $perPage = $request->get('per_page', 20);
        $companies = $this->companyService->searchCompanies($request->q, $user, $perPage);

        return $this->success([
            'companies' => $companies->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'country' => $company->country,
                    'currency' => $company->base_currency,
                ];
            }),
            'pagination' => [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
            ],
        ]);
    }

    /**
     * List all companies for the current user.
     */
    public function list(Request $request): JsonResponse
    {
        // Company list command - not implemented
        return response()->json([
            'message' => 'Company list command - not implemented',
        ], 501);
    }

    /**
     * Switch to a different company.
     */
    public function switch(Request $request, string $companyId): JsonResponse
    {
        // Company switch command - not implemented
        return response()->json([
            'message' => 'Company switch command - not implemented',
        ], 501);
    }

    /**
     * Generate unique slug from name.
     */
    private function generateSlug(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    }
}
