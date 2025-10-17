<?php

namespace Modules\Ledger\Domain\PeriodClose\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeriodCloseTask extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ledger.period_close_tasks';

    protected $fillable = [
        'period_close_id',
        'template_task_id',
        'code',
        'title',
        'category',
        'sequence',
        'status',
        'is_required',
        'completed_by',
        'completed_at',
        'notes',
        'attachment_manifest',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'attachment_manifest' => 'array',
            'completed_at' => 'datetime',
            'period_close_id' => 'string',
            'template_task_id' => 'string',
            'completed_by' => 'string',
        ];
    }

    /**
     * Get the period close that owns the task.
     */
    public function periodClose(): BelongsTo
    {
        return $this->belongsTo(PeriodClose::class);
    }

    /**
     * Get the template task that this task was created from.
     */
    public function templateTask(): BelongsTo
    {
        return $this->belongsTo(PeriodCloseTemplateTask::class);
    }

    /**
     * Get the user who completed the task.
     */
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Check if the task is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the task is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if the task is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the task is waived.
     */
    public function isWaived(): bool
    {
        return $this->status === 'waived';
    }

    /**
     * Check if the task can be marked as completed.
     */
    public function canBeCompleted(): bool
    {
        return in_array($this->status, ['pending', 'in_progress', 'blocked']);
    }

    /**
     * Check if the task can be marked as waived.
     */
    public function canBeWaived(): bool
    {
        return $this->is_required && in_array($this->status, ['pending', 'in_progress', 'blocked']);
    }

    /**
     * Check if the task is finished (completed or waived).
     */
    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'waived']);
    }

    /**
     * Mark the task as in progress.
     */
    public function markInProgress(): void
    {
        $this->status = 'in_progress';
        $this->save();
    }

    /**
     * Mark the task as completed.
     */
    public function markCompleted(User $user, ?string $notes = null, ?array $attachments = null): void
    {
        $this->status = 'completed';
        $this->completed_by = $user->id;
        $this->completed_at = now();

        if ($notes) {
            $this->notes = $notes;
        }

        if ($attachments) {
            $this->attachment_manifest = $attachments;
        }

        $this->save();
    }

    /**
     * Mark the task as blocked.
     */
    public function markBlocked(string $reason): void
    {
        $this->status = 'blocked';
        $this->notes = $reason;
        $this->save();
    }

    /**
     * Mark the task as waived.
     */
    public function markWaived(string $reason): void
    {
        $this->status = 'waived';
        $this->notes = $reason;
        $this->save();
    }

    /**
     * Reset the task to pending.
     */
    public function resetToPending(): void
    {
        $this->status = 'pending';
        $this->completed_by = null;
        $this->completed_at = null;
        $this->save();
    }

    /**
     * Get the display status with proper formatting.
     */
    public function getDisplayStatus(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'blocked' => 'Blocked',
            'completed' => 'Completed',
            'waived' => 'Waived',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the status color for UI display.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'secondary',
            'in_progress' => 'info',
            'blocked' => 'danger',
            'completed' => 'success',
            'waived' => 'warning',
            default => 'secondary',
        };
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
     * Check if the task has attachments.
     */
    public function hasAttachments(): bool
    {
        return ! empty($this->attachment_manifest);
    }

    /**
     * Get the number of attachments.
     */
    public function getAttachmentCount(): int
    {
        return count($this->attachment_manifest ?? []);
    }

    /**
     * Add an attachment to the manifest.
     */
    public function addAttachment(string $documentId, string $label): void
    {
        $attachments = $this->attachment_manifest ?? [];
        $attachments[] = [
            'document_id' => $documentId,
            'label' => $label,
            'added_at' => now()->toISOString(),
        ];

        $this->attachment_manifest = $attachments;
        $this->save();
    }

    /**
     * Remove an attachment from the manifest.
     */
    public function removeAttachment(string $documentId): bool
    {
        if (! $this->attachment_manifest) {
            return false;
        }

        $attachments = $this->attachment_manifest;
        $initialCount = count($attachments);

        $attachments = array_filter($attachments, function ($attachment) use ($documentId) {
            return $attachment['document_id'] !== $documentId;
        });

        if (count($attachments) < $initialCount) {
            $this->attachment_manifest = array_values($attachments);
            $this->save();

            return true;
        }

        return false;
    }

    /**
     * Get the completion summary.
     */
    public function getCompletionSummary(): array
    {
        return [
            'is_completed' => $this->isCompleted(),
            'is_waived' => $this->isWaived(),
            'is_finished' => $this->isFinished(),
            'completed_by' => $this->completed_by,
            'completed_at' => $this->completed_at,
            'has_notes' => ! empty($this->notes),
            'has_attachments' => $this->hasAttachments(),
            'attachment_count' => $this->getAttachmentCount(),
        ];
    }

    /**
     * Scope a query to only include tasks with a specific status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
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
     * Scope a query to only include finished tasks (completed or waived).
     */
    public function scopeFinished($query)
    {
        return $query->whereIn('status', ['completed', 'waived']);
    }

    /**
     * Scope a query to only include unfinished tasks.
     */
    public function scopeUnfinished($query)
    {
        return $query->whereNotIn('status', ['completed', 'waived']);
    }

    /**
     * Scope a query to only include blocked tasks.
     */
    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }
}
