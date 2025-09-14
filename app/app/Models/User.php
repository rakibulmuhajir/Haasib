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
        $companyId = session('current_company_id');
        if (! $companyId) {
            return null;
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
        return session('current_company_id');
    }
}
