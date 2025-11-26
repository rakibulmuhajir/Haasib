<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Guard name for Spatie permissions.
     * Works for both web and api (Sanctum).
     */
    protected $guard_name = 'web';

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot(['status', 'is_default', 'meta'])
            ->withTimestamps();
    }

    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()->wherePivot('status', 'active');
    }

    /*
    |--------------------------------------------------------------------------
    | Company Membership Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Check if user belongs to a company with active status.
     */
    public function belongsToCompany(Company $company): bool
    {
        return $this->companies()
            ->where('companies.id', $company->id)
            ->wherePivot('status', 'active')
            ->exists();
    }

    /**
     * Get user's default company.
     */
    public function defaultCompany(): ?Company
    {
        return $this->activeCompanies()
            ->wherePivot('is_default', true)
            ->first()
            ?? $this->activeCompanies()->first();
    }

    /**
     * Check if user has any companies.
     */
    public function hasCompanies(): bool
    {
        return $this->activeCompanies()->exists();
    }

    /**
     * Get membership status for a company.
     */
    public function membershipStatus(Company $company): ?string
    {
        $pivot = $this->companies()
            ->where('companies.id', $company->id)
            ->first()
            ?->pivot;

        return $pivot?->status;
    }

    /*
    |--------------------------------------------------------------------------
    | Role Helpers (Company-Scoped)
    |--------------------------------------------------------------------------
    */

    /**
     * Check if user is owner of the given company.
     * Must set team context before calling.
     */
    public function isOwnerOf(Company $company): bool
    {
        return $this->hasRole('owner');
    }

    /**
     * Check if user is super admin (global, no company context).
     */
    public function isSuperAdmin(): bool
    {
        // Super admin role is checked without team context
        return $this->roles()
            ->whereNull('company_id')
            ->where('name', 'super_admin')
            ->exists();
    }
}
