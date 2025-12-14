<?php

namespace App\Modules\Accounting\Models;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'acct.bank_reconciliations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'statement_date',
        'statement_ending_balance',
        'book_balance',
        'reconciled_balance',
        'difference',
        'status',
        'started_at',
        'completed_at',
        'completed_by_user_id',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'company_id' => 'string',
        'bank_account_id' => 'string',
        'statement_date' => 'date',
        'statement_ending_balance' => 'decimal:2',
        'book_balance' => 'decimal:2',
        'reconciled_balance' => 'decimal:2',
        'difference' => 'decimal:2',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'completed_by_user_id' => 'string',
        'created_by_user_id' => 'string',
        'updated_by_user_id' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'reconciliation_id');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
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
     * Check if reconciliation can be completed.
     */
    public function canComplete(): bool
    {
        return abs($this->difference) < 0.01;
    }

    /**
     * Check if reconciliation is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if reconciliation is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Scope for in-progress reconciliations.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for completed reconciliations.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
