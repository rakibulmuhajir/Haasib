<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class DipStick extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.dip_sticks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'unit',
        'max_reading',
        'notes',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'max_reading' => 'decimal:2',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function chartEntries(): HasMany
    {
        return $this->hasMany(DipChartEntry::class)->orderBy('stick_reading');
    }

    public function tank(): HasOne
    {
        return $this->hasOne(Warehouse::class, 'dip_stick_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Chart Conversion Methods
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Convert a stick reading to liters using the chart.
     * Uses interpolation for readings between chart entries.
     */
    public function convertToLiters(float $stickReading): ?float
    {
        // Use the database function for consistency
        $result = DB::selectOne(
            'SELECT fuel.convert_dip_reading(?, ?) as liters',
            [$this->id, $stickReading]
        );

        return $result?->liters !== null ? (float) $result->liters : null;
    }

    /**
     * Convert liters to approximate stick reading (inverse lookup).
     * Uses interpolation for values between chart entries.
     */
    public function convertToReading(float $liters): ?float
    {
        // Get exact match
        $exact = $this->chartEntries()
            ->where('liters', $liters)
            ->first();

        if ($exact) {
            return (float) $exact->stick_reading;
        }

        // Get bounds for interpolation
        $lower = $this->chartEntries()
            ->where('liters', '<', $liters)
            ->orderByDesc('liters')
            ->first();

        $upper = $this->chartEntries()
            ->where('liters', '>', $liters)
            ->orderBy('liters')
            ->first();

        if ($lower && $upper) {
            // Linear interpolation
            $fraction = ($liters - $lower->liters) / ($upper->liters - $lower->liters);
            return round($lower->stick_reading + $fraction * ($upper->stick_reading - $lower->stick_reading), 2);
        }

        return null;
    }

    /**
     * Check if a reading is within the valid range of the chart.
     */
    public function isValidReading(float $stickReading): bool
    {
        $min = $this->chartEntries()->min('stick_reading');
        $max = $this->chartEntries()->max('stick_reading');

        if ($min === null || $max === null) {
            return false;
        }

        return $stickReading >= $min && $stickReading <= $max;
    }

    /**
     * Get the minimum and maximum readings from the chart.
     */
    public function getReadingRange(): array
    {
        return [
            'min' => $this->chartEntries()->min('stick_reading'),
            'max' => $this->chartEntries()->max('stick_reading'),
        ];
    }

    /**
     * Get the minimum and maximum liters from the chart.
     */
    public function getLitersRange(): array
    {
        return [
            'min' => $this->chartEntries()->min('liters'),
            'max' => $this->chartEntries()->max('liters'),
        ];
    }

    /**
     * Bulk import chart entries from an array.
     * Format: [[reading => liters], ...]
     */
    public function importChart(array $entries): int
    {
        $count = 0;

        foreach ($entries as $reading => $liters) {
            $this->chartEntries()->updateOrCreate(
                ['stick_reading' => $reading],
                ['liters' => $liters]
            );
            $count++;
        }

        return $count;
    }
}
