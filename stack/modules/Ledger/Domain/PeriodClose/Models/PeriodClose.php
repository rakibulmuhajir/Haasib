<?php

namespace Modules\Ledger\Domain\PeriodClose\Models;

use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodClose extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ledger.period_closes';

    protected $fillable = [
        'company_id',
        'accounting_period_id',
        'template_id',
        'status',
        'trial_balance_variance',
        'unposted_documents',
        'adjusting_entry_id',
        'closing_summary',
        'started_by',
        'started_at',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'trial_balance_variance' => 'decimal:2',
            'unposted_documents' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
            'company_id' => 'string',
            'accounting_period_id' => 'string',
            'template_id' => 'string',
            'adjusting_entry_id' => 'string',
            'started_by' => 'string',
            'closed_by' => 'string',
            'reopened_by' => 'string',
        ];
    }

    /**
     * Get the company that owns the period close.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the accounting period for this close.
     */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    /**
     * Get the template used for this close.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PeriodCloseTemplate::class);
    }

    /**
     * Get the user who started the close.
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * Get the user who closed the period.
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the user who reopened the period.
     */
    public function reopener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * Get the adjusting journal entry for this close.
     */
    public function adjustingEntry(): BelongsTo
    {
        return $this->belongsTo(\App\Models\JournalEntry::class, 'adjusting_entry_id');
    }

    /**
     * Get the tasks for this period close.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTask::class)->orderBy('sequence');
    }

    /**
     * Get the required tasks for this period close.
     */
    public function requiredTasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTask::class)
            ->where('is_required', true)
            ->orderBy('sequence');
    }

    /**
     * Get the completed tasks for this period close.
     */
    public function completedTasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTask::class)
            ->where('status', 'completed')
            ->orderBy('sequence');
    }

    /**
     * Get the blocked tasks for this period close.
     */
    public function blockedTasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTask::class)
            ->where('status', 'blocked')
            ->orderBy('sequence');
    }

    /**
     * Check if the close is in review.
     */
    public function isInReview(): bool
    {
        return $this->status === 'in_review';
    }

    /**
     * Check if the close is awaiting approval.
     */
    public function isAwaitingApproval(): bool
    {
        return $this->status === 'awaiting_approval';
    }

    /**
     * Check if the close is locked.
     */
    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    /**
     * Check if the close is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Check if the close has been reopened.
     */
    public function isReopened(): bool
    {
        return $this->status === 'reopened';
    }

    /**
     * Check if all required tasks are completed.
     */
    public function allRequiredTasksCompleted(): bool
    {
        return $this->requiredTasks()
            ->where('status', '!=', 'completed')
            ->where('status', '!=', 'waived')
            ->count() === 0;
    }

    /**
     * Get the completion percentage of tasks.
     */
    public function getCompletionPercentage(): float
    {
        $totalTasks = $this->tasks()->count();
        if ($totalTasks === 0) {
            return 0;
        }

        $completedTasks = $this->tasks()
            ->whereIn('status', ['completed', 'waived'])
            ->count();

        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Check if trial balance is balanced.
     */
    public function isTrialBalanceBalanced(): bool
    {
        return $this->trial_balance_variance == 0;
    }

    /**
     * Check if there are unposted documents.
     */
    public function hasUnpostedDocuments(): bool
    {
        return ! empty($this->unposted_documents);
    }

    /**
     * Check if the close can proceed to the next step.
     */
    public function canProceed(): bool
    {
        return $this->allRequiredTasksCompleted()
            && $this->isTrialBalanceBalanced()
            && ! $this->hasUnpostedDocuments();
    }

    /**
     * Start the review process.
     */
    public function startReview(User $user): void
    {
        $this->status = 'in_review';
        $this->started_by = $user->id;
        $this->started_at = now();
        $this->save();
    }

    /**
     * Submit for approval.
     */
    public function submitForApproval(): void
    {
        $this->status = 'awaiting_approval';
        $this->save();
    }

    /**
     * Lock the period.
     */
    public function lock(User $user, ?string $summary = null): void
    {
        $this->status = 'locked';
        $this->closing_summary = $summary;
        $this->save();
    }

    /**
     * Complete the close.
     */
    public function complete(User $user, ?string $summary = null): void
    {
        $this->status = 'closed';
        $this->closed_by = $user->id;
        $this->closed_at = now();
        if ($summary) {
            $this->closing_summary = $summary;
        }
        $this->save();

        // Also update the accounting period
        $this->accountingPeriod->close($user, $summary);
    }

    /**
     * Reopen the close.
     */
    public function reopen(User $user): void
    {
        $this->status = 'reopened';
        $this->reopened_by = $user->id;
        $this->reopened_at = now();
        $this->save();

        // Also update the accounting period
        $this->accountingPeriod->reopen($user);
    }

    /**
     * Get the validation summary.
     */
    public function getValidationSummary(): array
    {
        return [
            'trial_balance_variance' => $this->trial_balance_variance,
            'is_balanced' => $this->isTrialBalanceBalanced(),
            'unposted_documents' => $this->unposted_documents ?? [],
            'has_unposted' => $this->hasUnpostedDocuments(),
            'required_tasks_completed' => $this->allRequiredTasksCompleted(),
            'completion_percentage' => $this->getCompletionPercentage(),
            'can_proceed' => $this->canProceed(),
        ];
    }

    /**
     * Scope a query to only include closes with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include closes for a specific company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope a query to only include active closes (not closed or reopened).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_review', 'awaiting_approval', 'locked']);
    }

    /**
     * Scope a query to only include completed closes.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'closed');
    }
}
