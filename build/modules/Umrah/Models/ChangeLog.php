<?php

namespace App\Modules\Umrah\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeLog extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';

    protected $table = 'umrah.change_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'reason',
        'old_values',
        'new_values',
        'metadata',
    ];

    protected $casts = [
        'company_id' => 'string',
        'user_id' => 'string',
        'entity_id' => 'string',
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
