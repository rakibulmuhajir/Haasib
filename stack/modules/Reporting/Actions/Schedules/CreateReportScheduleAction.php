<?php

namespace Modules\Reporting\Actions\Schedules;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateReportScheduleAction
{
    /**
     * Create a new report schedule
     */
    public function execute(array $data): array
    {
        $validator = Validator::make($data, $this->getValidationRules());

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $validated = $validator->validated();

        // Set defaults and compute next run time
        $this->prepareScheduleData($validated);

        try {
            DB::transaction(function () use ($validated) {
                $scheduleId = DB::table('rpt.report_schedules')->insertGetId([
                    'schedule_id' => $validated['schedule_id'],
                    'company_id' => $validated['company_id'],
                    'template_id' => $validated['template_id'],
                    'name' => $validated['name'],
                    'frequency' => $validated['frequency'],
                    'custom_cron' => $validated['custom_cron'] ?? null,
                    'next_run_at' => $validated['next_run_at'],
                    'timezone' => $validated['timezone'],
                    'parameters' => json_encode($validated['parameters']),
                    'delivery_channels' => json_encode($validated['delivery_channels']),
                    'status' => $validated['status'],
                    'created_by' => $validated['created_by'],
                    'updated_by' => $validated['updated_by'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Log schedule creation
                Log::info('Report schedule created', [
                    'schedule_id' => $validated['schedule_id'],
                    'company_id' => $validated['company_id'],
                    'template_id' => $validated['template_id'],
                    'frequency' => $validated['frequency'],
                    'next_run_at' => $validated['next_run_at'],
                ]);

                return $validated['schedule_id'];
            });

            return $this->getSchedule($validated['schedule_id'], $validated['company_id']);

        } catch (\Exception $e) {
            Log::error('Failed to create report schedule', [
                'company_id' => $validated['company_id'] ?? null,
                'template_id' => $validated['template_id'] ?? null,
                'name' => $validated['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to create report schedule: '.$e->getMessage());
        }
    }

    /**
     * Update an existing schedule
     */
    public function updateSchedule(string $scheduleId, string $companyId, array $data): array
    {
        $schedule = $this->getSchedule($scheduleId, $companyId);

        $validator = Validator::make($data, $this->getUpdateRules($schedule));

        if ($validator->fails()) {
            throw new \InvalidArgumentException($validator->errors()->first());
        }

        $validated = $validator->validated();
        $validated['updated_at'] = now();

        // Recalculate next run time if frequency or timing changed
        $this->recalculateNextRun($validated, $schedule);

        try {
            DB::table('rpt.report_schedules')
                ->where('schedule_id', $scheduleId)
                ->where('company_id', $companyId)
                ->update($validated);

            Log::info('Report schedule updated', [
                'schedule_id' => $scheduleId,
                'company_id' => $companyId,
                'changes' => array_keys($validated),
            ]);

            return $this->getSchedule($scheduleId, $companyId);

        } catch (\Exception $e) {
            Log::error('Failed to update report schedule', [
                'schedule_id' => $scheduleId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to update report schedule: '.$e->getMessage());
        }
    }

    /**
     * Delete a schedule
     */
    public function deleteSchedule(string $scheduleId, string $companyId): void
    {
        $schedule = $this->getSchedule($scheduleId, $companyId);

        try {
            DB::table('rpt.report_schedules')
                ->where('schedule_id', $scheduleId)
                ->where('company_id', $companyId)
                ->delete();

            Log::info('Report schedule deleted', [
                'schedule_id' => $scheduleId,
                'company_id' => $companyId,
                'name' => $schedule['name'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete report schedule', [
                'schedule_id' => $scheduleId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to delete report schedule: '.$e->getMessage());
        }
    }

    /**
     * Pause a schedule
     */
    public function pauseSchedule(string $scheduleId, string $companyId): array
    {
        return $this->updateSchedule($scheduleId, $companyId, [
            'status' => 'paused',
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Resume a schedule
     */
    public function resumeSchedule(string $scheduleId, string $companyId): array
    {
        $schedule = $this->getSchedule($scheduleId, $companyId);

        $nextRunAt = $this->calculateNextRunTime($schedule['frequency'], $schedule['custom_cron'], $schedule['timezone']);

        return $this->updateSchedule($scheduleId, $companyId, [
            'status' => 'active',
            'next_run_at' => $nextRunAt,
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * Manually trigger a schedule run
     */
    public function triggerScheduleRun(string $scheduleId, string $companyId): array
    {
        $schedule = $this->getSchedule($scheduleId, $companyId);

        // Create an immediate report run
        $reportData = [
            'company_id' => $companyId,
            'template_id' => $schedule['template_id'],
            'report_type' => $this->getReportTypeFromTemplate($schedule['template_id']),
            'name' => $schedule['name'].' - '.now()->format('Y-m-d H:i:s'),
            'parameters' => array_merge(
                json_decode($schedule['parameters'] ?? '{}', true),
                ['triggered_by' => 'manual', 'schedule_id' => $scheduleId]
            ),
            'filters' => json_decode($schedule['parameters'] ?? '{}', true),
            'status' => 'queued',
            'created_by' => auth()->id(),
            'created_at' => now(),
        ];

        $reportId = DB::table('rpt.reports')->insertGetId($reportData);

        // Update schedule's last run time
        $this->updateSchedule($scheduleId, $companyId, [
            'last_run_at' => now(),
            'next_run_at' => $this->calculateNextRunTime(
                $schedule['frequency'],
                $schedule['custom_cron'],
                $schedule['timezone']
            ),
            'updated_by' => auth()->id(),
        ]);

        return [
            'report_id' => $reportId,
            'schedule_id' => $scheduleId,
            'message' => 'Schedule run triggered successfully',
        ];
    }

    /**
     * Get schedule details
     */
    public function getSchedule(string $scheduleId, string $companyId): array
    {
        $schedule = DB::table('rpt.report_schedules')
            ->where('schedule_id', $scheduleId)
            ->where('company_id', $companyId)
            ->first();

        if (! $schedule) {
            throw new \InvalidArgumentException('Schedule not found');
        }

        $scheduleArray = (array) $schedule;
        $scheduleArray['parameters'] = json_decode($schedule->parameters ?? '{}', true);
        $scheduleArray['delivery_channels'] = json_decode($schedule->delivery_channels ?? '{}', true);

        return $scheduleArray;
    }

    /**
     * List schedules for a company
     */
    public function listSchedules(string $companyId, array $filters = []): array
    {
        $query = DB::table('rpt.report_schedules')
            ->leftJoin('rpt.report_templates', 'rpt.report_schedules.template_id', '=', 'rpt.report_templates.template_id')
            ->where('rpt.report_schedules.company_id', $companyId)
            ->select([
                'rpt.report_schedules.*',
                'rpt.report_templates.name as template_name',
                'rpt.report_templates.report_type as template_type',
            ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('rpt.report_schedules.status', $filters['status']);
        }

        if (isset($filters['frequency'])) {
            $query->where('rpt.report_schedules.frequency', $filters['frequency']);
        }

        if (isset($filters['template_id'])) {
            $query->where('rpt.report_schedules.template_id', $filters['template_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('rpt.report_schedules.name', 'ilike', "%{$search}%")
                    ->orWhere('rpt.report_templates.name', 'ilike', "%{$search}%");
            });
        }

        return $query->orderBy('rpt.report_schedules.created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get schedules that need to run
     */
    public function getSchedulesToRun(): array
    {
        return DB::table('rpt.report_schedules')
            ->where('status', 'active')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at', 'asc')
            ->get()
            ->toArray();
    }

    /**
     * Get next run times for multiple schedules
     */
    public function getNextRunTimes(array $scheduleIds): array
    {
        return DB::table('rpt.report_schedules')
            ->whereIn('schedule_id', $scheduleIds)
            ->select(['schedule_id', 'next_run_at', 'frequency'])
            ->get()
            ->keyBy('schedule_id')
            ->toArray();
    }

    /**
     * Prepare schedule data
     */
    private function prepareScheduleData(array &$data): void
    {
        $data['schedule_id'] = \Illuminate\Support\Str::uuid()->toString();
        $data['next_run_at'] = $this->calculateNextRunTime(
            $data['frequency'],
            $data['custom_cron'] ?? null,
            $data['timezone']
        );
        $data['status'] = $data['status'] ?? 'active';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        // Sanitize JSON fields
        $data['parameters'] = json_encode($data['parameters'] ?? []);
        $data['delivery_channels'] = json_encode($data['delivery_channels'] ?? []);
    }

    /**
     * Calculate next run time based on frequency
     */
    private function calculateNextRunTime(string $frequency, ?string $customCron, string $timezone): string
    {
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
                    $cron = \Cron\CronExpression::factory($customCron);

                    return $cron->getNextRunDate($now)->toDateTimeString();
                } catch (\Exception $e) {
                    throw new \InvalidArgumentException('Invalid cron expression: '.$e->getMessage());
                }

            default:
                throw new \InvalidArgumentException("Invalid frequency: {$frequency}");
        }
    }

    /**
     * Recalculate next run time for updates
     */
    private function recalculateNextRunTime(array &$validated, array $schedule): void
    {
        if (isset($validated['frequency']) || isset($validated['custom_cron']) || isset($validated['timezone'])) {
            $frequency = $validated['frequency'] ?? $schedule['frequency'];
            $customCron = $validated['custom_cron'] ?? $schedule['custom_cron'];
            $timezone = $validated['timezone'] ?? $schedule['timezone'];

            $validated['next_run_at'] = $this->calculateNextRunTime($frequency, $customCron, $timezone);
        }

        // Sanitize JSON fields if present
        if (isset($validated['parameters'])) {
            $validated['parameters'] = json_encode($validated['parameters']);
        }

        if (isset($validated['delivery_channels'])) {
            $validated['delivery_channels'] = json_encode($validated['delivery_channels']);
        }
    }

    /**
     * Get report type from template
     */
    private function getReportTypeFromTemplate(string $templateId): string
    {
        $template = DB::table('rpt.report_templates')
            ->where('template_id', $templateId)
            ->first();

        if (! $template) {
            throw new \InvalidArgumentException('Template not found');
        }

        return $template->report_type;
    }

    /**
     * Get validation rules for creation
     */
    private function getValidationRules(): array
    {
        return [
            'company_id' => ['required', 'uuid'],
            'template_id' => ['required', 'exists:pgsql.rpt.report_templates,template_id'],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'])],
            'custom_cron' => ['required_if:frequency,custom', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:50'],
            'parameters' => ['nullable', 'array'],
            'delivery_channels' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(['active', 'paused', 'archived'])],
            'created_by' => ['required', 'uuid'],
            'updated_by' => ['nullable', 'uuid'],
        ];
    }

    /**
     * Get validation rules for update
     */
    private function getUpdateRules(array $schedule): array
    {
        return [
            'template_id' => ['sometimes', 'exists:pgsql.rpt.report_templates,template_id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'frequency' => ['sometimes', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'])],
            'custom_cron' => ['required_if:frequency,custom', 'string', 'max:100'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'parameters' => ['nullable', 'array'],
            'delivery_channels' => ['nullable', 'array'],
            'status' => ['sometimes', Rule::in(['active', 'paused', 'archived'])],
            'updated_by' => ['required', 'uuid'],
        ];
    }
}
