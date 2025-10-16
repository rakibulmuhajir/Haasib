<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalTransaction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'acct.journal_transactions';

    /**
     * The primary key associated with the table.
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'journal_entry_id',
        'line_number',
        'account_id',
        'account_code',
        'account_name',
        'debit_credit',
        'amount',
        'description',
        'reconcile_id',
        'tax_code_id',
        'tax_amount',
        'currency',
        'exchange_rate',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'line_number' => 'integer',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the journal entry that owns the transaction.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account for the transaction.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the sources for this transaction.
     */
    public function sources(): HasMany
    {
        return $this->hasMany(JournalEntrySource::class, 'journal_transaction_id');
    }

    /**
     * Scope to get transactions for a specific journal entry.
     */
    public function scopeForJournalEntry($query, string $journalEntryId)
    {
        return $query->where('journal_entry_id', $journalEntryId);
    }

    /**
     * Scope to get transactions by account.
     */
    public function scopeForAccount($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope a query to only include debit transactions.
     */
    public function scopeDebits($query)
    {
        return $query->where('debit_credit', 'debit');
    }

    /**
     * Scope a query to only include credit transactions.
     */
    public function scopeCredits($query)
    {
        return $query->where('debit_credit', 'credit');
    }

    /**
     * Check if this is a debit transaction.
     */
    public function isDebit(): bool
    {
        return $this->debit_credit === 'debit';
    }

    /**
     * Check if this is a credit transaction.
     */
    public function isCredit(): bool
    {
        return $this->debit_credit === 'credit';
    }

    /**
     * Get the amount (same for debit/credit).
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Get the signed amount (positive for debits, negative for credits for asset accounts).
     */
    public function getSignedAmount(): float
    {
        if ($this->account && $this->account->normal_balance === 'debit') {
            return $this->isDebit() ? $this->amount : -$this->amount;
        } else {
            return $this->isCredit() ? $this->amount : -$this->amount;
        }
    }
}
