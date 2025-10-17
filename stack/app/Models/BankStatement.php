<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankStatement extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'ops.bank_statements';

    protected $primaryKey = 'id';

    protected $fillable = [
        'company_id',
        'ledger_account_id',
        'statement_uid',
        'statement_name',
        'opening_balance',
        'closing_balance',
        'currency',
        'statement_start_date',
        'statement_end_date',
        'file_path',
        'format',
        'imported_by',
        'imported_at',
        'processed_at',
        'status',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'closing_balance' => 'decimal:4',
        'statement_start_date' => 'date',
        'statement_end_date' => 'date',
        'imported_at' => 'datetime',
        'processed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'ledger_account_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function bankStatementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'statement_id');
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(BankReconciliation::class, 'statement_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForLedgerAccount($query, $accountId)
    {
        return $query->where('ledger_account_id', $accountId);
    }

    public function scopeWithStatus($query, array $statuses)
    {
        return $query->whereIn('status', $statuses);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    public function scopeReconciled($query)
    {
        return $query->where('status', 'reconciled');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->where('statement_start_date', '>=', $startDate)
                ->where('statement_end_date', '<=', $endDate);
        });
    }

    public function isProcessed(): bool
    {
        return $this->status === 'processed';
    }

    public function isReconciled(): bool
    {
        return $this->status === 'reconciled';
    }

    public function canBeReconciled(): bool
    {
        return $this->isProcessed() && ! $this->isReconciled();
    }

    public function getFormattedOpeningBalanceAttribute(): string
    {
        return number_format($this->opening_balance, 2);
    }

    public function getFormattedClosingBalanceAttribute(): string
    {
        return number_format($this->closing_balance, 2);
    }

    public function getStatementPeriodAttribute(): string
    {
        return $this->statement_start_date->format('M j, Y').' - '.$this->statement_end_date->format('M j, Y');
    }

    public function getTotalLinesAttribute(): int
    {
        return $this->bankStatementLines()->count();
    }

    public function getSumOfLinesAttribute(): string
    {
        return $this->bankStatementLines()->sum('amount');
    }
}
