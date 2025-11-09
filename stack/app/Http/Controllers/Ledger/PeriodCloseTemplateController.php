<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Ledger\Domain\PeriodClose\Actions\ArchivePeriodCloseTemplateAction;
use Modules\Ledger\Domain\PeriodClose\Actions\SyncPeriodCloseTemplateAction;
use Modules\Ledger\Domain\PeriodClose\Actions\UpdatePeriodCloseTemplateAction;
use Modules\Ledger\Services\PeriodCloseService;

class PeriodCloseTemplateController extends Controller
{
    public function __construct(
        private PeriodCloseService $periodCloseService,
        private SyncPeriodCloseTemplateAction $syncAction,
        private UpdatePeriodCloseTemplateAction $updateAction,
        private ArchivePeriodCloseTemplateAction $archiveAction
    ) {
        $this->middleware('auth');
        $this->middleware('permission:period-close.templates.manage')->only(['store', 'update', 'destroy', 'sync', 'archive', 'duplicate']);
        $this->middleware('permission:period-close.view')->only(['index', 'show', 'statistics']);
    }

    /**
     * List period close templates for the current company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->current_company_id;

        $filters = $request->only(['frequency', 'active', 'search', 'sort_by', 'sort_order']);
        $perPage = $request->get('per_page', 15);

        $templates = $this->periodCloseService->getTemplates($companyId, $filters, $user, $perPage);

        return response()->json([
            'data' => [
                'templates' => $templates['data'],
                'total' => $templates['total'],
                'per_page' => $templates['per_page'],
                'current_page' => $templates['current_page'],
                'last_page' => $templates['last_page'],
                'active_templates' => $this->periodCloseService->getTemplateStatistics($companyId, $user)['active_templates'],
            ],
        ]);
    }

    /**
     * Create a new period close template
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $templateData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'sometimes|string|nullable|max:1000',
                'frequency' => 'required|in:monthly,quarterly,yearly,custom',
                'is_default' => 'sometimes|boolean',
                'active' => 'sometimes|boolean',
                'tasks' => 'required|array|min:1',
                'tasks.*.code' => 'required|string|max:100',
                'tasks.*.title' => 'required|string|max:255',
                'tasks.*.category' => 'required|in:trial_balance,reconciliations,compliance,reporting,adjustments,other',
                'tasks.*.sequence' => 'required|integer|min:1',
                'tasks.*.is_required' => 'required|boolean',
                'tasks.*.default_notes' => 'sometimes|string|nullable|max:1000',
            ], [
                'name.required' => 'Template name is required',
                'frequency.required' => 'Template frequency is required',
                'tasks.required' => 'At least one task is required',
                'tasks.*.category.in' => 'Invalid task category. Must be one of: trial_balance, reconciliations, compliance, reporting, adjustments, other.',
            ]);

            $template = $this->periodCloseService->createTemplate($templateData, $user);

            return response()->json([
                'message' => 'Template created successfully',
                'data' => $template->load('templateTasks'),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create template: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific period close template
     */
    public function show(Request $request, string $templateId): JsonResponse
    {
        $user = $request->user();

        try {
            $template = $this->periodCloseService->getTemplate($templateId, $user);

            return response()->json([
                'data' => $template->load('templateTasks'),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Template not found or access denied',
            ], 404);
        }
    }

    /**
     * Update a period close template
     */
    public function update(Request $request, string $templateId): JsonResponse
    {
        $user = $request->user();

        try {
            $updateData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string|nullable|max:1000',
                'frequency' => 'sometimes|in:monthly,quarterly,yearly,custom',
                'is_default' => 'sometimes|boolean',
                'active' => 'sometimes|boolean',
                'tasks' => 'sometimes|array',
                'tasks.*.id' => 'sometimes|integer|exists:pgsql.ledger.period_close_template_tasks,id',
                'tasks.*.code' => 'required_with:tasks|string|max:100',
                'tasks.*.title' => 'required_with:tasks|string|max:255',
                'tasks.*.category' => 'required_with:tasks|in:trial_balance,reconciliations,compliance,reporting,adjustments,other',
                'tasks.*.sequence' => 'required_with:tasks|integer|min:1',
                'tasks.*.is_required' => 'required_with:tasks|boolean',
                'tasks.*.default_notes' => 'sometimes|string|nullable|max:1000',
            ]);

            $template = $this->updateAction->execute($templateId, $updateData, $user);

            return response()->json([
                'message' => 'Template updated successfully',
                'data' => $template->load('templateTasks'),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update template: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Archive (soft delete) a period close template
     */
    public function archive(Request $request, string $templateId): JsonResponse
    {
        $user = $request->user();

        try {
            $this->archiveAction->execute($templateId, $user);

            return response()->json([
                'message' => 'Template archived successfully',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to archive template: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync a template to a period close
     */
    public function sync(Request $request, string $templateId): JsonResponse
    {
        $user = $request->user();

        try {
            $syncData = $request->validate([
                'period_close_id' => 'required|string|exists:pgsql.ledger.period_closes,id',
            ]);

            $result = $this->syncAction->execute($templateId, $syncData['period_close_id'], $user);

            return response()->json([
                'message' => 'Template synced to period close successfully',
                'data' => $result,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to sync template: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate an existing template
     */
    public function duplicate(Request $request, string $templateId): JsonResponse
    {
        $user = $request->user();

        try {
            $duplicateData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'sometimes|string|nullable|max:1000',
            ]);

            $template = $this->periodCloseService->duplicateTemplate($templateId, $duplicateData, $user);

            return response()->json([
                'message' => 'Template duplicated successfully',
                'data' => $template->load('templateTasks'),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to duplicate template: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get template statistics for the current company
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $user->current_company_id;

        $statistics = $this->periodCloseService->getTemplateStatistics($companyId, $user);

        return response()->json($statistics);
    }
}
