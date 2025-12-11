<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndustryCoaTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.industry_coa_templates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'industry_pack_id',
        'code',
        'name',
        'type',
        'subtype',
        'normal_balance',
        'is_contra',
        'is_system',
        'system_identifier',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'industry_pack_id' => 'string',
        'is_contra' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function industryPack(): BelongsTo
    {
        return $this->belongsTo(IndustryCoaPack::class, 'industry_pack_id');
    }

    public function scopeSystemAccounts($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeBySystemIdentifier($query, string $identifier)
    {
        return $query->where('system_identifier', $identifier);
    }
}
