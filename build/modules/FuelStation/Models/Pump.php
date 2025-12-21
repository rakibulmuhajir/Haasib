<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pump extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'fuel.pumps';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'tank_id',
        'current_meter_reading',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'tank_id' => 'string',
        'current_meter_reading' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'tank_id');
    }

    public function pumpReadings(): HasMany
    {
        return $this->hasMany(PumpReading::class);
    }

    public function attendantHandovers(): HasMany
    {
        return $this->hasMany(AttendantHandover::class);
    }
}
