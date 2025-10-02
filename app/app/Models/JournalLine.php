<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class JournalLine extends Model
{
    use HasFactory;

    protected $table = 'acct.journal_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'company_id',
        'journal_entry_id',
        'ledger_account_id',
        'description',
        'debit_amount',
        'credit_amount',
        'line_number',
        'metadata',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'line_number' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'debit_amount' => 0,
        'credit_amount' => 0,
        'line_number' => 1,
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = $model->id ?: (string) Str::uuid();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForJournalEntry($query, $journalEntryId)
    {
        return $query->where('journal_entry_id', $journalEntryId);
    }

    public function scopeForLedgerAccount($query, $accountId)
    {
        return $query->where('ledger_account_id', $accountId);
    }

    public function hasAmount(): bool
    {
        return $this->debit_amount > 0 || $this->credit_amount > 0;
    }

    public function getAmount(): float
    {
        return max($this->debit_amount, $this->credit_amount);
    }

    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    public function getEffect(): string
    {
        if ($this->debit_amount > 0) {
            return 'debit';
        }
        if ($this->credit_amount > 0) {
            return 'credit';
        }

        return 'zero';
    }
}
