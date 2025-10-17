<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\Ledger\Domain\PeriodClose\Exceptions\PeriodCloseException;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodClose;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTask;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplate;
use Modules\Ledger\Domain\PeriodClose\Models\PeriodCloseTemplateTask;
use Modules\Ledger\Services\PeriodCloseService;

class StartPeriodCloseAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Start a period close workflow for the given accounting period.
     */
    public function execute(AccountingPeriod $period, User $startedBy, ?string $notes = null): PeriodClose
    {
        // Validate that the period can be closed
        $this->validatePeriodCanClose($period);

        // Check if period close already exists
        $existingClose = $period->periodClose()->first();
        if ($existingClose) {
            throw new PeriodCloseException('Period close already in progress for this period');
        }

        try {
            DB::beginTransaction();

            // Update accounting period status to closing
            $period->startClosing();

            // Create or update period close record
            $periodClose = PeriodClose::updateOrCreate(
                ['accounting_period_id' => $period->id],
                [
                    'company_id' => $period->company_id,
                    'status' => 'in_review',
                    'started_by' => $startedBy->id,
                    'started_at' => now(),
                    'closing_summary' => $notes,
                ]
            );

            // Determine which template to use
            $template = $this->getTemplateForPeriod($period);
            if ($template) {
                $periodClose->template_id = $template->id;
                $periodClose->save();
                $this->createTasksFromTemplate($periodClose, $template);
            } else {
                $this->createTasksFromDefaultTemplate($periodClose);
            }

            // Emit audit event
            Event::dispatch('period-close.started', [
                'period_close_id' => $periodClose->id,
                'accounting_period_id' => $period->id,
                'company_id' => $period->company_id,
                'user_id' => $startedBy->id,
                'template_id' => $template?->id,
                'notes' => $notes,
                'tasks_created' => $periodClose->tasks->count(),
            ]);

            // Log the action
            Log::info('Period close started', [
                'period_id' => $period->id,
                'user_id' => $startedBy->id,
                'company_id' => $period->company_id,
                'period_close_id' => $periodClose->id,
                'template_used' => $template?->name ?? 'default',
            ]);

            DB::commit();

            return $periodClose->fresh(['tasks', 'template']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to start period close', [
                'period_id' => $period->id,
                'user_id' => $startedBy->id,
                'company_id' => $period->company_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new PeriodCloseException('Failed to start period close: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate that the period can be closed.
     */
    private function validatePeriodCanClose(AccountingPeriod $period): void
    {
        if (! $period->canBeClosed()) {
            throw new PeriodCloseException("Period {$period->id} cannot be closed. Current status: {$period->status}");
        }

        // Additional business logic validations
        if ($period->fiscal_year->status !== 'active') {
            throw new PeriodCloseException('Cannot close a period in a non-active fiscal year');
        }

        // Check if there are any unclosed previous periods
        $unclosedPreviousPeriods = AccountingPeriod::where('fiscal_year_id', $period->fiscal_year_id)
            ->where('company_id', $period->company_id)
            ->where('end_date', '<', $period->end_date)
            ->where('status', '!=', 'closed')
            ->count();

        if ($unclosedPreviousPeriods > 0) {
            throw new PeriodCloseException('Previous periods must be closed before this period can be closed');
        }
    }

    /**
     * Get the appropriate template for the period.
     */
    private function getTemplateForPeriod(AccountingPeriod $period): ?PeriodCloseTemplate
    {
        // First try to get company-specific default template
        $template = $this->periodCloseService->getDefaultTemplate($period->company_id, 'monthly');

        if ($template) {
            return $template;
        }

        // If no company template, use the system default template
        return PeriodCloseTemplate::where('company_id', null)
            ->where('frequency', 'monthly')
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }

    /**
     * Create tasks from a template.
     */
    private function createTasksFromTemplate(PeriodClose $periodClose, PeriodCloseTemplate $template): void
    {
        foreach ($template->tasks()->orderBy('sequence')->get() as $templateTask) {
            $templateTask->createPeriodCloseTask($periodClose->id);
        }

        Log::debug('Created tasks from template', [
            'period_close_id' => $periodClose->id,
            'template_id' => $template->id,
            'tasks_count' => $template->tasks()->count(),
        ]);
    }

    /**
     * Create tasks from the default template.
     */
    private function createTasksFromDefaultTemplate(PeriodClose $periodClose): void
    {
        $defaultTasks = PeriodCloseTemplateTask::getDefaultMonthlyTasks();
        $sequence = 1;

        foreach ($defaultTasks as $taskData) {
            PeriodCloseTask::create([
                'period_close_id' => $periodClose->id,
                'code' => $taskData['code'],
                'title' => $taskData['title'],
                'category' => $taskData['category'],
                'sequence' => $sequence++,
                'is_required' => $taskData['is_required'],
                'notes' => $taskData['default_notes'] ?? null,
            ]);
        }

        Log::debug('Created default tasks', [
            'period_close_id' => $periodClose->id,
            'tasks_count' => count($defaultTasks),
        ]);
    }
}
