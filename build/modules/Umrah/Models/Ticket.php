<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A ticket is dual-currency: the buyer's fare converts at one rate,
 * the supplier's cost converts at another, both to the same base
 * currency. Commission is derived, never stored -- see commissionBase().
 */
class Ticket extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.tickets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'ticket_booking_id',
        'ticket_number',
        'airline_ticket_number',
        'passenger_id',
        'passenger_name',
        'passport_number',
        'airline',
        'route',
        'travel_date',
        'sale_currency',
        'sale_exchange_rate',
        'gross_fare',
        'taxes',
        'discount',
        'service_fee',
        'gross_fare_base',
        'taxes_base',
        'discount_base',
        'service_fee_base',
        'supplier_currency',
        'supplier_exchange_rate',
        'supplier_cost',
        'supplier_cost_base',
        'base_currency',
        'status',
    ];

    protected $casts = [
        'company_id' => 'string',
        'ticket_booking_id' => 'string',
        'passenger_id' => 'string',
        'travel_date' => 'date',
        'sale_exchange_rate' => 'decimal:8',
        'gross_fare' => 'decimal:6',
        'taxes' => 'decimal:6',
        'discount' => 'decimal:6',
        'service_fee' => 'decimal:6',
        'gross_fare_base' => 'decimal:2',
        'taxes_base' => 'decimal:2',
        'discount_base' => 'decimal:2',
        'service_fee_base' => 'decimal:2',
        'supplier_exchange_rate' => 'decimal:8',
        'supplier_cost' => 'decimal:6',
        'supplier_cost_base' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(TicketBooking::class, 'ticket_booking_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(Passenger::class);
    }

    /**
     * Commission is what is left of the buyer's fare and taxes after the
     * supplier is paid. Deriving it means a corrected supplier cost cannot
     * leave a stale commission behind.
     */
    public function commissionBase(): float
    {
        return (float) $this->gross_fare_base
            + (float) $this->taxes_base
            - (float) $this->supplier_cost_base;
    }
}
