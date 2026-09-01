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

    public const SERVICE_TICKET = 'ticket';

    public const SERVICE_OTHER = 'other';

    /**
     * Every service a refund can name, including ones no longer offered.
     * Read this when displaying an existing refund; it has to be able to
     * label whatever is already stored.
     */
    public const SERVICES = [
        self::SERVICE_VISA => 'Visa',
        self::SERVICE_TRANSPORT => 'Transport',
        self::SERVICE_HOTEL => 'Hotel',
        self::SERVICE_TICKET => 'Ticket',
        self::SERVICE_OTHER => 'Other',
    ];

    /**
     * What an agent's refund may name. Visa is absent by design: a group is
     * built after the visas come back approved and only the approved ones
     * are imported into it, so by the time a group exists there is no visa
     * left to give back to the buyer. The three that remain are the three a
     * passenger can drop out of -- their hotel, their ticket, their seat.
     *
     * Refunds are partial by nature. One person out of a group not
     * travelling is the ordinary case, which is why the amount is free and
     * this says which part of their package it came from.
     *
     * A vendor refund is the other direction entirely -- a supplier giving
     * money back to the company -- and a visa desk returning a fee is an
     * ordinary thing for one to do. Those keep the full list.
     */
    public const AGENT_SERVICES = [
        self::SERVICE_HOTEL => 'Hotel',
        self::SERVICE_TICKET => 'Ticket',
        self::SERVICE_TRANSPORT => 'Transport',
        self::SERVICE_OTHER => 'Other',
    ];

    /**
     * What this party could possibly be refunding.
     *
     * A visa desk gives back visa fees. It has never sold anyone a hotel
     * room, so offering it the choice is asking a question with one real
     * answer and three wrong ones. Other stays on each list as the escape
     * hatch for a fee that fits nowhere else.
     */
    /**
     * Why money went back, in a form something can count.
     *
     * The two sides ask different questions. An agent is refunded because
     * of what happened to their pilgrims -- somebody did not travel, or
     * they paid us too much. A supplier gives money back because of what
     * happened to a bill -- they overcharged, or the price was
     * renegotiated afterwards. One list for both would offer each of them
     * the other's reasons.
     *
     * Other is on both, because a list that cannot say everything must not
     * make someone pick the nearest wrong thing.
     */
    public const AGENT_REASONS = [
        'passenger_withdrew' => 'A passenger did not travel',
        'overpaid' => 'The agent paid more than the group came to',
        'service_not_provided' => 'A service was not provided',
        'error' => 'Corrected an error in what we charged',
        'goodwill' => 'Goodwill',
        'other' => 'Other',
    ];

    public const VENDOR_REASONS = [
        'renegotiated' => 'Price renegotiated after we were billed',
        'overcharged' => 'The supplier overcharged us',
        'service_not_provided' => 'A service was not provided',
        'error' => 'Corrected an error in their invoice',
        'other' => 'Other',
    ];

    public static function reasonsFor(?string $partyType): array
    {
        return $partyType === self::PARTY_AGENT ? self::AGENT_REASONS : self::VENDOR_REASONS;
    }

    public static function servicesFor(?string $partyType): array
    {
        return match ($partyType) {
            self::PARTY_AGENT => self::AGENT_SERVICES,
            self::PARTY_VISA_VENDOR => [
                self::SERVICE_VISA => self::SERVICES[self::SERVICE_VISA],
                self::SERVICE_OTHER => self::SERVICES[self::SERVICE_OTHER],
            ],
            self::PARTY_TRANSPORT_VENDOR => [
                self::SERVICE_TRANSPORT => self::SERVICES[self::SERVICE_TRANSPORT],
                self::SERVICE_OTHER => self::SERVICES[self::SERVICE_OTHER],
            ],
            self::PARTY_HOTEL_VENDOR => [
                self::SERVICE_HOTEL => self::SERVICES[self::SERVICE_HOTEL],
                self::SERVICE_OTHER => self::SERVICES[self::SERVICE_OTHER],
            ],
            default => self::SERVICES,
        };
    }

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
        'reason_category',
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
