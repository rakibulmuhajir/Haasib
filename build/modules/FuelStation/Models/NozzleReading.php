<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * NozzleReading - Daily meter readings per nozzle
 *
 * Tracks both electronic (computerized) and manual readings for verification.
 * Electronic reading is the official source; manual is for double-checking.
 */
class NozzleReading extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.nozzle_readings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'nozzle_id',
        'item_id',
        'reading_date',
        'opening_electronic',
        'closing_electronic',
        'opening_manual',
        'closing_manual',
        'liters_dispensed',
        'electronic_manual_variance',
        'recorded_by_user_id',
        'daily_close_transaction_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'nozzle_id' => 'string',
        'item_id' => 'string',
        'reading_date' => 'date',
        'opening_electronic' => 'decimal:2',
        'closing_electronic' => 'decimal:2',
        'opening_manual' => 'decimal:2',
        'closing_manual' => 'decimal:2',
        'liters_dispensed' => 'decimal:2',
        'electronic_manual_variance' => 'decimal:2',
        'recorded_by_user_id' => 'string',
        'daily_close_transaction_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function nozzle(): BelongsTo
    {
        return $this->belongsTo(Nozzle::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function dailyCloseTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'daily_close_transaction_id');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calculate liters from electronic reading
     */
    public function getElectronicLitersAttribute(): float
    {
        return max(0, (float) $this->closing_electronic - (float) $this->opening_electronic);
    }

    /**
     * Calculate liters from manual reading
     */
    public function getManualLitersAttribute(): ?float
    {
        if ($this->opening_manual === null || $this->closing_manual === null) {
            return null;
        }
        return max(0, (float) $this->closing_manual - (float) $this->opening_manual);
    }

    /**
     * Check if manual reading matches electronic
     */
    public function getReadingsMatchAttribute(): bool
    {
        if ($this->manual_liters === null) {
            return true; // No manual reading to compare
        }
        // Allow 0.5 liter tolerance
        return abs($this->electronic_liters - $this->manual_liters) <= 0.5;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Boot
    // ─────────────────────────────────────────────────────────────────────────

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($reading) {
            // Auto-calculate liters dispensed from electronic reading
            $reading->liters_dispensed = max(0,
                (float) $reading->closing_electronic - (float) $reading->opening_electronic
            );

            // Calculate variance if manual readings exist
            if ($reading->opening_manual !== null && $reading->closing_manual !== null) {
                $manualLiters = (float) $reading->closing_manual - (float) $reading->opening_manual;
                $reading->electronic_manual_variance = round($reading->liters_dispensed - $manualLiters, 2);
            }
        });
    }
}
