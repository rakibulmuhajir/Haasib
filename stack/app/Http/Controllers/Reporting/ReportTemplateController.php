<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Modules\Reporting\Services\ReportTemplateService;

class ReportTemplateController extends Controller
{
    public function __construct(
        private ReportTemplateService $templateService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:reporting.templates.view')->only(['index', 'show']);
        $this->middleware('permission:reporting.templates.create')->only(['store']);
        $this->middleware('permission:reporting.templates.update')->only(['update', 'reorder']);
        $this->middleware('permission:reporting.templates.delete')->only(['destroy']);
    }

    /**
     * List report templates
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['nullable', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard', 'custom'])],
            'category' => ['nullable', 'string', Rule::in(['financial', 'operational', 'analytical'])],
            'is_public' => ['nullable', 'boolean'],
            'is_system_template' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $filters = array_intersect_key($validated, array_flip([
                'report_type', 'category', 'is_public', 'is_system_template', 'search',
            ]));

            $templates = $this->templateService->listTemplates($companyId, $filters);

            return response()->json([
                'data' => $templates,
            ]);

        } catch (\Exception $e) {
            Log::error('Template listing failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch report templates.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a new report template
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'report_type' => ['required', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard', 'custom'])],
            'category' => ['required', 'string', Rule::in(['financial', 'operational', 'analytical'])],
            'configuration' => ['required', 'array'],
            'filters' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'is_public' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $companyId = $request->user()->current_company_id;
        $userId = $request->user()->id;

        // Prepare template data
        $templateData = array_merge($validated, [
            'company_id' => $companyId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'is_system_template' => false, // Never allow system template creation via API
        ]);

        try {
            $template = $this->templateService->createTemplate($templateData);

            return response()->json($template, Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Template creation failed', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'name' => $validated['name'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to create report template.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show a specific report template
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $template = $this->templateService->getTemplate($id, $companyId);

            // Include usage statistics
            $usage = $this->templateService->getTemplateUsage($id, $companyId);
            $template['usage'] = $usage;

            return response()->json($template);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Template fetch failed', [
                'company_id' => $companyId,
                'template_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch report template.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a report template
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['sometimes', 'string', Rule::in(['financial', 'operational', 'analytical'])],
            'configuration' => ['sometimes', 'array'],
            'filters' => ['nullable', 'array'],
            'parameters' => ['nullable', 'array'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $companyId = $request->user()->current_company_id;
        $userId = $request->user()->id;

        // Add updated by
        if (! empty($validated)) {
            $validated['updated_by'] = $userId;
        }

        try {
            $template = $this->templateService->updateTemplate($id, $companyId, $validated);

            return response()->json($template);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Template update failed', [
                'company_id' => $companyId,
                'template_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to update report template.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a report template
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;
        $userId = $request->user()->id;

        try {
            $this->templateService->deleteTemplate($id, $companyId);

            Log::info('Template deleted by user', [
                'template_id' => $id,
                'company_id' => $companyId,
                'user_id' => $userId,
            ]);

            return response()->json([
                'message' => 'Report template deleted successfully.',
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Template deletion failed', [
                'company_id' => $companyId,
                'template_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to delete report template.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Duplicate a report template
     */
    public function duplicate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_public' => ['boolean'],
        ]);

        $companyId = $request->user()->current_company_id;
        $userId = $request->user()->id;

        try {
            $template = $this->templateService->duplicateTemplate($id, $companyId, [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_public' => $validated['is_public'] ?? false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return response()->json($template, Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Template duplication failed', [
                'company_id' => $companyId,
                'source_template_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to duplicate report template.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reorder report templates
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'templates' => ['required', 'array', 'min:1'],
            'templates.*.template_id' => ['required', 'uuid'],
            'templates.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $this->templateService->reorderTemplates($companyId, $validated['templates']);

            return response()->json([
                'message' => 'Template order updated successfully.',
            ]);

        } catch (\Exception $e) {
            Log::error('Template reordering failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to reorder report templates.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Validate template configuration
     */
    public function validateConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard', 'custom'])],
            'configuration' => ['required', 'array'],
        ]);

        try {
            $errors = $this->templateService->validateTemplateConfiguration($validated);

            if (empty($errors)) {
                return response()->json([
                    'valid' => true,
                    'message' => 'Template configuration is valid.',
                ]);
            } else {
                return response()->json([
                    'valid' => false,
                    'errors' => $errors,
                    'message' => 'Template configuration has validation errors.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

        } catch (\Exception $e) {
            Log::error('Template configuration validation failed', [
                'report_type' => $validated['report_type'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to validate template configuration.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get default configuration for a report type
     */
    public function getDefaultConfiguration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard'])],
        ]);

        try {
            $configuration = $this->templateService->getDefaultConfiguration($validated['report_type']);

            return response()->json([
                'configuration' => $configuration,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get default configuration', [
                'report_type' => $validated['report_type'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to get default configuration.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get templates available to user (based on permissions and visibility)
     */
    public function available(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['nullable', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance', 'kpi_dashboard', 'custom'])],
        ]);

        $companyId = $request->user()->current_company_id;
        $userRoles = $request->user()->roles->pluck('name')->toArray();

        try {
            $templates = $this->templateService->getAvailableTemplates($companyId, $userRoles);

            // Filter by report type if specified
            if (isset($validated['report_type'])) {
                $templates = array_filter($templates, function ($template) use ($validated) {
                    return $template['report_type'] === $validated['report_type'];
                });
            }

            return response()->json([
                'data' => array_values($templates),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get available templates', [
                'company_id' => $companyId,
                'user_roles' => $userRoles,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to get available templates.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
