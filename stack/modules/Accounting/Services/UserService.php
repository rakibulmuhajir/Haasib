<?php

namespace Modules\Accounting\Services;

use App\Models\AuditEntry;
use App\Models\Company;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(private readonly AuthService $authService) {}

    /**
     * Register a new user (optionally attaching to a company immediately).
     *
     * @throws ValidationException
     */
    public function register(array $userData, ?Company $company = null, ?string $role = null): User
    {
        $validator = Validator::make($userData, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'system_role' => ['sometimes', 'string', 'in:user,admin,superadmin'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $payload = $validator->validated();
        $payload['password'] = Hash::make($payload['password']);
        $payload['system_role'] = $payload['system_role'] ?? 'user';
        $payload['is_active'] = $payload['is_active'] ?? true;

        $user = User::create($payload);

        if ($company) {
            $this->attachToCompany($user, $company, $role ?? 'member', $user);
        }

        $this->logAudit('user_registered', $user, [
            'email' => $user->email,
            'system_role' => $user->system_role,
        ]);

        return $user;
    }

    public function createWithTemporaryPassword(array $userData, bool $mustChangePassword = true): User
    {
        $temporaryPassword = str()->random(12);
        $userData['password'] = $temporaryPassword;

        $user = $this->register($userData);

        if ($mustChangePassword) {
            $user->setSetting('must_change_password', true);
        }

        return $user;
    }

    public function getUser(string $userId): ?User
    {
        return User::with(['companies'])->find($userId);
    }

    public function listUsers(?Company $company = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = User::query()->orderBy('name');

        if ($company) {
            $query->whereHas('companies', function ($q) use ($company) {
                $q->where('auth.company_user.company_id', $company->id);
            });
        }

        return $query->paginate($perPage);
    }

    public function updateProfile(User $user, array $attributes): User
    {
        $user->fill($attributes);
        $user->save();

        $this->logAudit('user_profile_updated', $user, $attributes);

        return $user->fresh();
    }

    public function changePassword(User $user, string $newPassword, User $performedBy): bool
    {
        $validation = $this->authService->validatePasswordStrength($newPassword);

        if (! $validation['valid']) {
            throw new \InvalidArgumentException(implode(', ', $validation['errors']));
        }

        $user->password = Hash::make($newPassword);
        $user->setSetting('must_change_password', false);
        $user->save();

        $this->authService->revokeAllTokens($user);

        $this->logAudit('user_password_changed', $performedBy, [
            'target_user_id' => $user->id,
        ]);

        return true;
    }

    public function deactivate(User $user, User $performedBy): void
    {
        $user->deactivate();

        $this->logAudit('user_deactivated', $performedBy, [
            'target_user_id' => $user->id,
        ]);
    }

    public function reactivate(User $user, User $performedBy): void
    {
        $user->activate();

        $this->logAudit('user_reactivated', $performedBy, [
            'target_user_id' => $user->id,
        ]);
    }

    public function attachToCompany(User $user, Company $company, string $role, ?User $invitedBy = null): void
    {
        $company->users()->syncWithoutDetaching([
            $user->id => [
                'role' => $role,
                'invited_by_user_id' => $invitedBy?->id,
                'is_active' => true,
                'joined_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function detachFromCompany(User $user, Company $company, ?User $performedBy = null): void
    {
        $company->users()->updateExistingPivot($user->id, [
            'is_active' => false,
            'left_at' => now(),
            'updated_at' => now(),
        ]);

        $this->logAudit('user_removed_from_company', $performedBy ?? $user, [
            'company_id' => $company->id,
            'target_user_id' => $user->id,
        ], $company);
    }

    public function getUserPermissions(User $user, Company $company): array
    {
        return $this->authService->getUserPermissions($user, $company);
    }

    protected function logAudit(string $event, User $actor, array $payload = [], ?Company $company = null): void
    {
        AuditEntry::create([
            'event' => $event,
            'model_type' => User::class,
            'model_id' => $actor->id,
            'company_id' => $company?->id,
            'user_id' => $actor->id,
            'new_values' => $payload,
            'metadata' => $payload,
        ]);
    }
}
