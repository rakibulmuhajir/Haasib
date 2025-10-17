<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;
use Modules\Ledger\Services\PeriodCloseService;

class UpdatePeriodCloseTemplateAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Update a period close template
     *
     * @throws ValidationException
     */
    public function execute(string $templateId, array $updateData, User $user): PeriodCloseTemplate
    {
        // Validate inputs
        $this->validateUpdateRequest($templateId, $updateData, $user);

        return DB::transaction(function () use ($templateId, $updateData, $user) {
            // Get template
            $template = PeriodCloseTemplate::where('id', $templateId)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            // Store original values for audit
            $originalValues = $template->only(['name', 'description', 'frequency', 'is_default', 'active']);

            // Handle setting as default - unset other defaults if this is being set as default
            if (isset($updateData['is_default']) && $updateData['is_default'] === true) {
                PeriodCloseTemplate::where('company_id', $user->current_company_id)
                    ->where('id', '!=', $templateId)
                    ->update(['is_default' => false]);
            }

            // Update template basic info
            $updateFields = [
                'name' => $updateData['name'] ?? $template->name,
                'description' => $updateData['description'] ?? $template->description,
                'frequency' => $updateData['frequency'] ?? $template->frequency,
                'is_default' => $updateData['is_default'] ?? $template->is_default,
                'active' => $updateData['active'] ?? $template->active,
                'updated_by' => $user->id,
            ];

            $template->update($updateFields);

            // Handle tasks update if provided
            if (isset($updateData['tasks']) && is_array($updateData['tasks'])) {
                $this->updateTemplateTasks($template, $updateData['tasks'], $user);
            }

            // Dispatch events for audit and monitoring
            $changes = array_diff_assoc($updateFields, $originalValues);
            if (! empty($changes) || isset($updateData['tasks'])) {
                event(new \Modules\Ledger\Events\PeriodCloseTemplateUpdated(
                    $template,
                    $changes,
                    isset($updateData['tasks']),
                    $user
                ));
            }

            // Refresh template to include updated relationships
            $template->refresh();

            return $template;
        });
    }

    /**
     * Validate the update request
     */
    private function validateUpdateRequest(string $templateId, array $updateData, User $user): void
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

        // Check user permissions
        if (! $user->can('period-close.templates.manage')) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to update period close templates',
            ]);
        }

        // Validate update data
        $this->validateUpdateData($updateData, $templateId, $user->current_company_id);
    }

    /**
     * Validate template update data
     */
    private function validateUpdateData(array $updateData, string $excludeTemplateId, string $companyId): void
    {
        $validator = validator($updateData, [
            'name' => 'sometimes|string|max:255|unique:ledger.period_close_templates,name,'.$excludeTemplateId.',id,company_id,'.$companyId,
            'description' => 'sometimes|string|nullable|max:1000',
            'frequency' => 'sometimes|in:monthly,quarterly,yearly,custom',
            'is_default' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
            'tasks' => 'sometimes|array',
            'tasks.*.code' => 'required_with:tasks|string|max:100',
            'tasks.*.title' => 'required_with:tasks|string|max:255',
            'tasks.*.category' => 'required_with:tasks|in:trial_balance,reconciliations,compliance,reporting,adjustments,other',
            'tasks.*.sequence' => 'required_with:tasks|integer|min:1',
            'tasks.*.is_required' => 'required_with:tasks|boolean',
            'tasks.*.default_notes' => 'sometimes|string|nullable|max:1000',
        ], [
            'name.unique' => 'A template with this name already exists for your company.',
            'tasks.*.category.in' => 'Invalid task category. Must be one of: trial_balance, reconciliations, compliance, reporting, adjustments, other.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // Additional validation for tasks if provided
        if (isset($updateData['tasks'])) {
            $this->validateTaskSequences($updateData['tasks']);
        }
    }

    /**
     * Update template tasks
     */
    private function updateTemplateTasks(PeriodCloseTemplate $template, array $tasks, User $user): void
    {
        // Get existing task IDs
        $existingTaskIds = $template->templateTasks->pluck('id')->toArray();
        $incomingTaskIds = [];

        foreach ($tasks as $taskData) {
            // Check if this is an existing task or new
            if (isset($taskData['id']) && in_array($taskData['id'], $existingTaskIds)) {
                // Update existing task
                $existingTask = $template->templateTasks()->find($taskData['id']);
                if ($existingTask) {
                    $existingTask->update([
                        'code' => $taskData['code'],
                        'title' => $taskData['title'],
                        'category' => $taskData['category'],
                        'sequence' => $taskData['sequence'],
                        'is_required' => $taskData['is_required'],
                        'default_notes' => $taskData['default_notes'] ?? null,
                        'updated_by' => $user->id,
                    ]);
                    $incomingTaskIds[] = $existingTask->id;
                }
            } else {
                // Create new task
                $newTask = $template->templateTasks()->create([
                    'code' => $taskData['code'],
                    'title' => $taskData['title'],
                    'category' => $taskData['category'],
                    'sequence' => $taskData['sequence'],
                    'is_required' => $taskData['is_required'],
                    'default_notes' => $taskData['default_notes'] ?? null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
                $incomingTaskIds[] = $newTask->id;
            }
        }

        // Delete tasks that were not included in the update
        $tasksToDelete = array_diff($existingTaskIds, $incomingTaskIds);
        if (! empty($tasksToDelete)) {
            $template->templateTasks()->whereIn('id', $tasksToDelete)->delete();
        }

        // Reorder tasks to ensure proper sequence
        $this->reorderTasks($template);
    }

    /**
     * Validate task sequences are unique
     */
    private function validateTaskSequences(array $tasks): void
    {
        $sequences = array_column($tasks, 'sequence');
        $uniqueSequences = array_unique($sequences);

        if (count($sequences) !== count($uniqueSequences)) {
            throw ValidationException::withMessages([
                'tasks' => 'Task sequences must be unique within a template.',
            ]);
        }

        // Check for gaps in sequences
        sort($sequences);
        $expectedSequences = range(1, count($sequences));

        if ($sequences !== $expectedSequences) {
            throw ValidationException::withMessages([
                'tasks' => 'Task sequences must be consecutive starting from 1 (e.g., 1, 2, 3, ...).',
            ]);
        }
    }

    /**
     * Reorder tasks to ensure proper sequence
     */
    private function reorderTasks(PeriodCloseTemplate $template): void
    {
        $tasks = $template->templateTasks()
            ->orderBy('sequence')
            ->orderBy('created_at')
            ->get();

        $sequence = 1;
        foreach ($tasks as $task) {
            $task->update(['sequence' => $sequence++]);
        }
    }
}
