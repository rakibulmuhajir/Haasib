<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Reporting\Actions\Schedules\CreateReportScheduleAction;
use Modules\Reporting\Jobs\RunScheduledReportsJob;

class ReportScheduleController extends Controller
{
    public function __construct(
        private CreateReportScheduleAction $scheduleAction
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:reporting.schedules.view')->only(['index', 'show']);
        $this->middleware('permission:reporting.schedules.create')->only(['store']);
        $this->middleware('permission:reporting.schedules.update')->only(['update', 'pause', 'resume', 'trigger']);
        $this->middleware('permission:reporting.schedules.delete')->only(['destroy']);
    }

    /**
     * List report schedules
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['active', 'paused', 'archived'])],
            'frequency' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'])],
            'template_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $filters = array_intersect_key($validated, array_flip([
                'status', 'frequency', 'template_id', 'search'
            ]));

            $schedules = $this->scheduleAction->listSchedules($companyId, $filters);

            // Add delivery statistics
            foreach ($schedules as &$schedule) {
                $schedule['delivery_stats'] = $this->getDeliveryStats($schedule['schedule_id']);
            }

            return response()->json([
                'data' => $schedules,
            ]);

        } catch (\Exception $e) {
            Log::error('Schedule listing failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch report schedules.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a new report schedule
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'exists:rpt.report_templates,template_id'],
            'name' => ['required', 'string', 'max:255'],
            'frequency' => ['required', 'string', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'])],
            'custom_cron' => ['required_if:frequency,custom', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:50'],
            'parameters' => ['nullable', 'array'],
            'delivery_channels' => ['nullable', 'array'],
            'status' => ['nullable', 'string', Rule::in(['active', 'paused', 'archived'])],
        ]);

        $companyId = $request->user()->current_company_id;
        $userId = $request->user()->id;

        // Prepare schedule data
        $scheduleData = array_merge($validated, [
            'company_id' => $companyId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        try {
            $schedule = $this->scheduleAction->execute($scheduleData);

            return response()->json($schedule, Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Schedule creation failed', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'name' => $validated['name'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to create report schedule.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show a specific report schedule
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $schedule = $this->scheduleAction->getSchedule($id, $companyId);

            // Add delivery statistics and recent runs
            $schedule['delivery_stats'] = $this->getDeliveryStats($id);
            $schedule['recent_runs'] = $this->getRecentRuns($id);
            $schedule['next_runs'] = $this->getNextRuns($id, 5);

            return response()->json($schedule);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Schedule fetch failed', [
                'company_id' => $companyId,
                'schedule_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch report schedule.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a report schedule
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['sometimes', 'exists:rpt.report_templates,template_id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'frequency' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'yearly', 'custom'])],
            'custom_cron' => ['required_if:frequency,custom', 'string', 'max:100'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'parameters' => ['nullable', 'array'],
            'delivery_channels' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'paused', 'archived'])],
        ]);

        $companyId = $request->user()->current_company_id;
        $userId = $request->user()->id;

        // Add updated by
        if (!empty($validated)) {
            $validated['updated_by'] = $userId;
        }

        try {
            $schedule = $this->scheduleAction->updateSchedule($id, $companyId, $validated);

            return response()->json($schedule);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Schedule update failed', [
                'company_id' => $companyId,
                'schedule_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to update report schedule.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a report schedule
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;
        $userId = $request->user()->id;

        try {
            $this->scheduleAction->deleteSchedule($id, $companyId);

            Log::info('Schedule deleted by user', [
                'schedule_id' => $id,
                'company_id' => $companyId,
                'user_id' => $userId,
            ]);

            return response()->json([
                'message' => 'Report schedule deleted successfully.',
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Schedule deletion failed', [
                'company_id' => $companyId,
                'schedule_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to delete report schedule.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Pause a schedule
     */
    public function pause(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $schedule = $this->scheduleAction->pauseSchedule($id, $companyId);

            return response()->json($schedule);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Schedule pause failed', [
                'company_id' => $companyId,
                'schedule_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to pause report schedule.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Resume a schedule
     */
    public function resume(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $schedule = $this->scheduleAction->resumeSchedule($id, $companyId);

            return response()->json($schedule);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Schedule resume failed', [
                'company_id' => $companyId,
                'schedule_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to resume report schedule.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Manually trigger a schedule run
     */
    public function trigger(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $result = $this->scheduleAction->triggerScheduleRun($id, $companyId);

            return response()->json($result, Response::HTTP_ACCEPTED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Schedule trigger failed', [
                'company_id' => $companyId,
                'schedule_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to trigger schedule run.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get schedule statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $stats = \Illuminate\Support\Facades\DB::table('rpt.report_schedules')
                ->where('company_id', $companyId)
                ->selectRaw('
                    COUNT(*) as total_schedules,
                    SUM(CASE WHEN status = \'active\' THEN 1 ELSE 0 END) as active_schedules,
                    SUM(CASE WHEN status = \'paused\' THEN 1 ELSE 0 END) as paused_schedules,
                    SUM(CASE WHEN status = \'archived\' THEN 1 ELSE 0 END) as archived_schedules,
                    SUM(CASE WHEN last_run_at IS NOT NULL THEN 1 ELSE 0 END) as schedules_with_runs,
                    MAX(last_run_at) as last_run_date
                ')
                ->first();

            $deliveryStats = \Illuminate\Support\Facades\DB::table('rpt.report_deliveries')
                ->join('rpt.report_schedules', 'rpt.report_deliveries.schedule_id', '=', 'rpt.report_schedules.schedule_id')
                ->where('rpt.report_schedules.company_id', $companyId)
                ->selectRaw('
                    COUNT(*) as total_deliveries,
                    SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as successful_deliveries,
                    SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed_deliveries,
                    SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending_deliveries,
                    MAX(sent_at) as last_delivery_date
                ')
                ->first();

            return response()->json([
                'total_schedules' => (int) $stats->total_schedules,
                'active_schedules' => (int) $stats->active_schedules,
                'paused_schedules' => (int) $stats->paused_schedules,
                'archived_schedules' => (int) $stats->archived_schedules,
                'schedules_with_runs' => (int) $stats->schedules_with_runs,
                'last_run_at' => $stats->last_run_date,
                'total_deliveries' => (int) $deliveryStats->total_deliveries,
                'successful_deliveries' => (int) $deliveryStats->successful_deliveries,
                'failed_deliveries' => (int) $deliveryStats->failed_deliveries,
                'pending_deliveries' => (int) $deliveryStats->pending_deliveries,
                'last_delivery_at' => $deliveryStats->last_delivery_date,
                'delivery_success_rate' => $deliveryStats->total_deliveries > 0 
                    ? (($deliveryStats->successful_deliveries / $deliveryStats->total_deliveries) * 100) 
                    : 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Schedule statistics failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch schedule statistics.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get upcoming runs for all schedules
     */
    public function upcoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hours' => ['nullable', 'integer', 'min:1', 'max:168'], // Max one week
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $companyId = $request->user()->current_company_id;
        $hours = $validated['hours'] ?? 24;
        $limit = $validated['limit'] ?? 50;

        try {
            $upcomingRuns = \Illuminate\Support\Facades\DB::table('rpt.report_schedules')
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->where('next_run_at', '<=', now()->addHours($hours))
                ->where('next_run_at', '>', now())
                ->leftJoin('rpt.report_templates', 'rpt.report_schedules.template_id', '=', 'rpt.report_templates.template_id')
                ->select([
                    'rpt.report_schedules.schedule_id',
                    'rpt.report_schedules.name',
                    'rpt.report_schedules.frequency',
                    'rpt.report_schedules.next_run_at',
                    'rpt.report_templates.name as template_name',
                    'rpt.report_templates.report_type as template_type',
                ])
                ->orderBy('rpt.report_schedules.next_run_at', 'asc')
                ->limit($limit)
                ->get()
                ->toArray();

            return response()->json([
                'data' => $upcomingRuns,
            ]);

        } catch (\Exception $e) {
            Log::error('Upcoming runs fetch failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch upcoming runs.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get delivery statistics for a schedule
     */
    private function getDeliveryStats(string $scheduleId): array
    {
        return \Illuminate\Support\Facades\DB::table('rpt.report_deliveries')
            ->where('schedule_id', $scheduleId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending,
                MAX(sent_at) as last_sent_at
            ')
            ->first()
            ?->toArray() ?? [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'pending' => 0,
                'last_sent_at' => null,
            ];
    }

    /**
     * Get recent runs for a schedule
     */
    private function getRecentRuns(string $scheduleId, int $limit = 10): array
    {
        return \Illuminate\Support\Facades\DB::table('rpt.reports')
            ->where('company_id', function ($query) use ($scheduleId) {
                $query->select('company_id')
                      ->from('rpt.report_schedules')
                      ->where('schedule_id', $scheduleId);
            })
            ->whereRaw("parameters->>'schedule_id' = ?", [$scheduleId])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['report_id', 'name', 'status', 'created_at', 'generated_at', 'file_size'])
            ->toArray();
    }

    /**
     * Get next runs for a schedule
     */
    private function getNextRuns(string $scheduleId, int $count = 5): array
    {
        $schedule = $this->scheduleAction->getSchedule($scheduleId, '');
        
        $nextRuns = [];
        $nextRunAt = Carbon::parse($schedule['next_run_at']);

        for ($i = 0; $i < $count; $i++) {
            $nextRuns[] = [
                'run_number' => $i + 1,
                'scheduled_at' => $nextRunAt->copy()->toDateTimeString(),
            ];

            // Calculate next run time based on frequency
            switch ($schedule['frequency']) {
                case 'daily':
                    $nextRunAt->addDay();
                    break;
                case 'weekly':
                    $nextRunAt->addWeek();
                    break;
                case 'monthly':
                    $nextRunAt->addMonth();
                    break;
                case 'quarterly':
                    $nextRunAt->addMonths(3);
                    break;
                case 'yearly':
                    $nextRunAt->addYear();
                    break;
                case 'custom':
                    // For custom cron, we'll need to parse the expression
                    // For now, skip additional runs for custom schedules
                    break;
            }
        }

        return $nextRuns;
    }
}
