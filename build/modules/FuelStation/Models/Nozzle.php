<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Nozzle - Individual dispensing point on a pump
 *
 * Each pump can have multiple nozzles (typically 2 - one per side).
 * Each nozzle dispenses a specific fuel type from a linked tank.
 */
class Nozzle extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'fuel.nozzles';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'pump_id',
        'tank_id',
        'item_id',
        'code',
        'label',
        'current_meter_reading',
        'last_closing_reading',
        'last_manual_reading',
        'has_electronic_meter',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'company_id' => 'string',
        'pump_id' => 'string',
        'tank_id' => 'string',
        'item_id' => 'string',
        'current_meter_reading' => 'decimal:2',
        'last_closing_reading' => 'decimal:2',
        'last_manual_reading' => 'decimal:2',
        'has_electronic_meter' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function pump(): BelongsTo
    {
        return $this->belongsTo(Pump::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'tank_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(NozzleReading::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get display name (code + fuel type)
     */
    public function getDisplayNameAttribute(): string
    {
        $fuelName = $this->item?->name ?? 'Unknown';
        return "{$this->code} - {$fuelName}";
    }

    /**
     * Get the fuel category from the linked item
     */
    public function getFuelCategoryAttribute(): ?string
    {
        return $this->item?->fuel_category;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPump($query, string $pumpId)
    {
        return $query->where('pump_id', $pumpId);
    }

    public function scopeForFuelType($query, string $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }
}
