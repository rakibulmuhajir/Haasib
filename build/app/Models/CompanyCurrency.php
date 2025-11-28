<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanyCurrency extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'auth.company_currencies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'currency_code',
        'is_base',
        'enabled_at',
    ];

    protected $casts = [
        'is_base' => 'boolean',
        'enabled_at' => 'datetime',
    ];
}
