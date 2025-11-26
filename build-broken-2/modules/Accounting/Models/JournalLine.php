<?php

namespace Modules\Accounting\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use BelongsToCompany, HasFactory, HasUuids;

    /**
     * The table associated with the model.
     */
    protected $table = 'acct.journal_lines';

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
        'company_id',
        'journal_entry_id',
        'account_id',
        'account_number',
        'account_name',
        'description',
        'debit_amount',
        'credit_amount',
        'created_by_id',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'debit_amount' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'metadata' => 'array',
            'company_id' => 'string',
        ];
    }

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'metadata',
    ];

    /**
     * Get the journal entry that owns the journal line.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the account for this journal line.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the user who created the journal line.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Scope to get lines for a specific company.
     */
    public function scopeForCompany($query, string $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to get lines for a specific journal entry.
     */
    public function scopeForJournalEntry($query, string $journalEntryId)
    {
        return $query->where('journal_entry_id', $journalEntryId);
    }

    /**
     * Scope to get debit lines.
     */
    public function scopeDebit($query)
    {
        return $query->where('debit_amount', '>', 0);
    }

    /**
     * Scope to get credit lines.
     */
    public function scopeCredit($query)
    {
        return $query->where('credit_amount', '>', 0);
    }

    /**
     * Check if this is a debit line.
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    /**
     * Check if this is a credit line.
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    /**
     * Get the amount for this line (debit or credit).
     */
    public function getAmountAttribute(): float
    {
        return max($this->debit_amount, $this->credit_amount);
    }

    /**
     * Get the debit/credit type for this line.
     */
    public function getTypeAttribute(): string
    {
        return $this->isDebit() ? 'debit' : 'credit';
    }
}
