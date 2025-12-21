<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Inventory\Models\Item;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pump Reading - Meter counter readings per pump per shift.
 *
 * Item is derived from pump→tank→linked_item_id but stored for:
 * - Query performance in reconciliation
 * - Audit trail if pump is reconfigured later
 * - Reconciliation health card groups by item, not just pump
 */
class PumpReading extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.pump_readings';
    protected $keyType = 'string';
    public $incrementing = false;

    public const SHIFT_DAY = 'day';
    public const SHIFT_NIGHT = 'night';

    protected $fillable = [
        'company_id',
        'pump_id',
        'item_id',
        'reading_date',
        'shift',
        'opening_meter',
        'closing_meter',
        'liters_dispensed',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'pump_id' => 'string',
        'item_id' => 'string',
        'reading_date' => 'date',
        'opening_meter' => 'decimal:2',
        'closing_meter' => 'decimal:2',
        'liters_dispensed' => 'decimal:2',
        'recorded_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (PumpReading $reading) {
            // Calculate liters dispensed
            $reading->liters_dispensed = $reading->closing_meter - $reading->opening_meter;

            // Derive item_id from pump→tank→linked_item_id if not set
            if (!$reading->item_id && $reading->pump_id) {
                $pump = Pump::with('tank')->find($reading->pump_id);
                if ($pump && $pump->tank) {
                    $reading->item_id = $pump->tank->linked_item_id;
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function pump(): BelongsTo
    {
        return $this->belongsTo(Pump::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public static function getShifts(): array
    {
        return [
            self::SHIFT_DAY,
            self::SHIFT_NIGHT,
        ];
    }
}
