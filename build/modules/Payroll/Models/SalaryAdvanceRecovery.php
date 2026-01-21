<?php

namespace App\Modules\Payroll\Models;

use App\Models\Company;
use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryAdvanceRecovery extends Model
{
    use HasUuids;

    protected $connection = 'pgsql';
    protected $table = 'pay.salary_advance_recoveries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'salary_advance_id',
        'payslip_id',
        'recovery_date',
        'amount',
        'recovery_type',
        'reference',
        'journal_entry_id',
        'recorded_by_user_id',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'string',
        'salary_advance_id' => 'string',
        'payslip_id' => 'string',
        'recovery_date' => 'date',
        'amount' => 'decimal:2',
        'journal_entry_id' => 'string',
        'recorded_by_user_id' => 'string',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────────────

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function salaryAdvance(): BelongsTo
    {
        return $this->belongsTo(SalaryAdvance::class);
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────────────

    public function scopePayrollDeductions($query)
    {
        return $query->where('recovery_type', 'payroll_deduction');
    }

    public function scopeManualRepayments($query)
    {
        return $query->where('recovery_type', 'manual_repayment');
    }

    public function scopeAdjustments($query)
    {
        return $query->where('recovery_type', 'adjustment');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────────────────────────────

    public function getIsPayrollDeductionAttribute(): bool
    {
        return $this->recovery_type === 'payroll_deduction';
    }

    public function getIsManualRepaymentAttribute(): bool
    {
        return $this->recovery_type === 'manual_repayment';
    }

    public function getIsAdjustmentAttribute(): bool
    {
        return $this->recovery_type === 'adjustment';
    }
}
