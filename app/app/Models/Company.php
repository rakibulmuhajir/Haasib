<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'auth.companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'base_currency',
        'language',
        'locale',
        'settings',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'created_by_user_id' => 'string',
    ];

    /**
     *  Setup model event hooks
     */
    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (!$company->slug) {
                $base = Str::slug((string) $company->name) ?: Str::slug(Str::uuid());
                $slug = $base;
                $i = 1;
                while (self::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $company->slug = $slug;
            }
        });
    }

    /**
     * The users that belong to the company.
     */
    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'auth.company_user')
            ->withPivot('role', 'invited_by_user_id')
            ->withTimestamps();
    }

    /**
     * Get the user who created this company.
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    /**
     * Get the companies created by this user.
     */
    public function createdCompanies()
    {
        return $this->hasMany(\App\Models\Company::class, 'created_by_user_id');
    }

    /**
     * Get the owner of the company (user with 'owner' role).
     */
    public function owner()
    {
        return $this->users()->where('auth.company_user.role', 'owner')->first();
    }
}
