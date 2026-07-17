<?php

namespace App\Modules\Umrah\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupTransportItem extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.group_transport_items';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id', 'visa_group_id', 'transport_vendor_id', 'transport_fare_id', 'transport_service_id', 'transport_sector_id',
        'transport_package_id', 'driver_id', 'description', 'scheduled_at', 'terminal', 'charging_basis',
        'quantity', 'passenger_count', 'unit_sale_amount', 'unit_cost_amount', 'surcharge_sale_amount',
        'surcharge_cost_amount', 'total_sale_amount', 'total_cost_amount', 'notes',
    ];

    protected $casts = [
        'company_id' => 'string', 'visa_group_id' => 'string', 'transport_vendor_id' => 'string', 'transport_fare_id' => 'string',
        'transport_service_id' => 'string', 'transport_sector_id' => 'string', 'transport_package_id' => 'string',
        'driver_id' => 'string', 'scheduled_at' => 'datetime', 'quantity' => 'integer', 'passenger_count' => 'integer',
        'unit_sale_amount' => 'decimal:2', 'unit_cost_amount' => 'decimal:2', 'surcharge_sale_amount' => 'decimal:2',
        'surcharge_cost_amount' => 'decimal:2', 'total_sale_amount' => 'decimal:2', 'total_cost_amount' => 'decimal:2',
        'created_at' => 'datetime', 'updated_at' => 'datetime', 'deleted_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(VisaGroup::class, 'visa_group_id');
    }

    public function fare(): BelongsTo
    {
        return $this->belongsTo(TransportFare::class, 'transport_fare_id')->withTrashed();
    }

    public function transportVendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class, 'transport_vendor_id')->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TransportService::class, 'transport_service_id')->withTrashed();
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(TransportSector::class, 'transport_sector_id')->withTrashed();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TransportPackage::class, 'transport_package_id')->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }
}
