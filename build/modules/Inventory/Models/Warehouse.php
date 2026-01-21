<?php

namespace App\Modules\Inventory\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'inv.warehouses';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'warehouse_type',
        'capacity',
        'low_level_alert',
        'linked_item_id',
        'dip_stick_id',
        'address',
        'city',
        'state',
        'postal_code',
        'country_code',
        'is_primary',
        'is_active',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'warehouse_type' => 'string',
        'capacity' => 'decimal:2',
        'low_level_alert' => 'decimal:2',
        'linked_item_id' => 'string',
        'dip_stick_id' => 'string',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * For tank warehouses, the linked fuel item.
     */
    public function linkedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'linked_item_id');
    }

    /**
     * For tank warehouses, the dip stick used for measurements.
     */
    public function dipStick(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\FuelStation\Models\DipStick::class);
    }

    /**
     * Check if this warehouse is a tank.
     */
    public function isTank(): bool
    {
        return $this->warehouse_type === 'tank';
    }

    /**
     * Convert a dip stick reading to liters using the tank's dip chart.
     */
    public function convertDipReading(float $stickReading): ?float
    {
        if (!$this->isTank() || !$this->dip_stick_id) {
            return null;
        }

        return $this->dipStick?->convertToLiters($stickReading);
    }
}
