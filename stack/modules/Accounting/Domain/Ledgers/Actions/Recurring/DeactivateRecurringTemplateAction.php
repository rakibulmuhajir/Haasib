<?php

namespace Modules\Accounting\Domain\Ledgers\Actions\Recurring;

use App\Models\RecurringJournalTemplate;

class DeactivateRecurringTemplateAction
{
    /**
     * Deactivate a recurring template.
     */
    public function execute(RecurringJournalTemplate $template, ?string $reason = null): RecurringJournalTemplate
    {
        $template->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
            'next_generation_date' => null,
        ]);

        return $template->fresh();
    }

    /**
     * Reactivate a deactivated recurring template.
     */
    public function reactivate(RecurringJournalTemplate $template): RecurringJournalTemplate
    {
        if (! $template->is_active) {
            // Calculate new next generation date from today
            $nextDate = $this->calculateNextGenerationDate($template);

            $template->update([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivation_reason' => null,
                'next_generation_date' => $nextDate,
            ]);
        }

        return $template->fresh();
    }

    /**
     * Calculate next generation date for reactivation.
     */
    protected function calculateNextGenerationDate(RecurringJournalTemplate $template): string
    {
        $today = now();
        $frequency = $template->frequency;
        $interval = $template->interval ?? 1;

        return match ($frequency) {
            'daily' => $today->copy()->addDays($interval)->toDateString(),
            'weekly' => $today->copy()->addWeeks($interval)->toDateString(),
            'monthly' => $today->copy()->addMonths($interval)->toDateString(),
            'quarterly' => $today->copy()->addQuarters($interval)->toDateString(),
            'yearly' => $today->copy()->addYears($interval)->toDateString(),
            default => $today->copy()->addMonth()->toDateString(),
        };
    }

    /**
     * Bulk deactivate templates for a company.
     */
    public function bulkDeactivate(int $companyId, ?string $reason = null): int
    {
        return RecurringJournalTemplate::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => $reason,
                'next_generation_date' => null,
            ]);
    }

    /**
     * Auto-deactivate templates that have passed their end date.
     */
    public function deactivateExpiredTemplates(): int
    {
        return RecurringJournalTemplate::query()
            ->where('is_active', true)
            ->where('end_date', '<', now()->toDateString())
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => 'Template expired (end date passed)',
                'next_generation_date' => null,
            ]);
    }
}
