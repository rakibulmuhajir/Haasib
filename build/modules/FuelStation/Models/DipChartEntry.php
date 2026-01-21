<?php

namespace App\Modules\FuelStation\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DipChartEntry extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.dip_chart_entries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'dip_stick_id',
        'stick_reading',
        'liters',
    ];

    protected $casts = [
        'dip_stick_id' => 'string',
        'stick_reading' => 'decimal:2',
        'liters' => 'decimal:2',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function dipStick(): BelongsTo
    {
        return $this->belongsTo(DipStick::class);
    }
}
