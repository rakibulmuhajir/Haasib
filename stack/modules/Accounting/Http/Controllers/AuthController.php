<?php

namespace Modules\Accounting\Http\Controllers;

use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\User;
use Modules\Accounting\Services\UserService;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private ContextService $contextService,
        private UserService $userService
    ) {}

    /**
     * Login user.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
            'device_name' => ['string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        $result = $this->authService->login(
            $request->email,
            $request->password,
            $request->boolean('remember', false)
        );

        if (! $result['success']) {
            return $this->error($result['message'], [], 401);
        }

        $user = $result['user'];

        // Create API token if requested
        $token = null;
        if ($request->has('device_name')) {
            $token = $this->authService->createApiToken(
                $user,
                $request->device_name.' - '.now()->format('Y-m-d H:i:s')
            );
        }

        // Restore last company context
        $this->contextService->restoreLastCompany($user);

        return $this->success([
            'user' => $user->only(['id', 'name', 'email', 'system_role', 'created_at']),
            'token' => $token,
            'abilities' => $token ? $user->tokens()->where('id', $token->id)->first()->abilities : [],
            'context' => $this->contextService->getContextMetadata($user),
        ], 'Login successful');
    }

    /**
     * Logout user.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        // Revoke current token
        $user->currentAccessToken()?->delete();

        // Clear context
        $this->contextService->clearCurrentCompany();

        return $this->success([], 'Logout successful');
    }

    /**
     * Logout from all devices.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $this->authService->revokeAllTokens($user);
        $this->contextService->clearCurrentCompany();

        return $this->success([], 'Logged out from all devices');
    }

    /**
     * Register new user.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:pgsql.auth.users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'company_country' => ['sometimes', 'string', 'size:2'],
            'company_currency' => ['sometimes', 'string', 'size:3'],
            'accept_terms' => ['required', 'accepted'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        try {
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
            ];

            $companyData = null;
            if ($request->has('company_name')) {
                $companyData = [
                    'name' => $request->company_name,
                    'country' => $request->company_country ?? 'US',
                    'currency' => $request->company_currency ?? 'USD',
                ];
            }

            [$user, $company] = $this->userService->registerUser($userData, $companyData);

            // Auto-login after registration
            $result = $this->authService->login($request->email, $request->password);

            if ($result['success']) {
                // Set context to created company or user's first company
                if ($company) {
                    $this->contextService->setCurrentCompany($result['user'], $company);
                } else {
                    $this->contextService->restoreLastCompany($result['user']);
                }
            }

            return $this->success([
                'user' => $result['user']->only(['id', 'name', 'email', 'system_role', 'created_at']),
                'company' => $company ? $company->only(['id', 'name', 'slug', 'country']) : null,
                'context' => $this->contextService->getContextMetadata($result['user']),
            ], 'Registration successful');

        } catch (\Exception $e) {
            return $this->error('Registration failed: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        // Load relationships
        $user->load(['companies' => function ($query) {
            $query->wherePivot('is_active', true);
        }]);

        return $this->success([
            'user' => $user->only(['id', 'name', 'email', 'system_role', 'created_at', 'last_login_at']),
            'companies' => $user->companies->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'role' => $company->pivot->role,
                    'is_current' => $company->id === $this->contextService->getCurrentCompany($user)?->id,
                ];
            }),
            'context' => $this->contextService->getContextMetadata($user),
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:pgsql.auth.users,email,'.$user->id],
            'current_password' => ['required_with:password,password_confirmation', 'string'],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        $data = $request->only(['name', 'email']);

        // Verify current password if changing password
        if ($request->has('password')) {
            if (! Hash::check($request->current_password, $user->password)) {
                return $this->error('Current password is incorrect', [], 422);
            }
            $data['password'] = $request->password;
            $data['password_confirmation'] = $request->password_confirmation;
        }

        // Check if email is available
        if (isset($data['email']) && ! $this->userService->isEmailAvailable($data['email'], $user->id)) {
            return $this->error('Email is already taken', [], 422);
        }

        try {
            $user = $this->userService->updateProfile($user, $data);

            return $this->success([
                'user' => $user->only(['id', 'name', 'email', 'system_role', 'updated_at']),
            ], 'Profile updated successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to update profile: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Change password.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', $validator->errors(), 422);
        }

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->error('Current password is incorrect', [], 422);
        }

        // Validate password strength
        $strengthCheck = $this->authService->validatePasswordStrength($request->password);
        if (! $strengthCheck['valid']) {
            return $this->error('Password does not meet requirements', $strengthCheck['errors'], 422);
        }

        try {
            $this->authService->changePassword($user, $request->password);

            return $this->success([], 'Password changed successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to change password: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Switch current company.
     */
    public function switchCompany(Request $request, string $companyId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $company = Company::find($companyId);

        if (! $company) {
            return $this->error('Company not found', [], 404);
        }

        if (! $this->authService->canAccessCompany($user, $company)) {
            return $this->error('Access denied to this company', [], 403);
        }

        try {
            $this->contextService->switchCompany($user, $company);

            return $this->success([
                'company' => $company->only(['id', 'name', 'slug', 'country']),
                'context' => $this->contextService->getContextMetadata($user),
                'permissions' => $this->contextService->getUserPermissions($user, $company),
            ], 'Company switched successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to switch company: '.$e->getMessage(), [], 500);
        }
    }

    /**
     * Get API tokens.
     */
    public function tokens(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $tokens = $user->tokens->map(function ($token) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'last_used_at' => $token->last_used_at,
            ];
        });

        return $this->success([
            'tokens' => $tokens,
        ]);
    }

    /**
     * Revoke API token.
     */
    public function revokeToken(Request $request, string $tokenId): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $token = $user->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            return $this->error('Token not found', [], 404);
        }

        $token->delete();

        return $this->success([], 'Token revoked successfully');
    }

    /**
     * Get active sessions.
     */
    public function sessions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $sessions = $this->authService->getActiveSessions($user);

        return $this->success([
            'sessions' => $sessions,
        ]);
    }

    /**
     * Revoke all other sessions.
     */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('User not authenticated', [], 401);
        }

        $this->authService->revokeOtherSessions($user);

        return $this->success([], 'Other sessions revoked successfully');
    }
}
