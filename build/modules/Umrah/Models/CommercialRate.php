<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommercialRate extends Model
{
    use HasUuids, SoftDeletes;

    public const SERVICE_VISA_ADULT = 'visa_adult';

    public const SERVICE_VISA_CHILD = 'visa_child';

    public const SERVICE_STANDARD_TRANSPORT = 'standard_transport';

    public const SERVICE_TRANSPORT_FARE = 'transport_fare';

    public const SERVICE_HOTEL_ROOM = 'hotel_room';

    public const SERVICE_TYPES = [
        self::SERVICE_VISA_ADULT => 'Visa · adult',
        self::SERVICE_VISA_CHILD => 'Visa · child',
        self::SERVICE_STANDARD_TRANSPORT => 'Standard transport',
        self::SERVICE_TRANSPORT_FARE => 'Specialized transport',
        self::SERVICE_HOTEL_ROOM => 'Hotel room',
    ];

    public const SCOPE_DEFAULT = 'default';

    public const SCOPE_CATEGORY = 'category';

    public const SCOPE_AGENT = 'agent';

    public const SCOPE_TYPES = [
        self::SCOPE_DEFAULT => 'Default rate',
        self::SCOPE_CATEGORY => 'Agent category',
        self::SCOPE_AGENT => 'Specific agent',
    ];

    public const CALC_SET_PRICE = 'set_price';

    public const CALC_DISCOUNT_AMOUNT = 'discount_amount';

    public const CALC_DISCOUNT_PERCENTAGE = 'discount_percentage';

    public const CALC_MARKUP_AMOUNT = 'markup_amount';

    public const CALC_MARKUP_PERCENTAGE = 'markup_percentage';

    public const CALCULATION_TYPES = [
        self::CALC_SET_PRICE => 'Set exact price',
        self::CALC_DISCOUNT_AMOUNT => 'Discount amount',
        self::CALC_DISCOUNT_PERCENTAGE => 'Discount percentage',
        self::CALC_MARKUP_AMOUNT => 'Markup amount',
        self::CALC_MARKUP_PERCENTAGE => 'Markup percentage',
    ];

    protected $connection = 'pgsql';

    protected $table = 'umrah.commercial_rates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id', 'service_type', 'visa_vendor_id', 'transport_fare_id', 'hotel_room_rate_id',
        'scope_type', 'pricing_category_id', 'agent_id', 'calculation_type', 'amount', 'percentage',
        'cost_amount', 'currency', 'effective_from', 'effective_until', 'notes', 'is_active', 'created_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'visa_vendor_id' => 'string',
        'transport_fare_id' => 'string',
        'hotel_room_rate_id' => 'string',
        'pricing_category_id' => 'string',
        'agent_id' => 'string',
        'amount' => 'decimal:2',
        'percentage' => 'decimal:4',
        'cost_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function visaVendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class)->withTrashed();
    }

    public function transportFare(): BelongsTo
    {
        return $this->belongsTo(TransportFare::class)->withTrashed();
    }

    public function hotelRoomRate(): BelongsTo
    {
        return $this->belongsTo(HotelRoomRate::class)->withTrashed();
    }

    public function pricingCategory(): BelongsTo
    {
        return $this->belongsTo(PricingCategory::class)->withTrashed();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class)->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function targetId(): string
    {
        return (string) ($this->visa_vendor_id ?: $this->transport_fare_id ?: $this->hotel_room_rate_id);
    }
}
