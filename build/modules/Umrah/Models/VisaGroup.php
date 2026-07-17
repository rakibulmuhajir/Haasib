<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisaGroup extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.visa_groups';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PASSPORTS_RECEIVED = 'passports_received';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VISA_APPROVED = 'visa_approved';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const TRANSPORT_STANDARD_BUS = 'standard_bus';

    public const TRANSPORT_SPECIALIZED = 'specialized';

    public const TRANSPORT_MODES = [
        self::TRANSPORT_STANDARD_BUS => 'Standard bus included with visa',
        self::TRANSPORT_SPECIALIZED => 'Specialized transport',
    ];

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PASSPORTS_RECEIVED => 'Passports received',
        self::STATUS_SUBMITTED => 'Submitted',
        self::STATUS_VISA_APPROVED => 'Visa approved',
        self::STATUS_DELIVERED => 'Delivered',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'company_id',
        'agent_id',
        'vendor_id',
        'mandatory_transport_vendor_id',
        'visa_service_id',
        'transport_service_id',
        'driver_id',
        'group_number',
        'name',
        'status',
        'travel_date',
        'flight_info',
        'hotel_info',
        'transport_required',
        'transport_mode',
        'included_bus_cost_per_passenger',
        'included_bus_cost_deduction',
        'mandatory_transport_cost_amount',
        'transport_quantity',
        'transport_pax_capacity',
        'passenger_count',
        'visa_sale_amount',
        'transport_amount',
        'hotel_amount',
        'discount_amount',
        'visa_cost_amount',
        'transport_cost_amount',
        'hotel_cost_amount',
        'total_receivable',
        'total_paid',
        'balance',
        'profit',
        'notes',
        'sale_transaction_id',
        'cost_transaction_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'agent_id' => 'string',
        'vendor_id' => 'string',
        'mandatory_transport_vendor_id' => 'string',
        'visa_service_id' => 'string',
        'transport_service_id' => 'string',
        'driver_id' => 'string',
        'travel_date' => 'date',
        'flight_info' => 'array',
        'hotel_info' => 'array',
        'transport_required' => 'boolean',
        'included_bus_cost_per_passenger' => 'decimal:2',
        'included_bus_cost_deduction' => 'decimal:2',
        'mandatory_transport_cost_amount' => 'decimal:2',
        'transport_quantity' => 'integer',
        'transport_pax_capacity' => 'integer',
        'passenger_count' => 'integer',
        'visa_sale_amount' => 'decimal:2',
        'transport_amount' => 'decimal:2',
        'hotel_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'visa_cost_amount' => 'decimal:2',
        'transport_cost_amount' => 'decimal:2',
        'hotel_cost_amount' => 'decimal:2',
        'total_receivable' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'balance' => 'decimal:2',
        'profit' => 'decimal:2',
        'sale_transaction_id' => 'string',
        'cost_transaction_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class, 'vendor_id')->withTrashed();
    }

    public function mandatoryTransportVendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class, 'mandatory_transport_vendor_id')->withTrashed();
    }

    public function visaService(): BelongsTo
    {
        return $this->belongsTo(VisaService::class)->withTrashed();
    }

    public function transportService(): BelongsTo
    {
        return $this->belongsTo(TransportService::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function passengers(): HasMany
    {
        return $this->hasMany(Passenger::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(GroupPayment::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class, 'visa_group_id')->whereNull('reversed_at');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'visa_group_id');
    }

    public function transportItems(): HasMany
    {
        return $this->hasMany(GroupTransportItem::class, 'visa_group_id');
    }

    public function saleTransaction(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Transaction::class, 'sale_transaction_id');
    }

    public function costTransaction(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Transaction::class, 'cost_transaction_id');
    }
}
