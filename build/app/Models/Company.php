<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'auth.companies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id', 'created_at', 'updated_at', 'is_active'];

    protected $casts = [
        'settings' => 'array',
        'created_by_user_id' => 'string',
        'is_active' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'industry',
        'slug',
        'country',
        'country_id',
        'base_currency',
        'language',
        'locale',
        'settings',
        'logo_url',
        'created_by_user_id',
    ];
}
