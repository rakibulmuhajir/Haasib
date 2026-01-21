<?php

namespace App\Models;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerTransaction extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'auth.partner_transactions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'partner_id',
        'transaction_date',
        'transaction_type',
        'amount',
        'description',
        'reference',
        'journal_entry_id',
        'payment_method',
        'bank_account_id',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'partner_id' => 'string',
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'journal_entry_id' => 'string',
        'bank_account_id' => 'string',
        'recorded_by_user_id' => 'string',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopeInvestments($query)
    {
        return $query->where('transaction_type', 'investment');
    }

    public function scopeWithdrawals($query)
    {
        return $query->where('transaction_type', 'withdrawal');
    }

    public function scopeAdjustments($query)
    {
        return $query->where('transaction_type', 'adjustment');
    }

    public function scopeInPeriod($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────────────

    public function getIsInvestmentAttribute(): bool
    {
        return $this->transaction_type === 'investment';
    }

    public function getIsWithdrawalAttribute(): bool
    {
        return $this->transaction_type === 'withdrawal';
    }

    public function getIsAdjustmentAttribute(): bool
    {
        return $this->transaction_type === 'adjustment';
    }
}
