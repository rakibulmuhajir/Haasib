<?php

namespace Modules\Ledger\Domain\PeriodClose\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodCloseTemplateTask extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ledger.period_close_template_tasks';

    protected $fillable = [
        'template_id',
        'code',
        'title',
        'category',
        'sequence',
        'is_required',
        'default_notes',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sequence' => 'integer',
            'template_id' => 'string',
        ];
    }

    /**
     * Get the template that owns the task.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PeriodCloseTemplate::class);
    }

    /**
     * Get the period close tasks created from this template task.
     */
    public function periodCloseTasks(): HasMany
    {
        return $this->hasMany(PeriodCloseTask::class, 'template_task_id');
    }

    /**
     * Check if the task is required.
     */
    public function isRequired(): bool
    {
        return $this->is_required;
    }

    /**
     * Check if the task is optional.
     */
    public function isOptional(): bool
    {
        return ! $this->is_required;
    }

    /**
     * Mark the task as required.
     */
    public function markAsRequired(): void
    {
        $this->is_required = true;
        $this->save();
    }

    /**
     * Mark the task as optional.
     */
    public function markAsOptional(): void
    {
        $this->is_required = false;
        $this->save();
    }

    /**
     * Get the category display name.
     */
    public function getCategoryDisplay(): string
    {
        return match ($this->category) {
            'trial_balance' => 'Trial Balance',
            'subledger' => 'Subledger',
            'compliance' => 'Compliance',
            'reporting' => 'Reporting',
            'misc' => 'Miscellaneous',
            default => ucfirst($this->category),
        };
    }

    /**
     * Get the default notes for display.
     */
    public function getDisplayNotes(): string
    {
        return $this->default_notes ?? '';
    }

    /**
     * Check if the task has default notes.
     */
    public function hasDefaultNotes(): bool
    {
        return ! empty($this->default_notes);
    }

    /**
     * Create a period close task from this template task.
     */
    public function createPeriodCloseTask(string $periodCloseId): PeriodCloseTask
    {
        return PeriodCloseTask::create([
            'period_close_id' => $periodCloseId,
            'template_task_id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'category' => $this->category,
            'sequence' => $this->sequence,
            'is_required' => $this->is_required,
            'notes' => $this->default_notes,
        ]);
    }

    /**
     * Update the task details.
     */
    public function updateDetails(array $data): bool
    {
        $allowedFields = ['title', 'category', 'sequence', 'is_required', 'default_notes'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $this->{$field} = $data[$field];
            }
        }

        return $this->save();
    }

    /**
     * Move the task to a new sequence position.
     */
    public function moveToSequence(int $newSequence): bool
    {
        if ($newSequence < 1) {
            return false;
        }

        $oldSequence = $this->sequence;

        // If moving to a higher sequence, decrement tasks in between
        if ($newSequence > $oldSequence) {
            $this->template->tasks()
                ->where('sequence', '>', $oldSequence)
                ->where('sequence', '<=', $newSequence)
                ->where('id', '!=', $this->id)
                ->decrement('sequence');
        }
        // If moving to a lower sequence, increment tasks in between
        elseif ($newSequence < $oldSequence) {
            $this->template->tasks()
                ->where('sequence', '>=', $newSequence)
                ->where('sequence', '<', $oldSequence)
                ->where('id', '!=', $this->id)
                ->increment('sequence');
        }

        $this->sequence = $newSequence;

        return $this->save();
    }

    /**
     * Get the validation rules for this task.
     */
    public function getValidationRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:trial_balance,subledger,compliance,reporting,misc'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_required' => ['boolean'],
            'default_notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Scope a query to only include required tasks.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope a query to only include optional tasks.
     */
    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }

    /**
     * Scope a query to only include tasks in a specific category.
     */
    public function scopeInCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope a query to order by sequence.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }

    /**
     * Get a standard set of default tasks for a monthly close.
     */
    public static function getDefaultMonthlyTasks(): array
    {
        return [
            [
                'code' => 'tb-validate',
                'title' => 'Validate Trial Balance',
                'category' => 'trial_balance',
                'sequence' => 1,
                'is_required' => true,
                'default_notes' => 'Ensure trial balance is balanced and accounts reconcile',
            ],
            [
                'code' => 'subledger-ar',
                'title' => 'Reconcile Accounts Receivable',
                'category' => 'subledger',
                'sequence' => 2,
                'is_required' => true,
                'default_notes' => 'Verify AR aging reports match general ledger',
            ],
            [
                'code' => 'subledger-ap',
                'title' => 'Reconcile Accounts Payable',
                'category' => 'subledger',
                'sequence' => 3,
                'is_required' => true,
                'default_notes' => 'Verify AP aging reports match general ledger',
            ],
            [
                'code' => 'bank-reconcile',
                'title' => 'Bank Reconciliation',
                'category' => 'compliance',
                'sequence' => 4,
                'is_required' => true,
                'default_notes' => 'Reconcile bank statements to cash accounts',
            ],
            [
                'code' => 'management-reports',
                'title' => 'Generate Management Reports',
                'category' => 'reporting',
                'sequence' => 5,
                'is_required' => true,
                'default_notes' => 'Prepare income statement and balance sheet',
            ],
            [
                'code' => 'tax-review',
                'title' => 'Tax Compliance Review',
                'category' => 'compliance',
                'sequence' => 6,
                'is_required' => false,
                'default_notes' => 'Review tax calculations and filings',
            ],
        ];
    }

    /**
     * Get a standard set of default tasks for a quarterly close.
     */
    public static function getDefaultQuarterlyTasks(): array
    {
        return array_merge(self::getDefaultMonthlyTasks(), [
            [
                'code' => 'financial-statements',
                'title' => 'Prepare Quarterly Financial Statements',
                'category' => 'reporting',
                'sequence' => 7,
                'is_required' => true,
                'default_notes' => 'Complete Q3 financial statement package',
            ],
            [
                'code' => 'flux-analysis',
                'title' => 'Statement of Cash Flows',
                'category' => 'reporting',
                'sequence' => 8,
                'is_required' => true,
                'default_notes' => 'Prepare quarterly cash flow statement',
            ],
        ]);
    }

    /**
     * Get a standard set of default tasks for an annual close.
     */
    public static function getDefaultAnnualTasks(): array
    {
        return array_merge(self::getDefaultQuarterlyTasks(), [
            [
                'code' => 'year-end-adjustments',
                'title' => 'Year-End Adjusting Entries',
                'category' => 'trial_balance',
                'sequence' => 9,
                'is_required' => true,
                'default_notes' => 'Record depreciation, amortization, and other year-end adjustments',
            ],
            [
                'code' => 'audit-preparation',
                'title' => 'Audit Preparation',
                'category' => 'compliance',
                'sequence' => 10,
                'is_required' => false,
                'default_notes' => 'Gather documentation for external audit',
            ],
            [
                'code' => 'tax-return',
                'title' => 'Annual Tax Return',
                'category' => 'compliance',
                'sequence' => 11,
                'is_required' => true,
                'default_notes' => 'Prepare and file annual tax returns',
            ],
        ]);
    }

    /**
     * Create default tasks for a template based on frequency.
     */
    public static function createDefaultTasksForTemplate(PeriodCloseTemplate $template): void
    {
        $tasks = match ($template->frequency) {
            'monthly' => self::getDefaultMonthlyTasks(),
            'quarterly' => self::getDefaultQuarterlyTasks(),
            'annual' => self::getDefaultAnnualTasks(),
            default => self::getDefaultMonthlyTasks(),
        };

        foreach ($tasks as $taskData) {
            $template->tasks()->create($taskData);
        }
    }
}
