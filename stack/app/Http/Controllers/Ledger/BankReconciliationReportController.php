<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Modules\Ledger\Listeners\BankReconciliationAuditSubscriber;
use Modules\Ledger\Services\BankReconciliationReportService;

class BankReconciliationReportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->middleware('permission:bank_reconciliation_reports.view')->only(['index', 'show', 'audit', 'variance']);
        $this->middleware('permission:bank_reconciliation_reports.export')->only(['export', 'download']);
    }

    /**
     * Display list of available reports for a reconciliation.
     */
    public function index(BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            $availableReports = [
                [
                    'type' => 'summary',
                    'name' => 'Reconciliation Summary',
                    'description' => 'Complete overview of reconciliation status, matches, and adjustments',
                    'formats' => ['json', 'pdf', 'csv'],
                ],
                [
                    'type' => 'variance',
                    'name' => 'Variance Analysis',
                    'description' => 'Detailed analysis of variance and unmatched items',
                    'formats' => ['json', 'pdf'],
                ],
                [
                    'type' => 'audit',
                    'name' => 'Audit Trail',
                    'description' => 'Complete audit history of all reconciliation activities',
                    'formats' => ['json', 'pdf'],
                ],
            ];

            return response()->json([
                'reconciliation' => [
                    'id' => $reconciliation->id,
                    'status' => $reconciliation->status,
                    'statement_period' => $reconciliation->statement->statement_period,
                    'bank_account' => $reconciliation->ledgerAccount->name,
                ],
                'available_reports' => $availableReports,
                'permissions' => [
                    'can_view_reports' => Auth::user()->can('bank_reconciliation_reports.view'),
                    'can_export_reports' => Auth::user()->can('bank_reconciliation_reports.export'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Generate and return a specific report.
     */
    public function show(Request $request, BankReconciliation $reconciliation, string $reportType): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            $request->validate([
                'format' => 'nullable|in:json,pdf,csv',
            ]);

            $format = $request->get('format', 'json');
            $reportService = new BankReconciliationReportService;

            switch ($reportType) {
                case 'summary':
                    $report = $reportService->generateSummaryReport($reconciliation);
                    break;
                case 'variance':
                    $report = $reportService->generateVarianceAnalysis($reconciliation);
                    break;
                case 'audit':
                    $report = $reportService->generateAuditTrail($reconciliation);
                    break;
                default:
                    return response()->json([
                        'message' => 'Invalid report type',
                    ], 400);
            }

            // Log report access
            $subscriber = new BankReconciliationAuditSubscriber;
            $subscriber->logReportAccess($reconciliation, $reportType);

            return response()->json([
                'report_type' => $reportType,
                'format' => $format,
                'data' => $report,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Export report in specified format.
     */
    public function export(Request $request, BankReconciliation $reconciliation, string $reportType): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);
            $this->authorize('export', $reconciliation);

            $request->validate([
                'format' => 'required|in:pdf,csv',
            ]);

            $format = $request->get('format');
            $reportService = new BankReconciliationReportService;

            switch ($reportType) {
                case 'summary':
                    $report = $reportService->generateSummaryReport($reconciliation);
                    break;
                case 'variance':
                    $report = $reportService->generateVarianceAnalysis($reconciliation);
                    break;
                case 'audit':
                    $report = $reportService->generateAuditTrail($reconciliation);
                    break;
                default:
                    return response()->json([
                        'message' => 'Invalid report type',
                    ], 400);
            }

            // Generate the file
            $filepath = $reportService->generateReport($reconciliation, $format);
            $filename = basename($filepath);

            // Create download URL
            $downloadUrl = route('ledger.bank-reconciliations.reports.download', [
                'reconciliation' => $reconciliation->id,
                'filename' => $filename,
            ]);

            return response()->json([
                'message' => 'Report generated successfully',
                'report_type' => $reportType,
                'format' => $format,
                'filename' => $filename,
                'download_url' => $downloadUrl,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Download generated report file.
     */
    public function download(BankReconciliation $reconciliation, string $filename)
    {
        try {
            $this->authorize('view', $reconciliation);
            $this->authorize('export', $reconciliation);

            $filepath = "bank-reconciliation-reports/{$filename}";

            if (! Storage::disk('local')->exists($filepath)) {
                return response()->json([
                    'message' => 'Report file not found',
                ], 404);
            }

            $file = Storage::disk('local')->get($filepath);
            $mimeType = $this->getMimeType($filename);

            return Response::make($file, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Content-Length' => strlen($file),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get variance analysis for a reconciliation.
     */
    public function variance(BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            $reportService = new BankReconciliationReportService;
            $varianceReport = $reportService->generateVarianceAnalysis($reconciliation);

            return response()->json([
                'reconciliation_id' => $reconciliation->id,
                'variance_analysis' => $varianceReport,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Get audit trail for a reconciliation.
     */
    public function audit(BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            $reportService = new BankReconciliationReportService;
            $auditReport = $reportService->generateAuditTrail($reconciliation);

            return response()->json([
                'reconciliation_id' => $reconciliation->id,
                'audit_trail' => $auditReport,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Get real-time reconciliation metrics.
     */
    public function metrics(BankReconciliation $reconciliation): JsonResponse
    {
        try {
            $this->authorize('view', $reconciliation);

            $reconciliation->load(['matches', 'adjustments']);
            $summaryStats = $reconciliation->getSummaryStats();

            $metrics = [
                'progress' => [
                    'percent_complete' => $reconciliation->percent_complete,
                    'matched_lines' => $summaryStats['statement_lines']['matched'],
                    'total_lines' => $summaryStats['statement_lines']['total'],
                    'unmatched_lines' => $summaryStats['statement_lines']['unmatched'],
                ],
                'variance' => [
                    'amount' => $reconciliation->variance,
                    'formatted' => $reconciliation->formatted_variance,
                    'status' => $reconciliation->variance_status,
                    'is_balanced' => abs($reconciliation->variance) <= 0.01,
                ],
                'activity' => [
                    'total_matches' => $summaryStats['matches']['total'],
                    'auto_matches' => $summaryStats['matches']['auto_matched'],
                    'manual_matches' => $summaryStats['matches']['manual_matches'],
                    'total_adjustments' => $summaryStats['adjustments']['total'],
                ],
                'timeline' => [
                    'started_at' => $reconciliation->started_at?->toISOString(),
                    'completed_at' => $reconciliation->completed_at?->toISOString(),
                    'locked_at' => $reconciliation->locked_at?->toISOString(),
                    'active_duration' => $reconciliation->active_duration,
                ],
                'status' => [
                    'current' => $reconciliation->status,
                    'can_be_edited' => $reconciliation->canBeEdited(),
                    'can_be_completed' => $reconciliation->canBeCompleted(),
                    'can_be_locked' => $reconciliation->canBeLocked(),
                    'can_be_reopened' => $reconciliation->canBeReopened(),
                ],
            ];

            return response()->json([
                'reconciliation_id' => $reconciliation->id,
                'metrics' => $metrics,
                'updated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Get available reports for multiple reconciliations (bulk operation).
     */
    public function bulkReports(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'reconciliation_ids' => 'required|array',
                'reconciliation_ids.*' => 'uuid',
                'report_type' => 'required|in:summary,variance,audit',
                'format' => 'required|in:json,pdf,csv',
            ]);

            $reconciliationIds = $request->get('reconciliation_ids');
            $reportType = $request->get('report_type');
            $format = $request->get('format');

            // Validate user has access to all requested reconciliations
            $reconciliations = BankReconciliation::whereIn('id', $reconciliationIds)
                ->where('company_id', Auth::user()->current_company_id)
                ->get();

            if ($reconciliations->count() !== count($reconciliationIds)) {
                return response()->json([
                    'message' => 'Some reconciliations not found or access denied',
                ], 403);
            }

            $reportService = new BankReconciliationReportService;
            $reports = [];

            foreach ($reconciliations as $reconciliation) {
                try {
                    switch ($reportType) {
                        case 'summary':
                            $report = $reportService->generateSummaryReport($reconciliation);
                            break;
                        case 'variance':
                            $report = $reportService->generateVarianceAnalysis($reconciliation);
                            break;
                        case 'audit':
                            $report = $reportService->generateAuditTrail($reconciliation);
                            break;
                    }

                    $reports[] = [
                        'reconciliation_id' => $reconciliation->id,
                        'statement_period' => $reconciliation->statement->statement_period,
                        'status' => $reconciliation->status,
                        'report' => $format === 'json' ? $report : 'Generated file ready for download',
                    ];

                } catch (\Exception $e) {
                    $reports[] = [
                        'reconciliation_id' => $reconciliation->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Log bulk operation
            $subscriber = new BankReconciliationAuditSubscriber;
            $subscriber->logBulkOperation("bulk_report_{$reportType}", $reconciliationIds);

            return response()->json([
                'report_type' => $reportType,
                'format' => $format,
                'total_reconciliations' => count($reconciliationIds),
                'successful_reports' => count(array_filter($reports, fn ($r) => ! isset($r['error']))),
                'reports' => $reports,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() === 422 ? 422 : 500);
        }
    }

    /**
     * Get MIME type based on file extension.
     */
    private function getMimeType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}
