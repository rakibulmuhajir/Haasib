<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Reporting\Actions\Reports\GenerateReportAction;

class ReportController extends Controller
{
    public function __construct(
        private GenerateReportAction $generateAction
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:reporting.reports.view')->only(['index', 'show']);
        $this->middleware('permission:reporting.reports.generate')->only(['store']);
        $this->middleware('permission:reporting.reports.delete')->only(['destroy']);
    }

    /**
     * List reports for the current company
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['nullable', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance'])],
            'status' => ['nullable', 'string', Rule::in(['queued', 'running', 'generated', 'failed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $companyId = $request->user()->current_company_id;
        $perPage = $validated['per_page'] ?? 20;
        $page = $validated['page'] ?? 1;

        try {
            $filters = array_intersect_key($validated, array_flip(['report_type', 'status', 'date_from', 'date_to']));

            $reports = $this->generateAction->getReports($companyId, $filters);

            // Simple pagination
            $total = count($reports);
            $pagedReports = array_slice($reports, ($page - 1) * $perPage, $perPage);

            return response()->json([
                'data' => $pagedReports,
                'meta' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Reports fetch failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch reports. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate a new report
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance'])],
            'date_range' => ['required', 'array', 'min:1'],
            'date_range.start' => ['required', 'date'],
            'date_range.end' => ['required', 'date', 'after_or_equal:date_range.start'],
            'comparison' => ['nullable', 'string', Rule::in(['prior_period', 'prior_year'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'export_format' => ['nullable', 'string', Rule::in(['json', 'pdf', 'csv'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high'])],
            'async' => ['nullable', 'boolean'],
            'include_zero_balances' => ['nullable', 'boolean'],
        ]);

        $companyId = $request->user()->current_company_id;
        $parameters = $validated;

        // Set default values
        $parameters['comparison'] = $parameters['comparison'] ?? 'prior_period';
        $parameters['currency'] = $parameters['currency'] ?? 'USD';
        $parameters['export_format'] = $parameters['export_format'] ?? 'json';
        $parameters['async'] = $parameters['async'] ?? true;

        try {
            $result = $this->generateAction->execute($companyId, $parameters, $parameters['async']);

            return response()->json($result, $parameters['async'] ? Response::HTTP_ACCEPTED : Response::HTTP_OK);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'validation_error',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to generate reports.',
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'company_id' => $companyId,
                'report_type' => $parameters['report_type'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to generate report. Please try again later.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Show report details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            // First check if report exists and belongs to company
            $report = \Illuminate\Support\Facades\DB::table('rpt.reports')
                ->where('report_id', $id)
                ->where('company_id', $companyId)
                ->first();

            if (! $report) {
                return response()->json([
                    'error' => 'not_found',
                    'message' => 'Report not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            $reportData = $this->generateAction->getReportStatus($id);

            // If report is generated, include the payload
            if ($reportData['status'] === 'generated') {
                $payload = \Illuminate\Support\Facades\DB::table('rpt.reports')
                    ->where('report_id', $id)
                    ->value('payload');

                if ($payload) {
                    $reportData['payload'] = json_decode($payload, true);
                }
            }

            return response()->json($reportData);

        } catch (\Exception $e) {
            Log::error('Report show failed', [
                'company_id' => $companyId,
                'report_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch report details.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a report
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $this->generateAction->deleteReport($id);

            return response()->json([
                'message' => 'Report deleted successfully.',
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Report deletion failed', [
                'company_id' => $companyId,
                'report_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to delete report.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download report file
     */
    public function download(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $fileInfo = $this->generateAction->getReportFile($id);

            if (! Storage::exists($fileInfo['file_path'])) {
                return response()->json([
                    'error' => 'file_not_found',
                    'message' => 'Report file not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            $fileContents = Storage::get($fileInfo['file_path']);
            $fileName = $fileInfo['file_name'];

            // Determine MIME type
            $mimeType = $this->getMimeType($fileName);

            return response($fileContents)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"')
                ->header('Content-Length', strlen($fileContents));

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Report download failed', [
                'company_id' => $companyId,
                'report_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to download report.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Download report using a secure token
     */
    public function downloadWithToken(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $token = $validated['token'];

            // Import and use the DeliverReportAction for token validation
            $deliverAction = new \Modules\Reporting\Actions\Reports\DeliverReportAction;

            // Validate and consume the token
            $tokenData = $deliverAction->validateDownloadToken($token, [
                'company_id' => null, // Skip company check for token-based access
                'user_id' => null,     // Skip user check for token-based access
                'ip' => $request->ip(),
            ]);

            // Verify the token is for this report
            if ($tokenData['report_id'] !== $id) {
                return response()->json([
                    'error' => 'invalid_token',
                    'message' => 'Token is not valid for this report.',
                ], Response::HTTP_FORBIDDEN);
            }

            // Get file info using the GenerateReportAction
            $fileInfo = $this->generateAction->getReportFile($id);

            if (! Storage::exists($fileInfo['file_path'])) {
                return response()->json([
                    'error' => 'file_not_found',
                    'message' => 'Report file not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            $fileContents = Storage::get($fileInfo['file_path']);
            $fileName = $fileInfo['file_name'];

            // Determine MIME type
            $mimeType = $this->getMimeType($fileName);

            Log::info('Report downloaded via token', [
                'report_id' => $id,
                'ip' => $request->ip(),
                'token_consumed' => $tokenData['single_use'] ?? false,
            ]);

            return response($fileContents)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="'.$fileName.'"')
                ->header('Content-Length', strlen($fileContents));

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'invalid_token',
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);

        } catch (\Exception $e) {
            Log::error('Token-based report download failed', [
                'report_id' => $id,
                'token' => substr($validated['token'], 0, 8).'...',
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to download report.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Deliver a report through configured channels
     */
    public function deliver(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'delivery_data' => ['sometimes', 'array'],
            'delivery_data.user_id' => ['sometimes', 'string'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $deliverAction = new \Modules\Reporting\Actions\Reports\DeliverReportAction;

            $result = $deliverAction->execute($id, $validated['delivery_data'] ?? []);

            Log::info('Report delivery initiated', [
                'company_id' => $companyId,
                'report_id' => $id,
                'user_id' => $request->user()->id,
                'delivery_count' => count($result['deliveries'] ?? []),
            ]);

            return response()->json($result, Response::HTTP_ACCEPTED);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Report delivery failed', [
                'company_id' => $companyId,
                'report_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to deliver report.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get report status
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $status = $this->generateAction->getReportStatus($id);

            return response()->json($status);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'not_found',
                'message' => 'Report not found.',
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            Log::error('Report status check failed', [
                'company_id' => $companyId,
                'report_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to check report status.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available report types
     */
    public function types(Request $request): JsonResponse
    {
        $types = [
            [
                'type' => 'income_statement',
                'name' => 'Income Statement',
                'description' => 'Shows revenue, expenses, and profit/loss for the specified period',
                'comparisons' => ['prior_period', 'prior_year'],
            ],
            [
                'type' => 'balance_sheet',
                'name' => 'Balance Sheet',
                'description' => 'Shows assets, liabilities, and equity as of the specified date',
                'comparisons' => ['prior_period', 'prior_year'],
            ],
            [
                'type' => 'cash_flow',
                'name' => 'Cash Flow Statement',
                'description' => 'Shows cash inflows and outflows from operating, investing, and financing activities',
                'comparisons' => ['prior_period', 'prior_year'],
            ],
            [
                'type' => 'trial_balance',
                'name' => 'Trial Balance',
                'description' => 'Shows all account balances and variance analysis compared to prior period',
                'comparisons' => ['prior_period'],
            ],
        ];

        return response()->json([
            'data' => $types,
        ]);
    }

    /**
     * Generate a sample report for preview
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'report_type' => ['required', 'string', Rule::in(['income_statement', 'balance_sheet', 'cash_flow', 'trial_balance'])],
            'date_range' => ['nullable', 'array', 'min:1'],
            'date_range.start' => ['nullable', 'date'],
            'date_range.end' => ['nullable', 'date', 'after_or_equal:date_range.start'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $companyId = $request->user()->current_company_id;
        $parameters = $validated;

        // Set default values for preview
        $parameters['comparison'] = null; // No comparison for preview
        $parameters['currency'] = $parameters['currency'] ?? 'USD';
        $parameters['export_format'] = 'json';
        $parameters['async'] = false; // Synchronous for preview

        try {
            $result = $this->generateAction->execute($companyId, $parameters, false);

            return response()->json([
                'preview' => $result['payload'] ?? null,
                'metadata' => [
                    'report_type' => $parameters['report_type'],
                    'date_range' => $parameters['date_range'] ?? null,
                    'currency' => $parameters['currency'],
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Report preview failed', [
                'company_id' => $companyId,
                'report_type' => $parameters['report_type'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'preview_failed',
                'message' => 'Failed to generate report preview.',
                'details' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get report statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $stats = \Illuminate\Support\Facades\DB::table('rpt.reports')
                ->where('company_id', $companyId)
                ->selectRaw('
                    COUNT(*) as total_reports,
                    SUM(CASE WHEN status = \'generated\' THEN 1 ELSE 0 END) as generated_reports,
                    SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed_reports,
                    SUM(CASE WHEN status = \'running\' THEN 1 ELSE 0 END) as running_reports,
                    SUM(CASE WHEN status = \'queued\' THEN 1 ELSE 0 END) as queued_reports,
                    MAX(created_at) as last_report_date,
                    SUM(CASE WHEN status = \'generated\' THEN file_size ELSE 0 END) as total_file_size
                ')
                ->first();

            return response()->json([
                'total_reports' => (int) $stats->total_reports,
                'generated_reports' => (int) $stats->generated_reports,
                'failed_reports' => (int) $stats->failed_reports,
                'running_reports' => (int) $stats->running_reports,
                'queued_reports' => (int) $stats->queued_reports,
                'last_report_date' => $stats->last_report_date,
                'total_file_size' => (int) $stats->total_file_size,
                'success_rate' => $stats->total_reports > 0 ? (($stats->generated_reports / $stats->total_reports) * 100) : 0,
            ]);

        } catch (\Exception $e) {
            Log::error('Report statistics failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch report statistics.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get MIME type based on file extension
     */
    private function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'html' => 'text/html',
            default => 'application/octet-stream',
        };
    }

    /**
     * Get transaction drilldown data
     */
    public function drilldown(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => ['required', 'string'],
            'account_code' => ['nullable', 'string'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'counterparty_id' => ['nullable', 'string'],
            'include_running_balances' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $drilldownQuery = new \Modules\Reporting\QueryBuilder\TransactionDrilldownQuery($companyId);

            // Apply filters
            if (isset($validated['account_code'])) {
                $drilldownQuery->forAccount($validated['account_code']);
            }

            $drilldownQuery->forDateRange($validated['date_from'], $validated['date_to']);

            if (isset($validated['counterparty_id'])) {
                $drilldownQuery->forCounterparty($validated['counterparty_id']);
            }

            $limit = $validated['limit'] ?? 100;
            $drilldownQuery->limit($limit);

            // Get transactions
            $transactions = $drilldownQuery->execute();

            // Get summary statistics
            $summary = $drilldownQuery->getSummary();

            // Include running balances if requested
            $runningBalances = [];
            if ($validated['include_running_balances'] && isset($validated['account_code'])) {
                $accountTransactions = $drilldownQuery->getAccountTransactions(
                    $validated['account_code'],
                    ['include_balances' => true, 'limit' => $limit]
                );
                $runningBalances = $accountTransactions['running_balances'] ?? [];
            }

            return response()->json([
                'transactions' => $transactions,
                'summary' => $summary,
                'running_balances' => $runningBalances,
                'parameters' => $validated,
            ]);

        } catch (\Exception $e) {
            Log::error('Transaction drilldown failed', [
                'company_id' => $companyId,
                'account_id' => $validated['account_id'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch transaction drilldown data.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Search transactions
     */
    public function searchTransactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search_term' => ['required', 'string', 'min:2', 'max:255'],
            'account_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $drilldownQuery = new \Modules\Reporting\QueryBuilder\TransactionDrilldownQuery($companyId);

            // Apply account filter if provided
            if (isset($validated['account_id'])) {
                $drilldownQuery->forAccount($validated['account_id']);
            }

            // Apply date range filter if provided
            if (isset($validated['date_from']) && isset($validated['date_to'])) {
                $drilldownQuery->forDateRange($validated['date_from'], $validated['date_to']);
            }

            // Set limit
            $limit = $validated['limit'] ?? 50;
            $drilldownQuery->limit($limit);

            // Search transactions
            $transactions = $drilldownQuery->search($validated['search_term']);

            return response()->json($transactions);

        } catch (\Exception $e) {
            Log::error('Transaction search failed', [
                'company_id' => $companyId,
                'search_term' => $validated['search_term'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to search transactions.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get aging KPIs for receivables and payables
     */
    public function agingKpis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'aging_buckets' => ['nullable', 'array'],
            'aging_buckets.*' => ['integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $kpiService = new \Modules\Reporting\Services\KpiComputationService(
                new \Modules\Reporting\Services\CurrencyConversionService
            );

            $agingKpis = $kpiService->computeAgingKpis($companyId, [
                'date' => $validated['date'] ?? now()->toDateString(),
                'aging_buckets' => $validated['aging_buckets'] ?? [30, 60, 90, 120],
                'currency' => $validated['currency'] ?? 'USD',
            ]);

            return response()->json($agingKpis);

        } catch (\Exception $e) {
            Log::error('Aging KPIs computation failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to compute aging KPIs.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get budget vs actual KPIs
     */
    public function budgetKpis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_range' => ['required', 'array'],
            'date_range.start' => ['required', 'date'],
            'date_range.end' => ['required', 'date', 'after_or_equal:date_range.start'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $kpiService = new \Modules\Reporting\Services\KpiComputationService(
                new \Modules\Reporting\Services\CurrencyConversionService
            );

            $budgetKpis = $kpiService->computeBudgetKpis($companyId, $validated);

            return response()->json($budgetKpis);

        } catch (\Exception $e) {
            Log::error('Budget KPIs computation failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to compute budget KPIs.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get advanced KPIs including ratios and trends
     */
    public function advancedKpis(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_range' => ['required', 'array'],
            'date_range.start' => ['required', 'date'],
            'date_range.end' => ['required', 'date', 'after_or_equal:date_range.start'],
            'comparison' => ['nullable', 'string', Rule::in(['prior_period', 'prior_year'])],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $companyId = $request->user()->current_company_id;

        try {
            $kpiService = new \Modules\Reporting\Services\KpiComputationService(
                new \Modules\Reporting\Services\CurrencyConversionService
            );

            $advancedKpis = $kpiService->computeAdvancedKpis($companyId, $validated);

            return response()->json($advancedKpis);

        } catch (\Exception $e) {
            Log::error('Advanced KPIs computation failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to compute advanced KPIs.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available currencies for the company
     */
    public function currencies(Request $request): JsonResponse
    {
        $companyId = $request->user()->current_company_id;

        try {
            $currencyService = new \Modules\Reporting\Services\CurrencyConversionService;

            $currencies = $currencyService->getAvailableCurrencies($companyId);

            return response()->json([
                'available_currencies' => $currencies,
                'base_currency' => $this->getCompanyBaseCurrency($companyId),
            ]);

        } catch (\Exception $e) {
            Log::error('Currency list fetch failed', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'internal_error',
                'message' => 'Failed to fetch currencies.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get company base currency
     */
    private function getCompanyBaseCurrency(string $companyId): string
    {
        return DB::table('auth.companies')
            ->where('id', $companyId)
            ->value('base_currency') ?? 'USD';
    }
}
