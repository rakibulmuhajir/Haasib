<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The database connection that should be used by the model.
     */
    protected $connection = 'pgsql';

    /**
     * The table associated with the model.
     */
    protected $table = 'auth.users';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'system_role',
        'is_active',
        'created_by_user_id',
        'settings',
        'preferred_company_id',
    ];

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Generate username if it's not set, empty or null
            if (!isset($user->username) || empty($user->username) || is_null($user->username)) {
                $user->username = $user->generateUsername();
            }
        });
    }

    /**
     * Find user by email or username.
     */
    public static function findByEmailOrUsername(string $identifier): ?User
    {
        return static::where(function ($query) use ($identifier) {
            $query->where('email', $identifier)
                  ->orWhere('username', $identifier);
        })->first();
    }

    /**
     * Generate a unique username from name or email.
     */
    public function generateUsername(): string
    {
        $baseUsername = null;

        // Try to generate from name first
        if ($this->name) {
            $baseUsername = strtolower(str_replace(' ', '', $this->name));
            // Remove special characters and keep only alphanumeric
            $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername);
        }

        // Fallback to email if name doesn't work
        if (empty($baseUsername)) {
            $emailParts = explode('@', $this->email);
            $baseUsername = strtolower($emailParts[0]);
            $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername);
        }

        // Ensure we have something
        if (empty($baseUsername)) {
            $baseUsername = 'user';
        }

        // Ensure uniqueness
        $username = $baseUsername;
        $counter = 1;

        while (static::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'settings' => 'array',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'preferred_company_id' => 'string',
    ];

    /**
     * Companies the user belongs to.
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'auth.company_user')
            ->withPivot('role', 'invited_by_user_id', 'joined_at', 'left_at', 'is_active')
            ->withTimestamps();
    }

    /**
     * Pivot records for companies.
     */
    public function companyUsers()
    {
        return $this->hasMany(CompanyUser::class);
    }

    /**
     * Audit entries created by the user.
     */
    public function auditEntries()
    {
        return $this->hasMany(AuditEntry::class);
    }

    /**
     * User settings relationship.
     */
    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    /**
     * Determine if the user is a super administrator.
     */
    public function isSuperAdmin(): bool
    {
        return $this->system_role === 'superadmin';
    }

    /**
     * Determine if user owns the given company.
     */
    public function isOwnerOfCompany(Company $company): bool
    {
        return $this->companies()
            ->where('company_user.company_id', $company->id)
            ->where('company_user.role', 'company_owner')
            ->exists();
    }

    public function ownsCompany(string $companyId): bool
    {
        return $this->companies()
            ->where('company_user.company_id', $companyId)
            ->where('company_user.role', 'company_owner')
            ->exists();
    }

    /**
     * Determine if user is admin of given company.
     */
    public function isAdminOfCompany(Company $company): bool
    {
        return $this->companies()
            ->where('company_user.company_id', $company->id)
            ->whereIn('company_user.role', ['company_owner', 'company_admin'])
            ->exists();
    }

    public function getActiveCompanies()
    {
        return $this->companies()
            ->wherePivot('is_active', true)
            ->get();
    }

    public function getCompaniesWithRoles(): array
    {
        return $this->companies()
            ->withPivot('role', 'is_active')
            ->get()
            ->mapWithKeys(function (Company $company) {
                return [$company->id => [
                    'role' => $company->pivot->role,
                    'is_active' => (bool) $company->pivot->is_active,
                ]];
            })
            ->toArray();
    }

    /**
     * Get or resolve current company.
     */
    public function currentCompany()
    {
        // Try eager-loaded property first
        if ($this->relationLoaded('companies')) {
            $company = $this->companies->firstWhere('pivot.is_active', true);
            if ($company) {
                return $company;
            }
        }

        $companyId = null;
        $request = request();

        if ($request && $request->hasSession()) {
            $companyId = $request->session()->get('active_company_id')
                ?? $request->session()->get('current_company_id');
        }

        if (! $companyId) {
            $companyId = session('active_company_id') ?? session('current_company_id');
        }

        if ($companyId) {
            if ($this->isSuperAdmin()) {
                return Company::find($companyId);
            }

            return $this->companies()
                ->where('companies.id', $companyId)
                ->first();
        }

        return $this->companies()->first();
    }

    /**
     * Convenience accessor for current company id.
     */
    public function getCurrentCompanyIdAttribute(): ?string
    {
        return $this->currentCompany()?->id;
    }

    /**
     * Retrieve a user setting value.
     */
    public function getSetting(string $key, string $group = 'general', $default = null)
    {
        return UserSetting::getSetting($this, $key, $group, $default);
    }

    /**
     * Persist a user setting value.
     */
    public function setSetting(string $key, $value, string $group = 'general'): UserSetting
    {
        return UserSetting::setSetting($this, $key, $value, $group);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $systemRole)
    {
        return $query->where('system_role', $systemRole);
    }

    /**
     * Activate the user.
     */
    public function activate(): void
    {
        if (! $this->is_active) {
            $this->is_active = true;
            $this->save();
        }
    }

    /**
     * Deactivate the user.
     */
    public function deactivate(): void
    {
        if ($this->is_active) {
            $this->is_active = false;
            $this->save();
        }
    }

    /**
     * Check if user has system-level permissions.
     */
    public function isSystemUser(): bool
    {
        return $this->hasAnyRole(['super_admin', 'systemadmin', 'system_manager', 'system_auditor']);
    }

    /**
     * Get user's system role.
     */
    public function getSystemRole(): ?string
    {
        $systemRoles = ['super_admin', 'systemadmin', 'system_manager', 'system_auditor'];
        
        foreach ($systemRoles as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }
        
        return null;
    }

    /**
     * Assign user to company with specific role.
     */
    public function assignToCompany(Company $company, string $role): CompanyUser
    {
        // Check if user is already assigned to this company
        $existingAssignment = $this->companyUsers()
            ->where('company_id', $company->id)
            ->first();

        if ($existingAssignment) {
            // Remove old company role
            $this->removeCompanyRole($company, $existingAssignment->role);
            
            // Update existing assignment
            $existingAssignment->update([
                'role' => $role,
                'is_active' => true,
                'left_at' => null,
            ]);
        } else {
            // Create new assignment
            $existingAssignment = CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $this->id,
                'role' => $role,
                'is_active' => true,
                'joined_at' => now(),
            ]);
        }

        // Assign company-scoped Spatie role
        $this->assignCompanyRole($company, $role);

        return $existingAssignment;
    }

    /**
     * Remove user from company.
     */
    public function removeFromCompany(Company $company): bool
    {
        $companyUser = $this->companyUsers()
            ->where('company_id', $company->id)
            ->first();

        if (!$companyUser) {
            return false;
        }

        // Remove company role
        $this->removeCompanyRole($company, $companyUser->role);

        // Deactivate assignment
        $companyUser->update([
            'is_active' => false,
            'left_at' => now(),
        ]);

        return true;
    }

    /**
     * Assign company-scoped role to user.
     */
    private function assignCompanyRole(Company $company, string $role): void
    {
        $companyScopedRoleName = "{$role}_{$company->id}";
        $spatieRole = Role::where('name', $companyScopedRoleName)->first();
        
        if ($spatieRole && !$this->hasRole($companyScopedRoleName)) {
            $this->assignRole($spatieRole);
        }
    }

    /**
     * Remove company-scoped role from user.
     */
    private function removeCompanyRole(Company $company, string $role): void
    {
        $companyScopedRoleName = "{$role}_{$company->id}";
        
        if ($this->hasRole($companyScopedRoleName)) {
            $this->removeRole($companyScopedRoleName);
        }
    }

    /**
     * Switch to a different company context.
     */
    public function switchToCompany(?string $companyId): bool
    {
        if (!$companyId) {
            session()->forget('active_company_id');
            return true;
        }

        // Verify user has access to this company
        if (!$this->canAccessCompany($companyId)) {
            return false;
        }

        session(['active_company_id' => $companyId]);
        return true;
    }

    /**
     * Check if user can access a specific company.
     */
    public function canAccessCompany(string $companyId): bool
    {
        // System users can access any company
        if ($this->isSystemUser()) {
            return Company::where('id', $companyId)->exists();
        }

        // Regular users need active company assignment
        return $this->companies()
            ->where('company_id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Get user's role in a specific company.
     */
    public function getRoleInCompany(string $companyId): ?string
    {
        $companyUser = $this->companyUsers()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        return $companyUser?->role;
    }

    /**
     * Check if user has permission in specific company context.
     */
    public function hasCompanyPermission(string $permission, string $companyId): bool
    {
        // System users have broader permissions
        if ($this->isSystemUser() && $this->hasPermissionTo($permission)) {
            return true;
        }

        // Check company-scoped permission
        return $this->hasPermissionTo($permission, $companyId);
    }

    /**
     * Invitations sent by this user.
     */
    public function sentInvitations()
    {
        return $this->hasMany(Invitation::class, 'inviter_user_id');
    }

    /**
     * Pending invitations for this user's email.
     */
    public function pendingInvitations()
    {
        return Invitation::where('email', $this->email)
                        ->where('status', 'pending')
                        ->where('expires_at', '>', now());
    }

    /**
     * Get all invitations for this user's email.
     */
    public function invitations()
    {
        return Invitation::where('email', $this->email);
    }

    /**
     * Check if user has pending invitations.
     */
    public function hasPendingInvitations(): bool
    {
        return $this->pendingInvitations()->exists();
    }

    /**
     * Get count of pending invitations.
     */
    public function getPendingInvitationsCount(): int
    {
        return $this->pendingInvitations()->count();
    }

    /**
     * Get the user's preferred company.
     */
    public function preferredCompany()
    {
        return $this->belongsTo(Company::class, 'preferred_company_id');
    }

    /**
     * Set the user's preferred company.
     */
    public function setPreferredCompany(?string $companyId): bool
    {
        // Verify user has access to this company if provided
        if ($companyId && !$this->canAccessCompany($companyId)) {
            return false;
        }

        $this->preferred_company_id = $companyId;
        return $this->save();
    }

    /**
     * Get the user's preferred company ID.
     */
    public function getPreferredCompanyId(): ?string
    {
        return $this->preferred_company_id;
    }
}
