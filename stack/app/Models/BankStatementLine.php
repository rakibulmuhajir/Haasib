<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class BankStatementLine extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'ops.bank_statement_lines';

    protected $primaryKey = 'id';

    protected $fillable = [
        'statement_id',
        'company_id',
        'transaction_date',
        'posted_at',
        'description',
        'reference_number',
        'amount',
        'balance_after',
        'external_id',
        'line_hash',
        'categorization',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'posted_at' => 'datetime',
        'amount' => 'decimal:4',
        'balance_after' => 'decimal:4',
        'categorization' => 'array',
    ];

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reconciliationMatch(): HasOne
    {
        return $this->hasOne(BankReconciliationMatch::class, 'statement_line_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForStatement($query, $statementId)
    {
        return $query->where('statement_id', $statementId);
    }

    public function scopeWithAmount($query, $amount)
    {
        return $query->where('amount', $amount);
    }

    public function scopeWithReference($query, $reference)
    {
        return $query->where('reference_number', $reference);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeWithDescription($query, $description)
    {
        return $query->where('description', 'ILIKE', "%{$description}%");
    }

    public function scopeUnmatched($query)
    {
        return $query->whereDoesntHave('reconciliationMatch');
    }

    public function scopeMatched($query)
    {
        return $query->whereHas('reconciliationMatch');
    }

    public function isMatched(): bool
    {
        return $this->reconciliationMatch !== null;
    }

    public function isUnmatched(): bool
    {
        return ! $this->isMatched();
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format(abs($this->amount), 2);
    }

    public function getAmountTypeAttribute(): string
    {
        return $this->amount >= 0 ? 'credit' : 'debit';
    }

    public function getSignedAmountAttribute(): string
    {
        $prefix = $this->amount < 0 ? '-' : '';

        return $prefix.number_format(abs($this->amount), 2);
    }

    public function getFormattedBalanceAfterAttribute(): ?string
    {
        return $this->balance_after ? number_format($this->balance_after, 2) : null;
    }

    public function getFormattedTransactionDateAttribute(): string
    {
        return $this->transaction_date->format('M j, Y');
    }

    public function getShortDescriptionAttribute(): string
    {
        return Str::limit($this->description, 50);
    }

    /**
     * Generate a unique hash for deduplication
     */
    public static function generateHash(array $data): string
    {
        $hashData = [
            'transaction_date' => $data['transaction_date'] ?? '',
            'description' => $data['description'] ?? '',
            'amount' => $data['amount'] ?? 0,
            'reference_number' => $data['reference_number'] ?? '',
        ];

        return hash('sha256', serialize($hashData));
    }

    /**
     * Check if this line is a duplicate based on hash
     */
    public function isDuplicateForStatement(string $statementId): bool
    {
        return static::where('statement_id', $statementId)
            ->where('line_hash', $this->line_hash)
            ->where('id', '!=', $this->id)
            ->exists();
    }

    /**
     * Find duplicates for the given statement
     */
    public static function findDuplicatesForStatement(string $statementId): array
    {
        return static::where('statement_id', $statementId)
            ->select('line_hash')
            ->groupBy('line_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('line_hash')
            ->toArray();
    }

    /**
     * Get searchable text for matching algorithms
     */
    public function getSearchableTextAttribute(): string
    {
        $parts = [
            $this->description,
            $this->reference_number,
            $this->external_id,
            $this->transaction_date->format('Y-m-d'),
            number_format(abs($this->amount), 2, '.', ''),
        ];

        return strtolower(implode(' ', array_filter($parts)));
    }

    /**
     * Get amount matching key for exact amount matching
     */
    public function getAmountMatchingKeyAttribute(): string
    {
        return number_format(abs($this->amount), 2, '.', '');
    }

    /**
     * Get date matching key for date-based matching
     */
    public function getDateMatchingKeyAttribute(): string
    {
        return $this->transaction_date->format('Y-m-d');
    }
}
