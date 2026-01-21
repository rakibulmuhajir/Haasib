<?php

namespace App\Modules\Payroll\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryAdvance extends Model
{
    use HasUuids, SoftDeletes;

    protected $connection = 'pgsql';
    protected $table = 'pay.salary_advances';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'employee_id',
        'advance_date',
        'amount',
        'amount_recovered',
        'amount_outstanding',
        'reason',
        'status',
        'payment_method',
        'bank_account_id',
        'reference',
        'journal_entry_id',
        'advance_account_id',
        'approved_by_user_id',
        'approved_at',
        'recorded_by_user_id',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'string',
        'employee_id' => 'string',
        'advance_date' => 'date',
        'amount' => 'decimal:2',
        'amount_recovered' => 'decimal:2',
        'amount_outstanding' => 'decimal:2',
        'bank_account_id' => 'string',
        'journal_entry_id' => 'string',
        'advance_account_id' => 'string',
        'approved_by_user_id' => 'string',
        'approved_at' => 'datetime',
        'recorded_by_user_id' => 'string',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function advanceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'advance_account_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function recoveries(): HasMany
    {
        return $this->hasMany(SalaryAdvanceRecovery::class);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePartiallyRecovered($query)
    {
        return $query->where('status', 'partially_recovered');
    }

    public function scopeFullyRecovered($query)
    {
        return $query->where('status', 'fully_recovered');
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['pending', 'partially_recovered']);
    }

    public function scopeForEmployee($query, string $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Accessors & Helpers
    // ─────────────────────────────────────────────────────────────────────

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsPartiallyRecoveredAttribute(): bool
    {
        return $this->status === 'partially_recovered';
    }

    public function getIsFullyRecoveredAttribute(): bool
    {
        return $this->status === 'fully_recovered';
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getHasOutstandingBalanceAttribute(): bool
    {
        return (float) $this->amount_outstanding > 0;
    }

    public function getRecoveryPercentageAttribute(): float
    {
        if ((float) $this->amount <= 0) {
            return 0;
        }
        return round(((float) $this->amount_recovered / (float) $this->amount) * 100, 2);
    }

    /**
     * Calculate maximum amount that can be recovered in a single deduction
     * (typically capped at a percentage of salary)
     */
    public function getMaxRecoveryAmount(?float $monthlySalary = null, float $maxPercentage = 50): float
    {
        $outstanding = (float) $this->amount_outstanding;

        if ($monthlySalary === null) {
            return $outstanding;
        }

        $maxFromSalary = $monthlySalary * ($maxPercentage / 100);
        return min($outstanding, $maxFromSalary);
    }
}
