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
        'exchange_rate',
        'enabled_at',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:8',
        'enabled_at' => 'datetime',
    ];
}
