<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;
use Modules\Ledger\Services\PeriodCloseService;

class ArchivePeriodCloseTemplateAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Archive a period close template
     *
     * @throws ValidationException
     */
    public function execute(string $templateId, User $user): bool
    {
        // Validate inputs
        $this->validateArchiveRequest($templateId, $user);

        return DB::transaction(function () use ($templateId, $user) {
            // Get template
            $template = PeriodCloseTemplate::where('id', $templateId)
                ->where('company_id', $user->current_company_id)
                ->firstOrFail();

            // Check if template can be archived
            $this->validateTemplateCanBeArchived($template);

            // Archive the template (soft delete by setting active to false)
            $template->update([
                'active' => false,
                'updated_by' => $user->id,
                'archived_at' => now(),
                'archived_by' => $user->id,
            ]);

            // If this was a default template, unset default flag
            if ($template->is_default) {
                $template->update(['is_default' => false]);
            }

            // Dispatch events for audit and monitoring
            event(new \Modules\Ledger\Events\PeriodCloseTemplateArchived(
                $template,
                $user
            ));

            return true;
        });
    }

    /**
     * Validate the archive request
     */
    private function validateArchiveRequest(string $templateId, User $user): void
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

        // Check if template is already archived
        if (! $template->active) {
            throw ValidationException::withMessages([
                'template_id' => 'Template is already archived',
            ]);
        }

        // Check user permissions
        if (! $user->can('period-close.templates.manage')) {
            throw ValidationException::withMessages([
                'authorization' => 'You do not have permission to archive period close templates',
            ]);
        }
    }

    /**
     * Validate that the template can be archived
     */
    private function validateTemplateCanBeArchived(PeriodCloseTemplate $template): void
    {
        // Check if template is currently being used in active period closes
        $activeUsageCount = $template->periodCloseTasks()
            ->whereHas('periodClose', function ($query) {
                $query->whereIn('status', ['draft', 'in_review', 'awaiting_approval', 'reopened']);
            })
            ->count();

        if ($activeUsageCount > 0) {
            throw ValidationException::withMessages([
                'template' => 'Cannot archive template that is currently being used in active period closes. '.
                             'Found '.$activeUsageCount.' active usages.',
            ]);
        }

        // Additional business rule: Don't allow archiving if it's the only active template for the company
        $activeTemplateCount = PeriodCloseTemplate::where('company_id', $template->company_id)
            ->where('active', true)
            ->where('id', '!=', $template->id)
            ->count();

        if ($activeTemplateCount === 0) {
            throw ValidationException::withMessages([
                'template' => 'Cannot archive the only active template for your company. '.
                             'Create another template first.',
            ]);
        }
    }
}
