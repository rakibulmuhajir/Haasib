<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acct.journal_entries';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'reference',
        'description',
        'date',
        'type',
        'status',
        'created_by',
        'posted_by',
        'posted_at',
        'voided_by',
        'voided_at',
        'void_reason',
        'currency',
        'exchange_rate',
        'fiscal_year_id',
        'accounting_period_id',
        'attachments',
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
            'date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
            'exchange_rate' => 'decimal:8',
            'company_id' => 'string',
            'created_by' => 'string',
            'posted_by' => 'string',
            'voided_by' => 'string',
            'fiscal_year_id' => 'string',
            'accounting_period_id' => 'string',
            'attachments' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the company that owns the journal entry.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who posted the entry.
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Get the user who voided the entry.
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Get the fiscal year for the entry.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the accounting period for the entry.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    /**
     * Get the transactions for the journal entry.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class);
    }

    /**
     * Scope a query to only include entries with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include draft entries.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope a query to only include approved entries.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope a query to only include posted entries.
     */
    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope a query to filter by journal type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Check if the entry is balanced.
     */
    public function checkBalanced(): bool
    {
        $totalDebits = $this->transactions()->where('debit_credit', 'debit')->sum('amount');
        $totalCredits = $this->transactions()->where('debit_credit', 'credit')->sum('amount');

        return abs($totalDebits - $totalCredits) < 0.01;
    }

    /**
     * Post the journal entry.
     */
    public function post(User $user): void
    {
        if ($this->status !== 'draft') {
            throw new \Exception('Journal entry must be in draft status to post');
        }

        if (! $this->checkBalanced()) {
            throw new \Exception('Cannot post unbalanced journal entry');
        }

        $this->status = 'posted';
        $this->posted_at = now();
        $this->posted_by = $user->id;
        $this->save();
    }

    /**
     * Void the journal entry.
     */
    public function void(User $user, string $reason): void
    {
        if ($this->status === 'void') {
            throw new \Exception('Journal entry is already void');
        }

        $this->status = 'void';
        $this->voided_at = now();
        $this->voided_by = $user->id;
        $this->void_reason = $reason;
        $this->save();
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReference(string $companyId, string $type = 'JE'): string
    {
        $year = now()->format('Y');
        $sequence = static::where('company_id', $companyId)
            ->where('type', $type)
            ->whereYear('created_at', $year)
            ->count() + 1;

        return "{$type}-{$year}-{$sequence}";
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\JournalEntryFactory::new();
    }
}
