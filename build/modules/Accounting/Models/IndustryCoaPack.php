<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndustryCoaPack extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.industry_coa_packs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(IndustryCoaTemplate::class, 'industry_pack_id');
    }

    public function systemAccounts(): HasMany
    {
        return $this->templates()->where('is_system', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
