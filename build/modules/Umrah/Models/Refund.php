<?php

namespace App\Modules\Umrah\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

/**
 * Phase 1 of docs/contracts/refunds.md -- "The obligation". A refund is an
 * obligation, not a payment: it exists, gets approved or refused, and money
 * moving is the last step (Phase 2), not the record itself.
 */
class Refund extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';

    protected $table = 'umrah.refunds';

    protected $keyType = 'string';

    public $incrementing = false;

    public const PARTY_AGENT = 'agent';

    public const PARTY_VISA_VENDOR = 'visa_vendor';

    public const PARTY_TRANSPORT_VENDOR = 'transport_vendor';

    public const PARTY_HOTEL_VENDOR = 'hotel_vendor';

    public const PARTY_TYPES = [
        self::PARTY_AGENT => 'Agent',
        self::PARTY_VISA_VENDOR => 'Visa vendor',
        self::PARTY_TRANSPORT_VENDOR => 'Transport vendor',
        self::PARTY_HOTEL_VENDOR => 'Hotel vendor',
    ];

    public const SERVICE_VISA = 'visa';

    public const SERVICE_TRANSPORT = 'transport';

    public const SERVICE_HOTEL = 'hotel';

    public const SERVICE_OTHER = 'other';

    public const SERVICES = [
        self::SERVICE_VISA => 'Visa',
        self::SERVICE_TRANSPORT => 'Transport',
        self::SERVICE_HOTEL => 'Hotel',
        self::SERVICE_OTHER => 'Other',
    ];

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_REQUESTED => 'Requested',
        self::STATUS_ACCEPTED => 'Accepted',
        self::STATUS_REFUNDED => 'Refunded',
        self::STATUS_CREDITED => 'Kept as credit',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const SETTLEMENT_CASH = 'cash';

    public const SETTLEMENT_CREDIT = 'credit';

    public const SETTLEMENT_METHODS = [
        self::SETTLEMENT_CASH => 'Pay it back',
        self::SETTLEMENT_CREDIT => 'Keep as credit',
    ];

    protected $fillable = [
        'company_id',
        'party_type',
        'party_id',
        'visa_group_id',
        'service',
        'refund_number',
        'amount',
        'currency',
        'exchange_rate',
        'base_currency',
        'base_amount',
        'reason',
        'status',
        'requested_by_user_id',
        'requested_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_remarks',
        'settled_payment_id',
        'transaction_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason',
        'settlement_method',
        'settled_at',
        'settled_by_user_id',
        'cancellation_transaction_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'party_id' => 'string',
        'visa_group_id' => 'string',
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:8',
        'base_amount' => 'decimal:2',
        'requested_by_user_id' => 'string',
        'requested_at' => 'datetime',
        'reviewed_by_user_id' => 'string',
        'reviewed_at' => 'datetime',
        'settled_payment_id' => 'string',
        'transaction_id' => 'string',
        'cancelled_at' => 'datetime',
        'cancelled_by_user_id' => 'string',
        'settled_at' => 'datetime',
        'settled_by_user_id' => 'string',
        'cancellation_transaction_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Invariant 4: an approved refund's amount, party_id and service are
     * immutable. Cancel and re-request instead -- the same rule an approved
     * voucher already follows. This is a last line of defence: RefundService
     * never edits these fields past approval, but a boot guard means the
     * invariant holds even if something else ever tries.
     */
    protected static function booted(): void
    {
        static::updating(function (self $refund): void {
            $original = $refund->getOriginal('status');

            if (! in_array($original, [self::STATUS_ACCEPTED, self::STATUS_REFUNDED, self::STATUS_CREDITED, self::STATUS_CANCELLED], true)) {
                return;
            }

            foreach (['amount', 'party_id', 'service'] as $field) {
                if ($refund->isDirty($field)) {
                    throw new RuntimeException("Refund {$field} cannot change once the refund has been approved. Cancel and re-request instead.");
                }
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(VisaGroup::class, 'visa_group_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'party_id');
    }

    public function visaVendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class, 'party_id');
    }

    public function transportVendor(): BelongsTo
    {
        return $this->belongsTo(VisaVendor::class, 'party_id');
    }

    public function hotelVendor(): BelongsTo
    {
        return $this->belongsTo(HotelVendor::class, 'party_id');
    }

    public function settledPayment(): BelongsTo
    {
        return $this->belongsTo(GroupPayment::class, 'settled_payment_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Transaction::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }

    public function cancellationTransaction(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Accounting\Models\Transaction::class, 'cancellation_transaction_id');
    }
}
