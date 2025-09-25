<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'system_role',
        'is_active',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_by_user_id' => 'string',
            'is_active' => 'boolean',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(self::class, 'created_by_user_id');
    }

    public function createdUsers()
    {
        return $this->hasMany(self::class, 'created_by_user_id');
    }

    public function companies()
    {
        return $this->belongsToMany(\App\Models\Company::class, 'auth.company_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    // Ensure companies are loaded with pivot data when accessed
    public function getCompaniesAttribute()
    {
        if (! $this->relationLoaded('companies')) {
            $this->load('companies');
        }

        return $this->getRelation('companies');
    }

    public function isSuperAdmin(): bool
    {
        return $this->system_role === 'superadmin';
    }

    public function getCurrentCompanyAttribute()
    {
        // Try to get from request session first, then fall back to global session
        $request = request();
        $companyId = null;

        if ($request && $request->hasSession()) {
            $companyId = $request->session()->get('current_company_id');
        }

        if (! $companyId) {
            $companyId = session('current_company_id');
        }

        // If no company is selected, get the first company for the user
        if (! $companyId) {
            $firstCompany = $this->companies()->first();
            if ($firstCompany) {
                $companyId = $firstCompany->id;
                // Set it in session for future requests
                if ($request && $request->hasSession()) {
                    $request->session()->put('current_company_id', $companyId);
                } else {
                    session(['current_company_id' => $companyId]);
                }
            }
        }

        if (! $companyId) {
            return null;
        }

        // Super admins can access any company
        if ($this->isSuperAdmin()) {
            return \App\Models\Company::find($companyId);
        }

        return $this->companies()->where('auth.companies.id', $companyId)->first();
    }

    public function currentCompany()
    {
        return $this->getCurrentCompanyAttribute();
    }

    /**
     * Get the current company ID attribute.
     */
    public function getCurrentCompanyIdAttribute(): ?string
    {
        $company = $this->getCurrentCompanyAttribute();

        return $company ? $company->id : null;
    }

    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    public function getSetting(string $key, string $group = 'general', $default = null)
    {
        return UserSetting::getSetting($this, $key, $group, $default);
    }

    public function setSetting(string $key, $value, string $group = 'general'): UserSetting
    {
        return UserSetting::setSetting($this, $key, $value, $group);
    }

    public function activate(): void
    {
        if ($this->is_active) {
            return;
        }

        $this->is_active = true;
        $this->save();
    }

    public function deactivate(): void
    {
        if (! $this->is_active) {
            return;
        }

        $this->is_active = false;
        $this->save();
    }
}
