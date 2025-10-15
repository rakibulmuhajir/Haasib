<?php

namespace Modules\Accounting\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Payment;
use Modules\Accounting\Domain\Payments\Services\PaymentQueryService;

class PaymentAuditController extends Controller
{
    public function __construct(
        private PaymentQueryService $queryService
    ) {}

    /**
     * Get audit trail for a specific payment.
     */
    public function show(Request $request, string $paymentId): JsonResponse
    {
        $this->authorize('accounting.payments.view');

        try {
            $payment = Payment::findOrFail($paymentId);
            
            $auditTrail = $this->queryService->getPaymentAuditTrail($paymentId, [
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'actions' => $request->get('actions'),
                'actor_types' => $request->get('actor_types'),
            ]);

            return response()->json([
                'payment_id' => $paymentId,
                'payment_number' => $payment->payment_number,
                'audit_trail' => $auditTrail,
                'total_entries' => count($auditTrail),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Payment not found',
                'message' => 'The requested payment could not be found.',
            ], 404);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve audit trail',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audit trail for all payments in a company.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('accounting.payments.view');

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement("SET app.current_company = ?", [$companyId]);
            }

            $filters = [
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'actions' => $request->get('actions'),
                'actor_types' => $request->get('actor_types'),
                'payment_methods' => $request->get('payment_methods'),
                'payment_statuses' => $request->get('payment_statuses'),
                'entity_id' => $request->get('entity_id'),
                'min_amount' => $request->get('min_amount'),
                'max_amount' => $request->get('max_amount'),
                'search' => $request->get('search'),
            ];

            $pagination = [
                'page' => $request->get('page', 1),
                'limit' => min($request->get('limit', 50), 100),
                'sort_by' => $request->get('sort_by', 'timestamp'),
                'sort_direction' => $request->get('sort_direction', 'desc'),
            ];

            $result = $this->queryService->getCompanyAuditTrail($filters, $pagination);

            return response()->json([
                'audit_trail' => $result['data'],
                'pagination' => [
                    'current_page' => $result['current_page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'last_page' => $result['last_page'],
                ],
                'filters_applied' => array_filter($filters, fn($v) => $v !== null),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve audit trail',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get bank reconciliation audit trail.
     */
    public function reconciliation(Request $request): JsonResponse
    {
        $this->authorize('accounting.payments.reconcile');

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement("SET app.current_company = ?", [$companyId]);
            }

            $filters = [
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'payment_number' => $request->get('payment_number'),
                'reconciled_only' => $request->get('reconciled_only'),
                'unreconciled_only' => $request->get('unreconciled_only'),
            ];

            $reconciliationData = $this->queryService->getBankReconciliationAudit($filters);

            return response()->json([
                'reconciliation_audit' => $reconciliationData,
                'total_payments' => count($reconciliationData),
                'reconciled_count' => count(array_filter($reconciliationData, fn($p) => $p['reconciled'])),
                'filters_applied' => array_filter($filters, fn($v) => $v !== null),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve reconciliation audit',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audit statistics and metrics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $this->authorize('accounting.payments.view');

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement("SET app.current_company = ?", [$companyId]);
            }

            $dateRange = [
                'start_date' => $request->get('start_date', now()->subDays(30)->toDateString()),
                'end_date' => $request->get('end_date', now()->toDateString()),
            ];

            $metrics = $this->queryService->getAuditMetrics($dateRange);

            return response()->json([
                'metrics' => $metrics,
                'date_range' => $dateRange,
                'generated_at' => now()->toISOString(),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to generate audit metrics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}