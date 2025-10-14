<?php

namespace Modules\Accounting\Domain\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\User;

class RegisterUser
{
    /**
     * Register a new user.
     *
     * @throws ValidationException
     */
    public function execute(array $data): User
    {
        // Validate input
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:auth.users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'system_role' => ['sometimes', 'string', 'in:user,admin,superadmin'],
            'is_active' => ['sometimes', 'boolean'],
            'created_by_user_id' => ['sometimes', 'uuid', 'exists:auth.users,id'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Set defaults
        $validated['password'] = Hash::make($validated['password']);
        $validated['system_role'] = $validated['system_role'] ?? 'user';
        $validated['is_active'] = $validated['is_active'] ?? true;

        // Create user
        $user = User::create($validated);

        // Log audit entry
        $user->auditEntries()->create([
            'action' => 'user_registered',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'user_id' => $validated['created_by_user_id'] ?? $user->id,
            'new_values' => [
                'name' => $user->name,
                'email' => $user->email,
                'system_role' => $user->system_role,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'is_system_action' => isset($validated['created_by_user_id']),
        ]);

        // Assign default role if Spatie permissions are being used
        if (class_exists('\Spatie\Permission\Models\Role')) {
            $role = \Spatie\Permission\Models\Role::firstOrCreate(
                ['name' => 'user'],
                ['guard_name' => 'web']
            );
            $user->assignRole($role);
        }

        return $user;
    }

    /**
     * Register a user with automatic company creation.
     *
     * @return array [User, Company]
     *
     * @throws ValidationException
     */
    public function executeWithCompany(array $userData, array $companyData): array
    {
        // Begin transaction
        DB::beginTransaction();

        try {
            // Register user
            $user = $this->execute($userData);

            // Create company
            $createCompanyAction = new CreateCompany;
            $company = $createCompanyAction->execute([
                'name' => $companyData['name'],
                'country' => $companyData['country'] ?? null,
                'base_currency' => $companyData['base_currency'] ?? 'USD',
                'created_by_user_id' => $user->id,
            ], $user);

            // Add user as owner of the company
            $user->companies()->attach($company->id, [
                'role' => 'owner',
                'invited_by_user_id' => $user->id,
            ]);

            // Switch to the new company
            $user->switchCompany($company);

            DB::commit();

            return [$user, $company];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate registration data without creating a user.
     *
     * @throws ValidationException
     */
    public function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:auth.users,email'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
            'system_role' => ['sometimes', 'string', 'in:user,admin,superadmin'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Check if an email is available for registration.
     */
    public function isEmailAvailable(string $email): bool
    {
        return ! User::where('email', $email)->exists();
    }

    /**
     * Generate a secure temporary password.
     */
    public function generateTemporaryPassword(int $length = 12): string
    {
        return \Illuminate\Support\Str::random($length);
    }

    /**
     * Create a user with temporary password.
     *
     * @throws ValidationException
     */
    public function createWithTemporaryPassword(array $data, bool $mustChangePassword = true): User
    {
        $tempPassword = $this->generateTemporaryPassword();
        $data['password'] = $tempPassword;
        $data['password_confirmation'] = $tempPassword;

        $user = $this->execute($data);

        if ($mustChangePassword) {
            $user->setSetting('must_change_password', true);
        }

        return $user;
    }
}
