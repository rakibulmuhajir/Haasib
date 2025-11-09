<?php

namespace Modules\Accounting\Http\Controllers\Api;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Domain\Payments\Actions\CreatePaymentBatchAction;
use Modules\Accounting\Domain\Payments\Services\PaymentReceiptService;

class PaymentController extends Controller
{
    /**
     * Create a new payment receipt.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('accounting.payments.create');

        $validated = $request->validate([
            'entity_id' => 'required|uuid|exists:hrm.customers,customer_id',
            'payment_method' => 'required|string|in:cash,bank_transfer,card,cheque,other',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'required|uuid|exists:public.currencies,id',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'auto_allocate' => 'boolean',
            'allocation_strategy' => 'nullable|string|in:fifo,proportional,overdue_first,largest_first,percentage_based,custom_priority',
            'allocation_options' => 'nullable|array',
        ]);

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            // Add user context
            $validated['company_id'] = $companyId;
            $validated['created_by_user_id'] = Auth::id();

            // Dispatch through command bus
            $result = Bus::dispatch('payment.create', $validated);

            $payment = Payment::with(['entity', 'currency'])
                ->findOrFail($result['payment_id']);

            return response()->json([
                'payment' => [
                    'id' => $payment->payment_id,
                    'payment_number' => $payment->payment_number,
                    'entity_id' => $payment->entity_id,
                    'entity_name' => $payment->entity?->name,
                    'amount' => $payment->amount,
                    'currency' => [
                        'id' => $payment->currency->id,
                        'code' => $payment->currency->code,
                        'symbol' => $payment->currency->symbol,
                    ],
                    'payment_method' => $payment->payment_method,
                    'payment_date' => $payment->payment_date,
                    'reference_number' => $payment->reference_number,
                    'status' => $payment->status,
                    'notes' => $payment->notes,
                    'created_at' => $payment->created_at,
                ],
                'remaining_amount' => $payment->remaining_amount,
                'is_fully_allocated' => $payment->is_fully_allocated,
                'message' => 'Payment recorded successfully',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Payment creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment details with allocation summary.
     */
    public function show(string $paymentId): JsonResponse
    {
        $this->authorize('accounting.payments.view');

        try {
            $payment = Payment::with([
                'entity',
                'currency',
                'allocations.invoice',
                'creator',
            ])->findOrFail($paymentId);

            return response()->json([
                'payment' => [
                    'id' => $payment->payment_id,
                    'payment_number' => $payment->payment_number,
                    'entity' => [
                        'id' => $payment->entity_id,
                        'name' => $payment->entity?->name,
                        'type' => $payment->entity_type,
                    ],
                    'amount' => $payment->amount,
                    'currency' => [
                        'id' => $payment->currency->id,
                        'code' => $payment->currency->code,
                        'symbol' => $payment->currency->symbol,
                    ],
                    'payment_method' => $payment->payment_method,
                    'payment_method_label' => $payment->payment_method_label,
                    'payment_date' => $payment->payment_date,
                    'reference_number' => $payment->reference_number,
                    'status' => $payment->status,
                    'status_label' => $payment->status_label,
                    'notes' => $payment->notes,
                    'reconciled' => $payment->reconciled,
                    'reconciled_date' => $payment->reconciled_date,
                    'created_by' => $payment->creator?->name,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                ],
                'allocation_summary' => $payment->allocation_summary,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Payment not found',
                'message' => 'The requested payment could not be found.',
            ], 404);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Allocate payment to invoices manually.
     */
    public function allocate(Request $request, string $paymentId): JsonResponse
    {
        $this->authorize('accounting.payments.allocate');

        $validated = $request->validate([
            'allocations' => 'required|array|min:1',
            'allocations.*.invoice_id' => 'required|uuid|exists:pgsql.acct.invoices,invoice_id',
            'allocations.*.amount' => 'required|numeric|min:0.01',
            'allocations.*.notes' => 'nullable|string',
        ]);

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            // Dispatch through command bus
            $result = Bus::dispatch('payment.allocate', [
                'payment_id' => $paymentId,
                'allocations' => $validated['allocations'],
            ]);

            return response()->json([
                'payment_id' => $result['payment_id'],
                'allocations_created' => $result['allocations_created'],
                'total_allocated' => $result['total_allocated'],
                'remaining_amount' => $result['remaining_amount'],
                'payment_status' => $result['payment_status'],
                'is_fully_allocated' => $result['is_fully_allocated'],
                'allocations' => $result['allocations'],
                'message' => 'Payment allocated successfully',
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Allocation failed',
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Payment allocation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-allocate payment to invoices.
     */
    public function autoAllocate(Request $request, string $paymentId): JsonResponse
    {
        $this->authorize('accounting.payments.allocate');

        $validated = $request->validate([
            'strategy' => 'required|string|in:fifo,proportional,overdue_first,largest_first,percentage_based,custom_priority',
            'options' => 'nullable|array',
        ]);

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            // Dispatch through command bus
            $result = Bus::dispatch('payment.allocate.auto', [
                'payment_id' => $paymentId,
                'strategy' => $validated['strategy'],
                'options' => $validated['options'] ?? [],
            ]);

            return response()->json([
                'payment_id' => $result['payment_id'],
                'strategy_used' => $result['strategy_used'],
                'allocations_created' => $result['allocations_created'],
                'total_allocated' => $result['total_allocated'],
                'remaining_amount' => $result['remaining_amount'],
                'payment_status' => $result['payment_status'],
                'is_fully_allocated' => $result['is_fully_allocated'],
                'allocations' => $result['allocations'],
                'message' => 'Auto-allocation completed successfully',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Auto-allocation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List allocations for a payment.
     */
    public function allocations(string $paymentId): JsonResponse
    {
        $this->authorize('accounting.payments.view');

        try {
            $payment = Payment::findOrFail($paymentId);

            $allocations = $payment->allocations()
                ->with(['invoice'])
                ->orderBy('created_at')
                ->get()
                ->map(function ($allocation) {
                    return [
                        'id' => $allocation->allocation_id,
                        'invoice_id' => $allocation->invoice_id,
                        'invoice_number' => $allocation->invoice?->invoice_number,
                        'invoice_due_date' => $allocation->invoice?->due_date,
                        'allocated_amount' => $allocation->allocated_amount,
                        'allocation_method' => $allocation->allocation_method,
                        'allocation_date' => $allocation->allocation_date?->format('Y-m-d'),
                        'notes' => $allocation->notes,
                        'status' => $allocation->status,
                        'created_at' => $allocation->created_at,
                    ];
                });

            return response()->json($allocations);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Payment not found',
                'message' => 'The requested payment could not be found.',
            ], 404);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve allocations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download payment receipt.
     */
    public function receipt(Request $request, string $paymentId): Response
    {
        $this->authorize('accounting.payments.view');

        try {
            $payment = Payment::with(['entity', 'currency', 'allocations.invoice'])
                ->findOrFail($paymentId);

            $format = $request->get('format', 'pdf');
            $receiptService = new PaymentReceiptService;

            if ($format === 'json') {
                $receiptData = $receiptService->generateReceiptData($payment);

                return response()->json($receiptData);
            }

            if ($format === 'pdf') {
                $pdfContent = $receiptService->generatePdfReceipt($payment);

                return response($pdfContent)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'attachment; filename="receipt-R-'.$payment->payment_number.'.pdf"');
            }

            return response()->json([
                'error' => 'Invalid format',
                'message' => 'Supported formats are: pdf, json',
            ], 400);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Payment not found',
                'message' => 'The requested payment could not be found.',
            ], 404);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to generate receipt',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reverse a payment.
     */
    public function reverse(Request $request, string $paymentId): JsonResponse
    {
        $this->authorize('accounting.payments.reverse');

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'amount' => 'nullable|numeric|min:0.01',
            'method' => 'required|string|in:void,refund,chargeback',
            'metadata' => 'nullable|array',
        ]);

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            // Add user context
            $validated['company_id'] = $companyId;
            $validated['created_by_user_id'] = Auth::id();

            // Dispatch through command bus
            $result = Bus::dispatch('payment.reverse', array_merge($validated, ['payment_id' => $paymentId]));

            return response()->json($result, 202);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Payment not found',
                'message' => 'The requested payment could not be found.',
            ], 404);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Payment reversal failed',
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Payment reversal failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reverse a specific allocation.
     */
    public function reverseAllocation(Request $request, string $paymentId, string $allocationId): JsonResponse
    {
        $this->authorize('accounting.payments.reverse');

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
            'refund_amount' => 'nullable|numeric|min:0.01',
        ]);

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            // Add user context
            $validated['company_id'] = $companyId;
            $validated['created_by_user_id'] = Auth::id();

            // Verify allocation belongs to the specified payment
            $allocation = PaymentAllocation::where('payment_id', $paymentId)
                ->findOrFail($allocationId);

            // Dispatch through command bus
            $result = Bus::dispatch('payment.allocation.reverse', array_merge($validated, ['allocation_id' => $allocationId]));

            return response()->json($result, 202);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Allocation not found',
                'message' => 'The requested allocation could not be found.',
            ], 404);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Allocation reversal failed',
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Allocation reversal failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new payment batch.
     */
    public function createBatch(Request $request): JsonResponse
    {
        $this->authorize('accounting.payments.create');

        // Check idempotency
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $existingBatch = Cache::get("batch_idempotency:{$idempotencyKey}");
            if ($existingBatch) {
                return response()->json([
                    'error' => 'Duplicate batch creation',
                    'message' => 'A batch with this idempotency key is already being processed',
                    'existing_batch_id' => $existingBatch,
                ], 409);
            }
        }

        $validated = $request->validate([
            'source_type' => 'required|string|in:manual,csv_import,bank_feed',
            'file' => 'required_if:source_type,csv_import|file|mimes:csv,txt|max:10240',
            'entries' => 'required_if:source_type,manual,bank_feed|array|min:1',
            'entries.*.entity_id' => 'required|uuid|exists:hrm.customers,customer_id',
            'entries.*.payment_method' => 'required|string|in:cash,bank_transfer,card,cheque,other',
            'entries.*.amount' => 'required|numeric|min:0.01',
            'entries.*.currency_id' => 'required|uuid|exists:public.currencies,id',
            'entries.*.payment_date' => 'required|date',
            'entries.*.reference_number' => 'nullable|string|max:100',
            'entries.*.notes' => 'nullable|string',
            'entries.*.auto_allocate' => 'boolean',
            'entries.*.allocation_strategy' => 'nullable|string|in:fifo,proportional,overdue_first,largest_first,percentage_based,custom_priority',
            'notes' => 'nullable|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            // Add context to validated data
            $validated['company_id'] = $companyId;
            $validated['created_by_user_id'] = Auth::id();

            // Store idempotency key
            if ($idempotencyKey) {
                Cache::put("batch_idempotency:{$idempotencyKey}", 'processing', 3600); // 1 hour
            }

            // Create batch through action
            $action = new CreatePaymentBatchAction;
            $result = $action->execute($validated);

            // Store actual batch ID for idempotency
            if ($idempotencyKey) {
                Cache::put("batch_idempotency:{$idempotencyKey}", $result['batch_id'], 3600);
            }

            return response()->json($result, 202);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Clear idempotency cache on validation error
            if ($idempotencyKey) {
                Cache::forget("batch_idempotency:{$idempotencyKey}");
            }

            return response()->json([
                'error' => 'Validation failed',
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            // Clear idempotency cache on error
            if ($idempotencyKey) {
                Cache::forget("batch_idempotency:{$idempotencyKey}");
            }

            return response()->json([
                'error' => 'Batch creation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment batch details and status.
     */
    public function getBatch(Request $request, string $batchId): JsonResponse
    {
        $this->authorize('accounting.payments.view');

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            $batch = PaymentBatch::with(['creator', 'payments'])
                ->findOrFail($batchId);

            // Build response
            $response = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'status' => $batch->status,
                'status_label' => $batch->status_label,
                'receipt_count' => $batch->receipt_count,
                'total_amount' => $batch->total_amount,
                'currency' => $batch->currency,
                'created_at' => $batch->created_at->toISOString(),
                'processing_started_at' => $batch->processing_started_at?->toISOString(),
                'processed_at' => $batch->processed_at?->toISOString(),
                'processing_finished_at' => $batch->processing_finished_at?->toISOString(),
                'estimated_completion' => $batch->estimated_completion,
                'progress_percentage' => $batch->progress_percentage,
                'created_by' => $batch->creator?->name,
                'notes' => $batch->notes,
                'metadata' => $batch->metadata,
                'source_type' => $batch->source_type,
            ];

            // Add error details if failed
            if ($batch->hasFailed()) {
                $response['error_type'] = $batch->getErrorType();
                $response['error_details'] = $batch->getErrorDetails();
            }

            // Add processing statistics
            $metadata = $batch->metadata ?? [];
            if (isset($metadata['processed_count']) || isset($metadata['failed_count'])) {
                $response['processed_count'] = $metadata['processed_count'] ?? 0;
                $response['failed_count'] = $metadata['failed_count'] ?? 0;
            }

            // Add payment list if requested
            if ($request->get('include_payments', false)) {
                $response['payments'] = $batch->payments()->get()->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'payment_number' => $payment->payment_number,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency->code,
                        'payment_method' => $payment->payment_method,
                        'payment_date' => $payment->payment_date,
                        'status' => $payment->status,
                        'entity_name' => $payment->entity?->name,
                        'created_at' => $payment->created_at->toISOString(),
                    ];
                })->toArray();
            }

            return response()->json($response);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Batch not found',
                'message' => 'The requested batch could not be found.',
            ], 404);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve batch',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List payment batches for the company.
     */
    public function listBatches(Request $request): JsonResponse
    {
        $this->authorize('accounting.payments.view');

        try {
            // Set company context for RLS
            $companyId = $request->header('X-Company-Id');
            if ($companyId) {
                DB::statement('SET app.current_company = ?', [$companyId]);
            }

            $validated = $request->validate([
                'status' => 'nullable|string|in:pending,processing,completed,failed,archived',
                'source_type' => 'nullable|string|in:manual,csv_import,bank_feed',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'page' => 'nullable|integer|min:1',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $query = PaymentBatch::with(['creator'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if (isset($validated['status'])) {
                $query->byStatus($validated['status']);
            }

            if (isset($validated['date_from'])) {
                $query->where('created_at', '>=', $validated['date_from']);
            }

            if (isset($validated['date_to'])) {
                $query->where('created_at', '<=', $validated['date_to'].' 23:59:59');
            }

            // Filter by source type in metadata
            if (isset($validated['source_type'])) {
                $query->whereJsonContains('metadata->source_type', $validated['source_type']);
            }

            // Pagination
            $limit = $validated['limit'] ?? 20;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            $total = $query->count();
            $batches = $query->offset($offset)->limit($limit)->get();

            $batchList = $batches->map(function ($batch) {
                return [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'status' => $batch->status,
                    'status_label' => $batch->status_label,
                    'source_type' => $batch->source_type,
                    'receipt_count' => $batch->receipt_count,
                    'total_amount' => $batch->total_amount,
                    'currency' => $batch->currency,
                    'progress_percentage' => $batch->progress_percentage,
                    'created_at' => $batch->created_at->toISOString(),
                    'processed_at' => $batch->processed_at?->toISOString(),
                    'created_by' => $batch->creator?->name,
                    'has_errors' => $batch->hasFailed(),
                ];
            });

            return response()->json([
                'data' => $batchList,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => ceil($total / $limit),
                ],
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to retrieve batches',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
