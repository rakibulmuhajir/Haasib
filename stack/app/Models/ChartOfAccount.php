<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acct.accounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'account_group_id',
        'code',
        'name',
        'description',
        'normal_balance',
        'is_active',
        'allow_manual_entries',
        'account_type',
        'currency',
        'opening_balance',
        'opening_balance_date',
        'parent_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'is_active' => 'boolean',
            'allow_manual_entries' => 'boolean',
            'company_id' => 'string',
            'account_group_id' => 'string',
            'parent_id' => 'string',
        ];
    }

    /**
     * Get the company that owns the account.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the account group for the account.
     */
    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class);
    }

    /**
     * Get the parent account.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child accounts.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Get the journal transactions for the account.
     */
    public function journalTransactions(): HasMany
    {
        return $this->hasMany(JournalTransaction::class);
    }

    /**
     * Scope a query to only include active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by account type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    /**
     * Scope a query to get parent accounts (no parent_id).
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to get detail accounts (has parent_id).
     */
    public function scopeDetails($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Get the current balance for the account.
     */
    public function getCurrentBalance(): float
    {
        $debits = $this->journalTransactions()
            ->where('debit_credit', 'debit')
            ->sum('amount');

        $credits = $this->journalTransactions()
            ->where('debit_credit', 'credit')
            ->sum('amount');

        if ($this->normal_balance === 'debit') {
            return $debits - $credits + $this->opening_balance;
        } else {
            return $credits - $debits + $this->opening_balance;
        }
    }

    /**
     * Get the balance for a specific period.
     */
    public function getPeriodBalance($startDate, $endDate): float
    {
        $debits = $this->journalTransactions()
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->where('debit_credit', 'debit')
            ->sum('amount');

        $credits = $this->journalTransactions()
            ->whereHas('journalEntry', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->where('debit_credit', 'credit')
            ->sum('amount');

        if ($this->normal_balance === 'debit') {
            return $debits - $credits;
        } else {
            return $credits - $debits;
        }
    }

    /**
     * Check if this is a balance sheet account.
     */
    public function isBalanceSheet(): bool
    {
        return in_array($this->accountGroup->accountClass->type ?? '', [
            'balance_sheet',
        ]);
    }

    /**
     * Check if this is an income statement account.
     */
    public function isIncomeStatement(): bool
    {
        return in_array($this->accountGroup->accountClass->type ?? '', [
            'income_statement',
        ]);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return \Database\Factories\ChartOfAccountFactory::new();
    }
}
