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
            ->where('company_user.role', 'owner')
            ->exists();
    }

    public function ownsCompany(string $companyId): bool
    {
        return $this->companies()
            ->where('company_user.company_id', $companyId)
            ->where('company_user.role', 'owner')
            ->exists();
    }

    /**
     * Determine if user is admin of given company.
     */
    public function isAdminOfCompany(Company $company): bool
    {
        return $this->companies()
            ->where('company_user.company_id', $company->id)
            ->whereIn('company_user.role', ['owner', 'admin'])
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
            $companyId = $request->session()->get('current_company_id');
        }

        if (! $companyId) {
            $companyId = session('current_company_id');
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
}
