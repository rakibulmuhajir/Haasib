<?php

namespace Modules\Accounting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Accounting\Domain\Ledgers\Actions\Recurring\GenerateJournalEntriesFromTemplateAction;

class GenerateRecurringJournalEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $retryAfter = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('accounting');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = now();

        Log::info('Starting recurring journal entries generation job', [
            'started_at' => $startTime->toISOString(),
        ]);

        try {
            $action = new GenerateJournalEntriesFromTemplateAction;
            $results = $action->generateForAllDueTemplates();

            $this->logResults($results, $startTime);

            // Dispatch events for successful generation
            $this->dispatchSuccessEvents($results);

        } catch (\Exception $e) {
            Log::error('Failed to generate recurring journal entries', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'started_at' => $startTime->toISOString(),
            ]);

            throw $e;
        }

        $duration = $startTime->diffInSeconds(now());

        Log::info('Completed recurring journal entries generation job', [
            'duration_seconds' => $duration,
            'completed_at' => now()->toISOString(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Recurring journal entries generation job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);

        // TODO: Send notification to administrators about the failure
    }

    /**
     * Log the generation results.
     */
    protected function logResults(array $results, \Carbon\Carbon $startTime): void
    {
        $summary = [
            'total_templates' => count($results),
            'successful' => count(array_filter($results, fn ($r) => $r['status'] === 'success')),
            'skipped' => count(array_filter($results, fn ($r) => $r['status'] === 'skipped')),
            'errors' => count(array_filter($results, fn ($r) => $r['status'] === 'error')),
        ];

        Log::info('Recurring journal entries generation results', array_merge([
            'started_at' => $startTime->toISOString(),
            'completed_at' => now()->toISOString(),
            'duration_seconds' => $startTime->diffInSeconds(now()),
        ], $summary));

        // Log individual results if there are errors
        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                Log::warning('Failed to generate entry for template', [
                    'template_id' => $result['template_id'],
                    'template_name' => $result['template_name'],
                    'error' => $result['message'],
                ]);
            }
        }
    }

    /**
     * Dispatch events for successful generations.
     */
    protected function dispatchSuccessEvents(array $results): void
    {
        foreach ($results as $result) {
            if ($result['status'] === 'success') {
                // TODO: Dispatch RecurringJournalEntryGenerated event
                // event(new RecurringJournalEntryGenerated(
                //     $result['template_id'],
                //     $result['journal_entry_id'],
                //     $result['template_name']
                // ));
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['recurring-journals', 'accounting'];
    }
}
