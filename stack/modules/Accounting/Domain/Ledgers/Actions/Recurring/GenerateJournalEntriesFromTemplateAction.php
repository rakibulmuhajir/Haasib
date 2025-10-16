<?php

namespace Modules\Accounting\Domain\Ledgers\Actions\Recurring;

use App\Models\JournalEntry;
use App\Models\JournalTransaction;
use App\Models\RecurringJournalTemplate;
use App\Models\RecurringJournalTemplateLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerateJournalEntriesFromTemplateAction
{
    /**
     * Generate journal entries from a recurring template.
     */
    public function execute(RecurringJournalTemplate $template): ?JournalEntry
    {
        // Check if template should generate today
        if (! $this->shouldGenerateToday($template)) {
            return null;
        }

        try {
            DB::beginTransaction();

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'company_id' => $template->company_id,
                'template_id' => $template->id,
                'description' => $this->generateEntryDescription($template),
                'date' => now()->toDateString(),
                'type' => 'recurring',
                'reference' => $this->generateReference($template),
                'status' => 'draft',
                'auto_generated' => true,
                'currency' => $template->currency,
            ]);

            // Create transactions from template lines
            $this->createTransactionsFromTemplate($journalEntry, $template);

            // Update template's next generation date
            $this->updateNextGenerationDate($template);

            DB::commit();

            return $journalEntry->load(['transactions.account']);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Check if the template should generate an entry today.
     */
    protected function shouldGenerateToday(RecurringJournalTemplate $template): bool
    {
        if (! $template->is_active) {
            return false;
        }

        $today = now()->toDateString();

        // Check if next generation date is today or in the past
        if ($template->next_generation_date > $today) {
            return false;
        }

        // Check if template has expired
        if ($template->end_date && $template->end_date < $today) {
            return false;
        }

        return true;
    }

    /**
     * Generate entry description based on template.
     */
    protected function generateEntryDescription(RecurringJournalTemplate $template): string
    {
        return sprintf(
            'Auto-generated: %s (%s)',
            $template->name,
            now()->format('Y-m-d')
        );
    }

    /**
     * Generate a unique reference for the entry.
     */
    protected function generateReference(RecurringJournalTemplate $template): string
    {
        return sprintf(
            'REC-%s-%s',
            str_pad($template->id, 6, '0', STR_PAD_LEFT),
            now()->format('Ymd')
        );
    }

    /**
     * Create transactions from template lines.
     */
    protected function createTransactionsFromTemplate(JournalEntry $journalEntry, RecurringJournalTemplate $template): void
    {
        $template->lines->each(function (RecurringJournalTemplateLine $line) use ($journalEntry) {
            JournalTransaction::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $line->account_id,
                'debit_credit' => $line->debit_credit,
                'amount' => $line->amount,
                'description' => $line->description ?? $journalEntry->description,
                'currency' => $journalEntry->currency,
            ]);
        });
    }

    /**
     * Update the next generation date for the template.
     */
    protected function updateNextGenerationDate(RecurringJournalTemplate $template): void
    {
        $currentDate = now();
        $frequency = $template->frequency;
        $interval = $template->interval ?? 1;

        $nextDate = match ($frequency) {
            'daily' => $currentDate->copy()->addDays($interval),
            'weekly' => $currentDate->copy()->addWeeks($interval),
            'monthly' => $currentDate->copy()->addMonths($interval),
            'quarterly' => $currentDate->copy()->addQuarters($interval),
            'yearly' => $currentDate->copy()->addYears($interval),
            default => $currentDate->copy()->addMonth(),
        };

        // Check if the next date exceeds the end date
        if ($template->end_date && $nextDate->greaterThan(Carbon::parse($template->end_date))) {
            // Deactivate the template since it has completed its schedule
            $template->update([
                'next_generation_date' => null,
                'is_active' => false,
            ]);
        } else {
            $template->update([
                'next_generation_date' => $nextDate->toDateString(),
            ]);
        }
    }

    /**
     * Generate entries for all active templates that are due.
     */
    public function generateForAllDueTemplates(): array
    {
        $results = [];

        // Get all active templates that are due for generation
        $dueTemplates = RecurringJournalTemplate::query()
            ->where('is_active', true)
            ->where('next_generation_date', '<=', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->toDateString());
            })
            ->get();

        foreach ($dueTemplates as $template) {
            try {
                $entry = $this->execute($template);
                if ($entry) {
                    $results[] = [
                        'template_id' => $template->id,
                        'template_name' => $template->name,
                        'journal_entry_id' => $entry->id,
                        'status' => 'success',
                        'message' => 'Journal entry generated successfully',
                    ];
                } else {
                    $results[] = [
                        'template_id' => $template->id,
                        'template_name' => $template->name,
                        'status' => 'skipped',
                        'message' => 'Template not due for generation',
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
