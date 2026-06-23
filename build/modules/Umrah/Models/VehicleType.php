<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleType extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'umrah.vehicle_types';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'seats',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'seats' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VisaGroup::class);
    }
}
