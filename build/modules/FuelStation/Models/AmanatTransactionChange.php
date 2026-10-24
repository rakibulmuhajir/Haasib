<?php

namespace App\Modules\FuelStation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Append-only record of a holder move or amount correction on an amanat movement. */
class AmanatTransactionChange extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $connection = 'pgsql';
    protected $table = 'fuel.amanat_transaction_changes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['company_id', 'amanat_transaction_id', 'user_id', 'action', 'before', 'after'];

    protected $casts = [
        'company_id' => 'string',
        'amanat_transaction_id' => 'string',
        'user_id' => 'string',
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
