<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Reporting\Jobs\RunScheduledReportsJob;

class ReportingScheduleRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reporting:schedule:run {--schedule-id= : Run a specific schedule by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run scheduled reports for all active schedules or a specific schedule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $scheduleId = $this->option('schedule-id');

        $this->info('Starting scheduled reports job...');

        try {
            $job = new RunScheduledReportsJob($scheduleId);

            // Run the job synchronously for the command
            $job->handle();

            if ($scheduleId) {
                $this->info("Successfully processed schedule: {$scheduleId}");
            } else {
                $this->info('Successfully processed all active schedules');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Failed to run scheduled reports: '.$e->getMessage());

            Log::error('Scheduled reports command failed', [
                'schedule_id' => $scheduleId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
