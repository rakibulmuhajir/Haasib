<?php

namespace Modules\Reporting\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Reporting\Actions\Reports\GenerateReportAction;
use Modules\Reporting\Actions\Schedules\CreateReportScheduleAction;
use Modules\Reporting\Events\ScheduleRunCompleted;
use Modules\Reporting\Events\ScheduleRunFailed;

class RunScheduledReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $retryAfter = 30;

    /**
     * The maximum number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $scheduleId = null
    ) {
        $this->onQueue('reporting');
    }

    /**
     * Execute the job.
     */
    public function handle(GenerateReportAction $generateAction, CreateReportScheduleAction $scheduleAction): void
    {
        try {
            $this->lockScheduler();

            $schedules = $this->getSchedulesToRun();

            foreach ($schedules as $schedule) {
                $this->processSchedule($schedule, $generateAction, $scheduleAction);
            }

            Log::info('Scheduled reports job completed', [
                'processed_schedules' => count($schedules),
                'job_id' => $this->job?->getJobId(),
            ]);

        } catch (\Exception $e) {
            Log::error('Scheduled reports job failed', [
                'error' => $e->getMessage(),
                'schedule_id' => $this->scheduleId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        } finally {
            $this->releaseScheduler();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Scheduled reports job failed permanently', [
            'error' => $exception->getMessage(),
            'schedule_id' => $this->scheduleId,
            'attempts' => $this->attempts(),
        ]);

        // If this was a specific schedule run, mark it as failed
        if ($this->scheduleId) {
            try {
                DB::table('rpt.report_schedules')
                    ->where('schedule_id', $this->scheduleId)
                    ->update([
                        'status' => 'paused',
                        'updated_at' => now(),
                    ]);
            } catch (\Exception $e) {
                Log::error('Failed to pause schedule after job failure', [
                    'schedule_id' => $this->scheduleId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get the unique identifier for the job.
     */
    public function uniqueId(): string
    {
        return 'reporting:schedule-run:'.($this->scheduleId ?? 'all').':'.now()->format('Y-m-d-H:i');
    }

    /**
     * Get schedules that need to run
     */
    protected function getSchedulesToRun(): array
    {
        $query = DB::table('rpt.report_schedules')
            ->where('status', 'active')
            ->where('next_run_at', '<=', now())
            ->where(function ($query) {
                // Avoid duplicate runs within the same hour
                $query->whereNull('last_run_at')
                    ->orWhere('last_run_at', '<', now()->subHour());
            });

        if ($this->scheduleId) {
            $query->where('schedule_id', $this->scheduleId);
        }

        return $query->orderBy('next_run_at', 'asc')
            ->limit(50) // Process maximum 50 schedules per job run
            ->get()
            ->toArray();
    }

    /**
     * Process a single schedule
     */
    protected function processSchedule(array $schedule, GenerateReportAction $generateAction, CreateReportScheduleAction $scheduleAction): void
    {
        $scheduleId = $schedule['schedule_id'];
        $companyId = $schedule['company_id'];

        DB::transaction(function () use ($schedule, $scheduleId, $companyId, $generateAction) {
            try {
                // Mark as running to prevent duplicate processing
                DB::table('rpt.report_schedules')
                    ->where('schedule_id', $scheduleId)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'running',
                        'updated_at' => now(),
                    ]);

                // Generate the report
                $reportData = $this->prepareReportData($schedule);
                $result = $generateAction->execute($companyId, $reportData, false);

                // Update schedule with next run time
                $nextRunAt = $this->calculateNextRunTime($schedule);
                DB::table('rpt.report_schedules')
                    ->where('schedule_id', $scheduleId)
                    ->update([
                        'status' => 'active',
                        'last_run_at' => now(),
                        'next_run_at' => $nextRunAt,
                        'updated_at' => now(),
                    ]);

                // Create delivery records
                $this->createDeliveryRecords($result['report_id'], $schedule);

                // Fire success event
                event(new ScheduleRunCompleted($companyId, $scheduleId, $result['report_id']));

                Log::info('Schedule run completed successfully', [
                    'schedule_id' => $scheduleId,
                    'company_id' => $companyId,
                    'report_id' => $result['report_id'],
                    'next_run_at' => $nextRunAt,
                ]);

            } catch (\Exception $e) {
                // Mark schedule as paused on error
                DB::table('rpt.report_schedules')
                    ->where('schedule_id', $scheduleId)
                    ->update([
                        'status' => 'paused',
                        'updated_at' => now(),
                    ]);

                // Fire failure event
                event(new ScheduleRunFailed($companyId, $scheduleId, $e));

                Log::error('Schedule run failed', [
                    'schedule_id' => $scheduleId,
                    'company_id' => $companyId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Prepare report data from schedule
     */
    protected function prepareReportData(array $schedule): array
    {
        $parameters = json_decode($schedule['parameters'] ?? '{}', true);
        $parameters['triggered_by'] = 'schedule';
        $parameters['schedule_id'] = $schedule['schedule_id'];

        // Calculate date ranges for relative schedules
        if (isset($parameters['date_range_type'])) {
            switch ($parameters['date_range_type']) {
                case 'last_month':
                    $end = now()->subMonth()->endOfMonth();
                    $start = now()->subMonth()->startOfMonth();
                    break;

                case 'current_month':
                    $end = now()->endOfMonth();
                    $start = now()->startOfMonth();
                    break;

                case 'last_quarter':
                    $end = now()->subQuarter()->endOfQuarter();
                    $start = now()->subQuarter()->startOfQuarter();
                    break;

                case 'current_quarter':
                    $end = now()->endOfQuarter();
                    $start = now()->startOfQuarter();
                    break;

                case 'last_year':
                    $end = now()->subYear()->endOfYear();
                    $start = now()->subYear()->startOfYear();
                    break;

                case 'current_year':
                    $end = now()->endOfYear();
                    $start = now()->startOfYear();
                    break;

                default:
                    // Use provided dates or current month as fallback
                    $end = $parameters['date_range']['end'] ?? now()->endOfMonth();
                    $start = $parameters['date_range']['start'] ?? now()->startOfMonth();
                    break;
            }

            $parameters['date_range'] = [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ];
        }

        return [
            'report_type' => $this->getReportTypeFromTemplate($schedule['template_id']),
            'name' => $schedule['name'].' - '.now()->format('Y-m-d H:i'),
            'parameters' => $parameters,
            'filters' => $parameters['filters'] ?? [],
            'export_format' => $parameters['export_format'] ?? 'json',
            'async' => false, // Synchronous for scheduled runs
            'priority' => 'low',
        ];
    }

    /**
     * Create delivery records for a report
     */
    protected function createDeliveryRecords(string $reportId, array $schedule): void
    {
        $deliveryChannels = json_decode($schedule['delivery_channels'] ?? '{}', true);
        $scheduleId = $schedule['schedule_id'];
        $companyId = $schedule['company_id'];

        foreach ($deliveryChannels as $channel) {
            try {
                DB::table('rpt.report_deliveries')->insert([
                    'delivery_id' => \Illuminate\Support\Str::uuid()->toString(),
                    'company_id' => $companyId,
                    'schedule_id' => $scheduleId,
                    'report_id' => $reportId,
                    'channel' => $channel['type'],
                    'target' => json_encode($channel),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create delivery record', [
                    'report_id' => $reportId,
                    'channel' => $channel['type'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Calculate next run time for a schedule
     */
    protected function calculateNextRunTime(array $schedule): string
    {
        $frequency = $schedule['frequency'];
        $customCron = $schedule['custom_cron'];
        $timezone = $schedule['timezone'];

        $now = Carbon::now($timezone);

        switch ($frequency) {
            case 'daily':
                return $now->addDay()->startOfDay()->toDateTimeString();

            case 'weekly':
                return $now->addWeek()->startOfDay()->toDateTimeString();

            case 'monthly':
                return $now->addMonth()->startOfDay()->toDateTimeString();

            case 'quarterly':
                return $now->addMonths(3)->startOfDay()->toDateTimeString();

            case 'yearly':
                return $now->addYear()->startOfDay()->toDateTimeString();

            case 'custom':
                if (! $customCron) {
                    throw new \InvalidArgumentException('Custom cron expression required for custom frequency');
                }

                try {
                    $cron = \Dragonmantank\CronExpression\CronExpression::factory($customCron);

                    return $cron->getNextRunDate($now)->toDateTimeString();
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException('Invalid cron expression: '.$e->getMessage());
                }

            default:
                throw new \InvalidArgumentException("Invalid frequency: {$frequency}");
        }
    }

    /**
     * Get report type from template
     */
    protected function getReportTypeFromTemplate(string $templateId): string
    {
        $template = DB::table('rpt.report_templates')
            ->where('template_id', $templateId)
            ->first();

        if (! $template) {
            throw new \InvalidArgumentException('Template not found: '.$templateId);
        }

        return $template->report_type;
    }

    /**
     * Acquire scheduler lock to prevent overlapping job runs
     */
    protected function lockScheduler(): void
    {
        $lockKey = 'reporting:scheduler:lock';

        if (! Cache::add($lockKey, true, 300)) { // 5 minutes lock
            throw new \RuntimeException('Scheduler is already running');
        }
    }

    /**
     * Release scheduler lock
     */
    protected function releaseScheduler(): void
    {
        Cache::forget('reporting:scheduler:lock');
    }
}
