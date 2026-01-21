<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.transactions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'transaction_number',
        'transaction_type',
        'reference_type',
        'reference_id',
        'transaction_date',
        'posting_date',
        'fiscal_year_id',
        'period_id',
        'description',
        'metadata',
        'currency',
        'base_currency',
        'exchange_rate',
        'total_debit',
        'total_credit',
        'status',
        'reversal_of_id',
        'reversed_by_id',
        'corrects_transaction_id',
        'amendment_reason',
        'amended_at',
        'amended_by_user_id',
        'posted_at',
        'posted_by_user_id',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
        'is_locked',
        'locked_at',
        'locked_by_user_id',
        'lock_reason',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'reference_id' => 'string',
        'transaction_date' => 'date',
        'posting_date' => 'date',
        'fiscal_year_id' => 'string',
        'period_id' => 'string',
        'metadata' => 'array',
        'exchange_rate' => 'decimal:8',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'reversal_of_id' => 'string',
        'reversed_by_id' => 'string',
        'corrects_transaction_id' => 'string',
        'amended_at' => 'datetime',
        'amended_by_user_id' => 'string',
        'posted_at' => 'datetime',
        'posted_by_user_id' => 'string',
        'voided_at' => 'datetime',
        'voided_by_user_id' => 'string',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'locked_by_user_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function period()
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'transaction_id');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversedBy()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * The transaction that this entry corrects (for amendments).
     */
    public function correctsTransaction()
    {
        return $this->belongsTo(self::class, 'corrects_transaction_id');
    }

    /**
     * The correction entry that replaced this transaction.
     */
    public function correctedBy()
    {
        return $this->hasOne(self::class, 'corrects_transaction_id');
    }

    /**
     * User who locked this transaction.
     */
    public function lockedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'locked_by_user_id');
    }

    /**
     * User who amended this transaction.
     */
    public function amendedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'amended_by_user_id');
    }

    /**
     * Get the display status for this transaction.
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->reversed_by_id) {
            return 'reversed';
        }
        if ($this->reversal_of_id) {
            return 'reversal';
        }
        if ($this->corrects_transaction_id) {
            return 'correction';
        }
        if ($this->is_locked) {
            return 'locked';
        }
        return 'posted';
    }

    /**
     * Check if this transaction can be amended.
     */
    public function isAmendable(): bool
    {
        return !$this->is_locked
            && !$this->reversed_by_id
            && !$this->voided_at
            && in_array($this->transaction_type, ['fuel_daily_close']);
    }

    /**
     * Check if this transaction can be locked.
     */
    public function isLockable(): bool
    {
        return !$this->is_locked
            && !$this->reversed_by_id
            && !$this->voided_at;
    }

    /**
     * Lock this transaction.
     */
    public function lock(string $userId, string $reason = 'manual'): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by_user_id' => $userId,
            'lock_reason' => $reason,
        ]);
    }

    /**
     * Unlock this transaction (owner only).
     */
    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by_user_id' => null,
            'lock_reason' => null,
        ]);
    }

    /**
     * Generate a simple journal number scoped per company.
     */
    public static function generateJournalNumber(string $companyId): string
    {
        $last = self::where('company_id', $companyId)
            ->where('transaction_type', 'manual')
            ->orderByDesc('created_at')
            ->value('transaction_number');

        $prefix = 'JNL-';
        $next = 1;

        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $next = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
