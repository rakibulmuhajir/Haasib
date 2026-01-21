<?php

namespace App\Modules\FuelStation\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendant Handover - Cash transit from attendants to company.
 * Control surface for fraud/mistakes.
 *
 * Channel breakdown matters because:
 * - "Boss, I already sent it" → which channel? verify
 * - Mobile wallet fraud detection
 * - Reconcile card swipes with bank settlement
 * - Track vendor card separately (goes to clearing, not cash)
 */
class AttendantHandover extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'fuel.attendant_handovers';
    protected $keyType = 'string';
    public $incrementing = false;

    public const SHIFT_DAY = 'day';
    public const SHIFT_NIGHT = 'night';

    public const STATUS_PENDING = 'pending';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_RECONCILED = 'reconciled';

    protected $fillable = [
        'company_id',
        'attendant_id',
        'handover_date',
        'pump_id',
        'shift',
        'cash_amount',
        'easypaisa_amount',
        'jazzcash_amount',
        'bank_transfer_amount',
        'card_swipe_amount',
        'parco_card_amount',
        'total_amount',
        'destination_bank_id',
        'status',
        'received_by_user_id',
        'received_at',
        'journal_entry_id',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'string',
        'attendant_id' => 'string',
        'handover_date' => 'datetime',
        'pump_id' => 'string',
        'cash_amount' => 'decimal:2',
        'easypaisa_amount' => 'decimal:2',
        'jazzcash_amount' => 'decimal:2',
        'bank_transfer_amount' => 'decimal:2',
        'card_swipe_amount' => 'decimal:2',
        'parco_card_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'destination_bank_id' => 'string',
        'received_by_user_id' => 'string',
        'received_at' => 'datetime',
        'journal_entry_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AttendantHandover $handover) {
            // Calculate total from channel amounts
            $handover->total_amount = $handover->cash_amount
                + $handover->easypaisa_amount
                + $handover->jazzcash_amount
                + $handover->bank_transfer_amount
                + $handover->card_swipe_amount
                + $handover->parco_card_amount;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function attendant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attendant_id');
    }

    public function pump(): BelongsTo
    {
        return $this->belongsTo(Pump::class);
    }

    public function destinationBank(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_bank_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the amount going to bank (excludes vendor card which goes to clearing).
     */
    public function getBankableAmountAttribute(): float
    {
        return $this->total_amount - $this->parco_card_amount;
    }

    /**
     * Get the digital wallet amount (easypaisa + jazzcash).
     */
    public function getDigitalWalletAmountAttribute(): float
    {
        return $this->easypaisa_amount + $this->jazzcash_amount;
    }

    /**
     * Check if handover is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if handover is received.
     */
    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    /**
     * Check if handover is reconciled.
     */
    public function isReconciled(): bool
    {
        return $this->status === self::STATUS_RECONCILED;
    }

    /**
     * Mark as received.
     */
    public function markAsReceived(string $receivedByUserId): void
    {
        $this->status = self::STATUS_RECEIVED;
        $this->received_by_user_id = $receivedByUserId;
        $this->received_at = now();
        $this->save();
    }

    public static function getShifts(): array
    {
        return [
            self::SHIFT_DAY,
            self::SHIFT_NIGHT,
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_RECEIVED,
            self::STATUS_RECONCILED,
        ];
    }
}
