<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransportService extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'umrah.transport_services';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'vehicle_type_id',
        'name',
        'make',
        'model',
        'color',
        'number_plate',
        'driver_name',
        'driver_contact',
        'default_sale_amount',
        'default_cost_amount',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'company_id' => 'string',
        'vehicle_type_id' => 'string',
        'default_sale_amount' => 'decimal:2',
        'default_cost_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(VisaGroup::class);
    }
}
