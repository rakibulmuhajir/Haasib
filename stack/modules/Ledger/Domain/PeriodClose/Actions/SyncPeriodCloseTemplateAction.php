<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;
use Modules\Ledger\Services\PeriodCloseService;

class SyncPeriodCloseTemplateAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Sync template tasks to a period close
     *
     * @throws ValidationException
     */
    public function execute(string $templateId, string $periodCloseId, User $user): array
    {
        // Validate inputs
        $this->validateSyncRequest($templateId, $periodCloseId, $user);

        return DB::transaction(function () use ($templateId, $periodCloseId, $user) {
            // Get template and period close
            $template = PeriodCloseTemplate::where('id', $templateId)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            $periodClose = PeriodClose::where('id', $periodCloseId)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            // Check if period close is in a valid state for syncing
            $this->validatePeriodCloseState($periodClose);

            // Get existing template tasks to sync
            $templateTasks = $template->templateTasks()
                ->orderBy('sequence')
                ->get();

            if ($templateTasks->isEmpty()) {
                throw ValidationException::withMessages([
                    'template' => 'Template has no tasks to sync',
                ]);
            }

            // Remove existing tasks that came from templates (keep manual tasks)
            $existingTemplateTaskIds = $periodClose->tasks()
                ->whereNotNull('template_task_id')
                ->pluck('template_task_id')
                ->toArray();

            // Delete tasks that were previously synced but are no longer in template
            $periodClose->tasks()
                ->whereIn('template_task_id', $existingTemplateTaskIds)
                ->whereNotIn('template_task_id', $templateTasks->pluck('id'))
                ->delete();

            // Sync or create tasks from template
            $syncedCount = 0;
            foreach ($templateTasks as $templateTask) {
                // Check if task already exists
                $existingTask = $periodClose->tasks()
                    ->where('template_task_id', $templateTask->id)
                    ->first();

                if ($existingTask) {
                    // Update existing task
                    $existingTask->update([
                        'title' => $templateTask->title,
                        'category' => $templateTask->category,
                        'sequence' => $templateTask->sequence,
                        'is_required' => $templateTask->is_required,
                        'updated_by' => $user->id,
                    ]);
                    $syncedCount++;
                } else {
                    // Create new task
                    PeriodCloseTask::create([
                        'period_close_id' => $periodClose->id,
                        'template_task_id' => $templateTask->id,
                        'code' => $templateTask->code,
                        'title' => $templateTask->title,
                        'category' => $templateTask->category,
                        'sequence' => $templateTask->sequence,
                        'is_required' => $templateTask->is_required,
                        'status' => 'pending',
                        'notes' => $templateTask->default_notes,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                    $syncedCount++;
                }
            }

            // Reorder tasks to ensure proper sequence
            $this->reorderTasks($periodClose);

            // Dispatch events for audit and monitoring
            event(new \Modules\Ledger\Events\PeriodCloseTemplateSynced(
                $periodClose,
                $template,
                $syncedCount,
                $user
            ));

            return [
                'synced_tasks_count' => $syncedCount,
                'template_id' => $template->id,
                'period_close_id' => $periodClose->id,
                'template_name' => $template->name,
                'period_close_name' => $periodClose->name,
            ];
        });
    }

    /**
     * Validate the sync request
     */
    private function validateSyncRequest(string $templateId, string $periodCloseId, User $user): void
    {
        // Check if template exists and user has access
        $template = PeriodCloseTemplate::where('id', $templateId)
            ->where('company_id', $user->current_company_id)
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'template_id' => 'Template not found or access denied',
            ]);
        }

        // Check if period close exists and user has access
        $periodClose = PeriodClose::where('id', $periodCloseId)
            ->where('company_id', $user->current_company_id)
            ->first();

        if (! $periodClose) {
            throw ValidationException::withMessages([
                'period_close_id' => 'Period close not found or access denied',
            ]);
        }

        // Check user permissions
        if (! $user->can('period-close.view') || ! $user->can('period-close.templates.manage')) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to sync templates to period closes',
            ]);
        }
    }

    /**
     * Validate that period close is in a valid state for syncing
     */
    private function validatePeriodCloseState(PeriodClose $periodClose): void
    {
        $validStates = ['draft', 'in_review', 'awaiting_approval', 'reopened'];

        if (! in_array($periodClose->status, $validStates)) {
            throw ValidationException::withMessages([
                'period_close' => 'Cannot sync template to period close in "'.$periodClose->status.'" state. '.
                                 'Valid states are: '.implode(', ', $validStates),
            ]);
        }
    }

    /**
     * Reorder tasks to ensure proper sequence after sync
     */
    private function reorderTasks(PeriodClose $periodClose): void
    {
        $tasks = $periodClose->tasks()
            ->orderBy('sequence')
            ->orderBy('created_at')
            ->get();

        $sequence = 1;
        foreach ($tasks as $task) {
            $task->update(['sequence' => $sequence++]);
        }
    }
}
