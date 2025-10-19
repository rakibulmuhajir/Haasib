<?php

namespace Modules\Reporting\Jobs;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Reporting\Actions\Reports\GenerateReportAction;

class GenerateReportJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = [30, 60, 120];

    /**
     * The maximum number of seconds a job should run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * The queue the job should be sent to.
     */
    public string $queue = 'reports';

    private string $jobId;

    private string $reportId;

    private string $companyId;

    private array $parameters;

    public function __construct(string $reportId, string $companyId, array $parameters = [])
    {
        $this->jobId = (string) Str::uuid();
        $this->reportId = $reportId;
        $this->companyId = $companyId;
        $this->parameters = $parameters;

        // Set priority based on report type and parameters
        $priority = $parameters['priority'] ?? 'normal';
        $this->onQueue(match ($priority) {
            'high' => 'reports-high',
            'low' => 'reports-low',
            default => 'reports',
        });
    }

    /**
     * Get the unique job identifier
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Get the job's unique identifier for queue tracking
     */
    public function uniqueId(): string
    {
        return sprintf('report-generation-%s', $this->reportId);
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateReportAction $generateAction): void
    {
        $startTime = now();

        Log::info('Report generation job started', [
            'job_id' => $this->jobId,
            'report_id' => $this->reportId,
            'company_id' => $this->companyId,
            'report_type' => $this->parameters['report_type'],
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Set company context for audit logging
            if (auth()->check()) {
                app('current_company_id', $this->companyId);
                app('current_user_id', auth()->id());
            }

            // Perform the report generation
            $generateAction->performReportGeneration($this->reportId, $this->companyId, $this->parameters);

            $duration = $startTime->diffInSeconds(now());

            Log::info('Report generation job completed', [
                'job_id' => $this->jobId,
                'report_id' => $this->reportId,
                'company_id' => $this->companyId,
                'report_type' => $this->parameters['report_type'],
                'duration_seconds' => $duration,
                'attempt' => $this->attempts(),
            ]);

            // Fire event for notification purposes
            event(new \Modules\Reporting\Events\ReportGenerated(
                $this->companyId,
                $this->reportId,
                $this->parameters['report_type'],
                $duration
            ));

        } catch (\Exception $e) {
            $duration = $startTime->diffInSeconds(now());

            Log::error('Report generation job failed', [
                'job_id' => $this->jobId,
                'report_id' => $this->reportId,
                'company_id' => $this->companyId,
                'report_type' => $this->parameters['report_type'],
                'error' => $e->getMessage(),
                'duration_seconds' => $duration,
                'attempt' => $this->attempts(),
                'will_retry' => $this->attempts() < $this->tries,
            ]);

            // Fire event for error notifications
            event(new \Modules\Reporting\Events\ReportGenerationFailed(
                $this->companyId,
                $this->reportId,
                $this->parameters['report_type'],
                $e,
                $this->attempts()
            ));

            // Re-throw to trigger queue retry mechanism
            throw $e;
        } finally {
            // Clean up context
            app()->forget('current_company_id');
            app()->forget('current_user_id');
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Report generation job failed permanently', [
            'job_id' => $this->jobId,
            'report_id' => $this->reportId,
            'company_id' => $this->companyId,
            'report_type' => $this->parameters['report_type'],
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'exception_class' => get_class($exception),
        ]);

        // Fire event for permanent failure notifications
        event(new \Modules\Reporting\Events\ReportGenerationFailedPermanently(
            $this->companyId,
            $this->reportId,
            $this->parameters['report_type'],
            $exception,
            $this->attempts()
        ));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'report-generation',
            'company:'.$this->companyId,
            'report-id:'.$this->reportId,
            'report-type:'.$this->parameters['report_type'],
            'job-id:'.$this->jobId,
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10); // Maximum 10 minutes total runtime
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            new \Illuminate\Queue\Middleware\WithoutOverlapping(
                key: 'report-generation-'.$this->companyId,
                releaseAfter: 60
            ),
            new \Illuminate\Queue\Middleware\Throttles(5, 60), // 5 jobs per minute per company
        ];
    }

    /**
     * Calculate memory usage for the job
     */
    public function calculateMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Get estimated processing time
     */
    public function getEstimatedProcessingTime(): int
    {
        $reportType = $this->parameters['report_type'];
        $dateRange = $this->parameters['date_range'] ?? null;

        $baseTime = match ($reportType) {
            'trial_balance' => 10,
            'income_statement' => 15,
            'balance_sheet' => 20,
            'cash_flow' => 25,
            default => 30,
        };

        // Add time for large date ranges
        if ($dateRange) {
            $start = \Carbon\Carbon::parse($dateRange['start']);
            $end = \Carbon\Carbon::parse($dateRange['end']);
            $days = $start->diffInDays($end);

            if ($days > 365) {
                $baseTime *= 1.5; // 50% more time for year+ ranges
            } elseif ($days > 90) {
                $baseTime *= 1.25; // 25% more time for quarterly+ ranges
            }
        }

        return (int) $baseTime;
    }

    /**
     * Get job priority
     */
    public function getPriority(): string
    {
        return $this->parameters['priority'] ?? 'normal';
    }

    /**
     * Check if this is a high priority job
     */
    public function isHighPriority(): bool
    {
        return $this->getPriority() === 'high';
    }

    /**
     * Get report metadata for logging
     */
    public function getReportMetadata(): array
    {
        return [
            'report_id' => $this->reportId,
            'company_id' => $this->companyId,
            'report_type' => $this->parameters['report_type'],
            'parameters' => $this->parameters,
            'job_id' => $this->jobId,
            'queue' => $this->queue,
            'priority' => $this->getPriority(),
            'estimated_time' => $this->getEstimatedProcessingTime(),
            'memory_usage' => $this->calculateMemoryUsage(),
        ];
    }

    /**
     * Prepare the job for execution
     */
    protected function prepare(): void
    {
        // Set error reporting
        error_reporting(E_ALL);

        // Set memory limit for large reports
        $memoryLimit = $this->getMemoryLimit();
        if ($memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
        }

        parent::prepare();
    }

    /**
     * Get memory limit based on report type
     */
    private function getMemoryLimit(): string
    {
        return match ($this->parameters['report_type']) {
            'trial_balance' => '256M',
            'income_statement' => '512M',
            'balance_sheet' => '512M',
            'cash_flow' => '512M',
            default => '512M',
        };
    }

    /**
     * Cleanup after job execution
     */
    protected function tearDown(): void
    {
        // Clear any temporary files or data
        $this->clearTemporaryData();

        parent::tearDown();
    }

    /**
     * Clear temporary data
     */
    private function clearTemporaryData(): void
    {
        // This could be expanded to clean up any temporary files
        // created during report generation
    }
}
