<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tank Reading - Manual dip measurements for variance calculation.
 *
 * Variance posting workflow:
 * 1. Staff records dip reading → status = 'draft'
 * 2. Manager reviews/confirms → status = 'confirmed'
 * 3. System creates shrinkage/gain JE → status = 'posted', journal_entry_id set
 * 4. Draft readings can be edited; confirmed/posted cannot
 */
class TankReading extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.tank_readings';
    protected $keyType = 'string';
    public $incrementing = false;

    public const TYPE_OPENING = 'opening';
    public const TYPE_CLOSING = 'closing';
    public const TYPE_SPOT_CHECK = 'spot_check';

    public const VARIANCE_LOSS = 'loss';
    public const VARIANCE_GAIN = 'gain';
    public const VARIANCE_NONE = 'none';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_POSTED = 'posted';

    // Variance reason codes for audit/analytics
    public const REASON_EVAPORATION = 'evaporation';
    public const REASON_LEAK_SUSPECTED = 'leak_suspected';
    public const REASON_METER_FAULT = 'meter_fault';
    public const REASON_DIP_ERROR = 'dip_error';
    public const REASON_TEMPERATURE = 'temperature';
    public const REASON_THEFT_SUSPECTED = 'theft_suspected';
    public const REASON_UNKNOWN = 'unknown';

    protected $fillable = [
        'company_id',
        'tank_id',
        'item_id',
        'stick_reading',
        'reading_date',
        'reading_type',
        'dip_measurement_liters',
        'system_calculated_liters',
        'variance_liters',
        'variance_type',
        'variance_reason',
        'status',
        'journal_entry_id',
        'recorded_by_user_id',
        'confirmed_by_user_id',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'string',
        'tank_id' => 'string',
        'item_id' => 'string',
        'reading_date' => 'datetime',
        'stick_reading' => 'decimal:2',
        'dip_measurement_liters' => 'decimal:2',
        'system_calculated_liters' => 'decimal:2',
        'variance_liters' => 'decimal:2',
        'journal_entry_id' => 'string',
        'recorded_by_user_id' => 'string',
        'confirmed_by_user_id' => 'string',
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'tank_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /**
     * Calculate and set variance fields based on dip vs system readings.
     */
    public function calculateVariance(): void
    {
        $this->variance_liters = $this->dip_measurement_liters - $this->system_calculated_liters;

        if ($this->variance_liters < 0) {
            $this->variance_type = self::VARIANCE_LOSS;
        } elseif ($this->variance_liters > 0) {
            $this->variance_type = self::VARIANCE_GAIN;
        } else {
            $this->variance_type = self::VARIANCE_NONE;
        }
    }

    /**
     * Check if this reading can be edited.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if this reading has a loss.
     */
    public function hasLoss(): bool
    {
        return $this->variance_type === self::VARIANCE_LOSS;
    }

    /**
     * Check if this reading has a gain.
     */
    public function hasGain(): bool
    {
        return $this->variance_type === self::VARIANCE_GAIN;
    }

    public static function getReadingTypes(): array
    {
        return [
            self::TYPE_OPENING,
            self::TYPE_CLOSING,
            self::TYPE_SPOT_CHECK,
        ];
    }

    public static function getVarianceReasons(): array
    {
        return [
            self::REASON_EVAPORATION,
            self::REASON_LEAK_SUSPECTED,
            self::REASON_METER_FAULT,
            self::REASON_DIP_ERROR,
            self::REASON_TEMPERATURE,
            self::REASON_THEFT_SUSPECTED,
            self::REASON_UNKNOWN,
        ];
    }
}
