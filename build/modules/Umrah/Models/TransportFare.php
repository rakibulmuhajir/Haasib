<?php

namespace App\Modules\Umrah\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportFare extends Model
{
    use HasUuids, SoftDeletes;

    public const BASIS_PER_VEHICLE = 'per_vehicle';
    public const BASIS_PER_PASSENGER = 'per_passenger';
    public const BASIS_FLAT_GROUP = 'flat_group';
    public const BASES = [
        self::BASIS_PER_VEHICLE => 'Per vehicle',
        self::BASIS_PER_PASSENGER => 'Per passenger',
        self::BASIS_FLAT_GROUP => 'Flat group',
    ];

    protected $connection = 'pgsql';
    protected $table = 'umrah.transport_fares';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id', 'transport_service_id', 'transport_sector_id', 'transport_package_id', 'name',
        'charging_basis', 'sale_amount', 'cost_amount', 'hajj_terminal_sale_amount',
        'hajj_terminal_cost_amount', 'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'transport_service_id' => 'string',
        'transport_sector_id' => 'string',
        'transport_package_id' => 'string',
        'sale_amount' => 'decimal:2',
        'cost_amount' => 'decimal:2',
        'hajj_terminal_sale_amount' => 'decimal:2',
        'hajj_terminal_cost_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function service(): BelongsTo { return $this->belongsTo(TransportService::class, 'transport_service_id')->withTrashed(); }
    public function sector(): BelongsTo { return $this->belongsTo(TransportSector::class, 'transport_sector_id')->withTrashed(); }
    public function package(): BelongsTo { return $this->belongsTo(TransportPackage::class, 'transport_package_id')->withTrashed(); }
}
