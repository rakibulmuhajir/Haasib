<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'acct.company_bank_accounts';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_id',
        'gl_account_id',
        'account_name',
        'account_number',
        'account_type',
        'currency',
        'iban',
        'swift_code',
        'routing_number',
        'branch_name',
        'branch_address',
        'opening_balance',
        'opening_balance_date',
        'current_balance',
        'last_reconciled_balance',
        'last_reconciled_date',
        'is_primary',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bank_id' => 'string',
        'gl_account_id' => 'string',
        'opening_balance' => 'decimal:2',
        'opening_balance_date' => 'date',
        'current_balance' => 'decimal:2',
        'last_reconciled_balance' => 'decimal:2',
        'last_reconciled_date' => 'date',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'gl_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class, 'bank_account_id');
    }

    public function bankRules(): HasMany
    {
        return $this->hasMany(BankRule::class, 'bank_account_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Get unreconciled transactions count.
     */
    public function getUnreconciledCountAttribute(): int
    {
        return $this->transactions()->unreconciled()->count();
    }

    /**
     * Scope for active accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for primary account.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Check if account has transactions (for currency immutability).
     */
    public function hasTransactions(): bool
    {
        return $this->transactions()->exists();
    }

    /**
     * Check if account has unreconciled transactions (for deletion check).
     */
    public function hasUnreconciledTransactions(): bool
    {
        return $this->transactions()->unreconciled()->exists();
    }

    /**
     * Get the last reconciliation.
     */
    public function lastReconciliation(): ?BankReconciliation
    {
        return $this->reconciliations()
            ->completed()
            ->orderByDesc('statement_date')
            ->first();
    }
}
