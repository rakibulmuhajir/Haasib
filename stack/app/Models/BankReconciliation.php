<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use BelongsToCompany;
    use HasFactory;
    use HasUuids;

    protected $table = 'ledger.bank_reconciliations';

    protected $primaryKey = 'id';

    protected $fillable = [
        'company_id',
        'statement_id',
        'ledger_account_id',
        'started_by',
        'started_at',
        'completed_by',
        'completed_at',
        'status',
        'unmatched_statement_total',
        'unmatched_internal_total',
        'variance',
        'notes',
        'locked_at',
    ];

    protected $casts = [
        'unmatched_statement_total' => 'decimal:4',
        'unmatched_internal_total' => 'decimal:4',
        'variance' => 'decimal:4',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'draft',
        'unmatched_statement_total' => 0,
        'unmatched_internal_total' => 0,
        'variance' => 0,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'statement_id');
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'ledger_account_id');
    }

    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class, 'reconciliation_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(BankReconciliationAdjustment::class, 'reconciliation_id');
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

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeLocked($query)
    {
        return $query->where('status', 'locked');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'in_progress', 'reopened']);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isReopened(): bool
    {
        return $this->status === 'reopened';
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['draft', 'in_progress', 'reopened']);
    }

    public function canBeEdited(): bool
    {
        return $this->isActive() && ! $this->isLocked();
    }

    public function canBeCompleted(): bool
    {
        return $this->isActive() && $this->variance == 0;
    }

    public function canBeLocked(): bool
    {
        return $this->isCompleted();
    }

    public function canBeReopened(): bool
    {
        return $this->isLocked();
    }

    public function startProgress(User $user): void
    {
        $this->update([
            'status' => 'in_progress',
            'started_by' => $user->id,
            'started_at' => now(),
        ]);
    }

    public function complete(User $user): void
    {
        if (! $this->canBeCompleted()) {
            throw new \InvalidArgumentException('Reconciliation cannot be completed. Variance must be zero.');
        }

        $this->update([
            'status' => 'completed',
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);
    }

    public function lock(): void
    {
        if (! $this->canBeLocked()) {
            throw new \InvalidArgumentException('Reconciliation must be completed before it can be locked.');
        }

        $this->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);
    }

    public function reopen(string $reason): void
    {
        if (! $this->canBeReopened()) {
            throw new \InvalidArgumentException('Only locked reconciliations can be reopened.');
        }

        $this->update([
            'status' => 'reopened',
            'notes' => ($this->notes ? $this->notes."\n\n" : '').'Reopened: '.$reason,
        ]);
    }

    public function recalculateVariance(): void
    {
        $service = new \Modules\Ledger\Services\BankReconciliationSummaryService;
        $variance = $service->calculateVariance($this);

        $this->update([
            'unmatched_statement_total' => $variance['unmatched_statement_total'],
            'unmatched_internal_total' => $variance['unmatched_internal_total'],
            'variance' => $variance['variance'],
        ]);
    }

    public function getSummaryStats(): array
    {
        $service = new \Modules\Ledger\Services\BankReconciliationSummaryService;

        return $service->getSummaryStats($this);
    }

    public function getBreakdown(): array
    {
        $service = new \Modules\Ledger\Services\BankReconciliationSummaryService;

        return $service->getBreakdown($this);
    }

    public function getFormattedVarianceAttribute(): string
    {
        $prefix = $this->variance < 0 ? '-' : '';

        return $prefix.number_format(abs($this->variance), 2);
    }

    public function getVarianceStatusAttribute(): string
    {
        if ($this->variance == 0) {
            return 'balanced';
        } elseif ($this->variance > 0) {
            return 'positive';
        } else {
            return 'negative';
        }
    }

    public function getPercentCompleteAttribute(): int
    {
        if ($this->statement) {
            $totalLines = $this->statement->bankStatementLines()->count();
            $matchedLines = $this->matches()->count();

            return $totalLines > 0 ? intval(($matchedLines / $totalLines) * 100) : 0;
        }

        return 0;
    }

    public function getActiveDurationAttribute(): ?string
    {
        if ($this->started_at) {
            $end = $this->completed_at ?? now();

            return $end->diffForHumans($this->started_at, true);
        }

        return null;
    }
}
