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
use Modules\Reporting\Actions\Dashboard\RefreshDashboardAction;

class RefreshDashboardJob implements ShouldQueue
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
    public string $queue = 'dashboard';

    private string $jobId;

    private string $companyId;

    private string $layoutId;

    private array $parameters;

    public function __construct(string $companyId, string $layoutId, array $parameters = [])
    {
        $this->jobId = (string) Str::uuid();
        $this->companyId = $companyId;
        $this->layoutId = $layoutId;
        $this->parameters = $parameters;

        // Set priority based on parameters
        $priority = $parameters['priority'] ?? 'normal';
        $this->onQueue(match ($priority) {
            'high' => 'dashboard-high',
            'low' => 'dashboard-low',
            default => 'dashboard',
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
        return sprintf('dashboard-refresh-%s-%s', $this->companyId, $this->layoutId);
    }

    /**
     * Execute the job.
     */
    public function handle(RefreshDashboardAction $refreshAction): void
    {
        $startTime = now();

        Log::info('Dashboard refresh job started', [
            'job_id' => $this->jobId,
            'company_id' => $this->companyId,
            'layout_id' => $this->layoutId,
            'queue' => $this->queue,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Set company context for audit logging
            if (auth()->check()) {
                app('current_company_id', $this->companyId);
                app('current_user_id', auth()->id());
            }

            // Perform the refresh
            $refreshAction->performRefresh($this->companyId, $this->layoutId, $this->parameters);

            $duration = $startTime->diffInSeconds(now());

            Log::info('Dashboard refresh job completed', [
                'job_id' => $this->jobId,
                'company_id' => $this->companyId,
                'layout_id' => $this->layoutId,
                'duration_seconds' => $duration,
                'attempt' => $this->attempts(),
            ]);

            // Fire event for notification purposes
            event(new \Modules\Reporting\Events\DashboardRefreshed(
                $this->companyId,
                $this->layoutId,
                $this->parameters,
                $duration
            ));

        } catch (\Exception $e) {
            $duration = $startTime->diffInSeconds(now());

            Log::error('Dashboard refresh job failed', [
                'job_id' => $this->jobId,
                'company_id' => $this->companyId,
                'layout_id' => $this->layoutId,
                'error' => $e->getMessage(),
                'duration_seconds' => $duration,
                'attempt' => $this->attempts(),
                'will_retry' => $this->attempts() < $this->tries,
            ]);

            // Fire event for error notifications
            event(new \Modules\Reporting\Events\DashboardRefreshFailed(
                $this->companyId,
                $this->layoutId,
                $this->parameters,
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
        Log::error('Dashboard refresh job failed permanently', [
            'job_id' => $this->jobId,
            'company_id' => $this->companyId,
            'layout_id' => $this->layoutId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'exception_class' => get_class($exception),
        ]);

        // Fire event for permanent failure notifications
        event(new \Modules\Reporting\Events\DashboardRefreshFailedPermanently(
            $this->companyId,
            $this->layoutId,
            $this->parameters,
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
            'dashboard-refresh',
            'company:'.$this->companyId,
            'layout:'.$this->layoutId,
            'job-id:'.$this->jobId,
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(15); // Maximum 15 minutes total runtime
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            new \Illuminate\Queue\Middleware\WithoutOverlapping(
                key: 'dashboard-refresh-'.$this->companyId,
                releaseAfter: 30
            ),
            new \Illuminate\Queue\Middleware\Throttles(10, 60), // 10 jobs per minute per company
        ];
    }
}
