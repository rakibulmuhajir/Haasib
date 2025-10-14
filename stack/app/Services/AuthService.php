<?php

namespace App\Services;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    /**
     * Attempt to authenticate a user with username/password credentials.
     */
    public function login(string $username, string $password, bool $remember = false): array
    {
        $user = User::where('username', $username)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'Invalid credentials',
            ];
        }

        if (! $user->is_active) {
            return [
                'success' => false,
                'user' => null,
                'message' => 'Account is deactivated',
            ];
        }

        // Authenticate the user for the session
        auth()->login($user, $remember);

        // TODO: Fix audit logging when audit_entries table schema is properly aligned
        // $this->logAudit('user_login', $user, [
        //     'username' => $user->username,
        //     'remember' => $remember,
        // ]);

        return [
            'success' => true,
            'user' => $this->userToArray($user),
            'message' => 'Login successful',
        ];
    }

    /**
     * Convert user to array format expected by tests.
     */
    private function userToArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->system_role,
            'companies' => $user->getActiveCompanies()->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'role' => $company->pivot->role,
                ];
            })->toArray(),
        ];
    }

    /**
     * Create an API token for the supplied user.
     */
    public function createApiToken(User $user, string $tokenName = 'api-token', array $abilities = ['*']): string
    {
        return $user->createToken($tokenName, $abilities)->plainTextToken;
    }

    /**
     * Revoke all existing API tokens for the user.
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Revoke a single API token.
     */
    public function revokeToken(string $token): bool
    {
        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken ? (bool) $accessToken->delete() : false;
    }

    /**
     * Determine if the given user may access the supplied company.
     */
    public function canAccessCompany(User $user, Company $company, string $permission = ''): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->companies()->where('auth.companies.id', $company->id)->exists()) {
            return false;
        }

        if ($permission === '') {
            return true;
        }

        $permissions = $this->getUserPermissions($user, $company);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * Switch the active company for the user.
     */
    public function switchCompany(User $user, Company $company): bool
    {
        if (! $this->canAccessCompany($user, $company)) {
            return false;
        }

        return app(ContextService::class)->setCurrentCompany($user, $company);
    }

    public function isOwner(User $user, Company $company): bool
    {
        return $user->isOwnerOfCompany($company);
    }

    public function isAdmin(User $user, Company $company): bool
    {
        return $user->isAdminOfCompany($company);
    }

    public function getUserRole(User $user, Company $company): ?string
    {
        return $user->companies()
            ->where('auth.companies.id', $company->id)
            ->first()?->pivot->role;
    }

    /**
     * Promote a company member to a new role.
     */
    public function promoteUser(User $target, Company $company, string $newRole, User $performedBy): bool
    {
        if (! $this->isOwner($performedBy, $company) && ! $performedBy->isSuperAdmin()) {
            return false;
        }

        if (! $this->canAccessCompany($target, $company)) {
            return false;
        }

        $company->users()->updateExistingPivot($target->id, [
            'role' => $newRole,
            'updated_at' => now(),
        ]);

        $this->logAudit('user_promoted', $performedBy, [
            'target_user_id' => $target->id,
            'company_id' => $company->id,
            'new_role' => $newRole,
        ], $company);

        return true;
    }

    /**
     * Demote a company member to a lower role.
     */
    public function demoteUser(User $target, Company $company, string $newRole, User $performedBy): bool
    {
        if (! $this->isOwner($performedBy, $company) && ! $performedBy->isSuperAdmin()) {
            return false;
        }

        if ($this->isOwner($target, $company) && ! $performedBy->isSuperAdmin()) {
            return false;
        }

        if (! $this->canAccessCompany($target, $company)) {
            return false;
        }

        $company->users()->updateExistingPivot($target->id, [
            'role' => $newRole,
            'updated_at' => now(),
        ]);

        $this->logAudit('user_demoted', $performedBy, [
            'target_user_id' => $target->id,
            'company_id' => $company->id,
            'new_role' => $newRole,
        ], $company);

        return true;
    }

    /**
     * Update a user's password and revoke existing sessions.
     */
    public function changePassword(User $user, string $newPassword): bool
    {
        $strength = $this->validatePasswordStrength($newPassword);

        if (! $strength['valid']) {
            throw new \InvalidArgumentException(implode(', ', $strength['errors']));
        }

        $user->password = Hash::make($newPassword);
        $user->save();

        $this->revokeAllTokens($user);

        $this->logAudit('password_changed', $user, ['user_id' => $user->id]);

        return true;
    }

    public function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (! preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain an uppercase letter.';
        }
        if (! preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain a lowercase letter.';
        }
        if (! preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain a number.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    public function hasTwoFactorEnabled(User $user): bool
    {
        return ! empty($user->getSetting('two_factor_secret'));
    }

    public function generateSessionFingerprint(User $user): string
    {
        $request = Request::instance();
        $userAgent = $request?->userAgent() ?? 'unknown';
        $ip = $request?->ip() ?? 'unknown';

        return hash('sha256', $user->id.$userAgent.$ip);
    }

    public function getActiveSessions(User $user): array
    {
        return [
            'total_sessions' => 0,
            'current_session' => $this->generateSessionFingerprint($user),
        ];
    }

    public function revokeOtherSessions(User $user): void
    {
        // Hook in with session storage as needed.
    }

    public function isVerified(User $user): bool
    {
        return ! empty($user->email_verified_at);
    }

    public function sendVerificationEmail(User $user): bool
    {
        if ($this->isVerified($user)) {
            return false;
        }

        // Integrate mailer when available.
        return true;
    }

    /**
     * Aggregate permissions granted to the user within a company.
     */
    public function getUserPermissions(User $user, Company $company): array
    {
        if ($user->isSuperAdmin()) {
            return ['*'];
        }

        $companyUser = $user->companies()
            ->where('auth.companies.id', $company->id)
            ->first();

        if (! $companyUser) {
            return [];
        }

        $role = $companyUser->pivot->role;

        $rolePermissions = match ($role) {
            'owner' => ['*'],
            'admin' => [
                'manage_users', 'manage_modules', 'manage_settings',
                'view_reports', 'create_transactions', 'edit_transactions',
                'delete_transactions', 'manage_customers', 'manage_vendors',
                'view_audit_log', 'export_data', 'view_transactions',
            ],
            'accountant' => [
                'view_reports', 'create_transactions', 'edit_transactions',
                'manage_customers', 'manage_vendors', 'reconcile_accounts',
                'export_data', 'view_transactions',
            ],
            'viewer' => ['view_reports', 'view_transactions'],
            'member' => ['view_own_transactions'],
            default => [],
        };

        if (in_array('*', $rolePermissions, true)) {
            return ['*'];
        }

        $modulePermissions = $company->modules()
            ->wherePivot('is_active', true)
            ->get()
            ->flatMap(fn (Module $module) => $module->permissions ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_values(array_unique(array_merge($rolePermissions, $modulePermissions)));
    }

    protected function logAudit(string $event, User $user, array $payload = [], ?Company $company = null): void
    {
        AuditEntry::create([
            'event' => $event,
            'model_type' => User::class,
            'model_id' => $user->id,
            'company_id' => $company?->id,
            'user_id' => $user->id,
            'new_values' => $payload,
            'metadata' => $payload,
        ]);
    }
}
