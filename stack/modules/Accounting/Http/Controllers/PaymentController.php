<?php

namespace Modules\Accounting\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * List all payments for the current company.
     */
    public function list(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $query = Payment::where('company_id', $company->id)
                ->with(['customer', 'allocations.invoice']);

            // Apply filters
            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            if ($request->has('date_from')) {
                $query->whereDate('payment_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('payment_date', '<=', $request->date_to);
            }

            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('reference', 'ilike', "%{$search}%")
                        ->orWhere('notes', 'ilike', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'ilike', "%{$search}%");
                        });
                });
            }

            // Sort
            $sortBy = $request->input('sort_by', 'payment_date');
            $sortDirection = $request->input('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Paginate
            $perPage = min($request->input('per_page', 15), 100);
            $payments = $query->paginate($perPage);

            return response()->json([
                'data' => $payments->items(),
                'pagination' => [
                    'total' => $payments->total(),
                    'per_page' => $payments->perPage(),
                    'current_page' => $payments->currentPage(),
                    'last_page' => $payments->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve payments',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new payment.
     */
    public function create(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'currency_code' => 'nullable|string|size:3',
            'exchange_rate' => 'nullable|numeric|min:0',
            'auto_allocate' => 'boolean',
            'allocations' => 'array',
            'allocations.*.invoice_id' => 'required_with:allocations|exists:invoices,id',
            'allocations.*.amount' => 'required_with:allocations|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $company = $request->user()->currentCompany();
            $validated = $validator->validated();

            // Verify customer belongs to company
            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($validated['customer_id']);

            // Verify allocations belong to customer invoices
            if (! empty($validated['allocations'])) {
                foreach ($validated['allocations'] as $allocation) {
                    $invoice = Invoice::where('company_id', $company->id)
                        ->where('customer_id', $customer->id)
                        ->findOrFail($allocation['invoice_id']);
                }

                // Verify total allocation doesn't exceed payment amount
                $totalAllocation = array_sum(array_column($validated['allocations'], 'amount'));
                if ($totalAllocation > $validated['amount']) {
                    return response()->json([
                        'message' => 'Total allocation amount cannot exceed payment amount',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
            }

            return DB::transaction(function () use ($validated, $company, $customer) {
                // Create payment
                $payment = Payment::create([
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                    'amount' => $validated['amount'],
                    'payment_date' => $validated['payment_date'],
                    'payment_method' => $validated['payment_method'],
                    'reference' => $validated['reference'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'currency_code' => $validated['currency_code'] ?? $company->currency_code,
                    'exchange_rate' => $validated['exchange_rate'] ?? 1,
                    'status' => 'received',
                ]);

                // Process allocations
                if (! empty($validated['allocations'])) {
                    foreach ($validated['allocations'] as $allocationData) {
                        $payment->allocations()->create([
                            'invoice_id' => $allocationData['invoice_id'],
                            'amount' => $allocationData['amount'],
                            'allocated_at' => now(),
                        ]);

                        // Update invoice status if fully paid
                        $invoice = Invoice::find($allocationData['invoice_id']);
                        $totalAllocated = $invoice->payments()->sum('amount');
                        if ($totalAllocated >= $invoice->total_amount) {
                            $invoice->update(['status' => 'paid']);
                        }
                    }
                } elseif ($validated['auto_allocate'] ?? false) {
                    // Auto-allocate to oldest unpaid invoices
                    $this->autoAllocatePayment($payment);
                }

                return response()->json([
                    'message' => 'Payment created successfully',
                    'data' => $payment->load(['customer', 'allocations.invoice']),
                ], Response::HTTP_CREATED);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific payment.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->with(['customer', 'allocations.invoice'])
                ->findOrFail($id);

            // Calculate unallocated amount
            $allocatedAmount = $payment->allocations()->sum('amount');
            $payment->unallocated_amount = $payment->amount - $allocatedAmount;

            return response()->json([
                'data' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Payment not found',
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update a payment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|required|numeric|min:0.01',
            'payment_date' => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:draft,received,pending,failed,void,refunded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            // Prevent updates if payment has been allocated
            if ($payment->allocations()->exists()) {
                return response()->json([
                    'message' => 'Cannot update payment that has been allocated',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $validated = $validator->validated();
            $payment->update($validated);

            return response()->json([
                'message' => 'Payment updated successfully',
                'data' => $payment->load(['customer', 'allocations.invoice']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update payment',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a payment.
     */
    public function delete(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->with(['allocations'])
                ->findOrFail($id);

            // Prevent deletion if payment has been allocated
            if ($payment->allocations()->exists()) {
                return response()->json([
                    'message' => 'Cannot delete payment that has been allocated',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return DB::transaction(function () use ($payment) {
                $payment->delete();

                return response()->json([
                    'message' => 'Payment deleted successfully',
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete payment',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Refund a payment.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refund_amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
            'refund_date' => 'required|date',
            'refund_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->with(['allocations'])
                ->findOrFail($id);

            $validated = $validator->validated();

            // Check refund amount doesn't exceed payment amount
            $totalRefunded = $payment->refunds()->sum('amount') + $validated['refund_amount'];
            if ($totalRefunded > $payment->amount) {
                return response()->json([
                    'message' => 'Total refund amount cannot exceed original payment amount',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return DB::transaction(function () use ($payment, $validated) {
                // Create refund record
                $refund = $payment->refunds()->create([
                    'amount' => $validated['refund_amount'],
                    'reason' => $validated['reason'],
                    'refund_date' => $validated['refund_date'],
                    'refund_method' => $validated['refund_method'],
                    'reference' => $validated['reference'] ?? null,
                ]);

                // Update payment status if fully refunded
                if ($totalRefunded >= $payment->amount) {
                    $payment->update(['status' => 'refunded']);
                }

                // Reverse allocations proportionally
                $this->reverseAllocationsOnRefund($payment, $validated['refund_amount']);

                return response()->json([
                    'message' => 'Payment refunded successfully',
                    'data' => $refund,
                ]);
            });

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refund payment',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get payment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            // Base query
            $query = Payment::where('company_id', $company->id);

            // Date range filtering
            if ($request->has('date_from')) {
                $query->whereDate('payment_date', '>=', $request->date_from);
            }
            if ($request->has('date_to')) {
                $query->whereDate('payment_date', '<=', $request->date_to);
            }

            $total = $query->count();
            $totalAmount = $query->sum('amount');

            // Status breakdown
            $statusBreakdown = $query->selectRaw('status, COUNT(*) as count, SUM(amount) as amount')
                ->groupBy('status')
                ->get()
                ->keyBy('status');

            // Payment method breakdown
            $methodBreakdown = $query->selectRaw('payment_method, COUNT(*) as count, SUM(amount) as amount')
                ->groupBy('payment_method')
                ->get()
                ->keyBy('payment_method');

            // Recent payments (last 30 days)
            $recentPayments = $query->whereDate('payment_date', '>=', now()->subDays(30))
                ->count();

            // Unallocated amount
            $allocatedAmount = $query->whereHas('allocations')
                ->with(['allocations' => function ($q) {
                    $q->select('payment_id', DB::raw('SUM(amount) as total'))
                        ->groupBy('payment_id');
                }])
                ->get()
                ->sum(function ($payment) {
                    return $payment->allocations->first()->total ?? 0;
                });

            $unallocatedAmount = $totalAmount - $allocatedAmount;

            return response()->json([
                'data' => [
                    'total_payments' => $total,
                    'total_amount' => $totalAmount,
                    'recent_payments_30_days' => $recentPayments,
                    'unallocated_amount' => $unallocatedAmount,
                    'status_breakdown' => $statusBreakdown,
                    'payment_method_breakdown' => $methodBreakdown,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve payment statistics',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Auto-allocate payment to oldest unpaid invoices.
     */
    private function autoAllocatePayment(Payment $payment): void
    {
        $unpaidInvoices = Invoice::where('company_id', $payment->company_id)
            ->where('customer_id', $payment->customer_id)
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();

        $remainingAmount = $payment->amount;

        foreach ($unpaidInvoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            $invoiceBalance = $invoice->total_amount - $invoice->payments()->sum('amount');
            $allocateAmount = min($remainingAmount, $invoiceBalance);

            if ($allocateAmount > 0) {
                $payment->allocations()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $allocateAmount,
                    'allocated_at' => now(),
                ]);

                // Update invoice status if fully paid
                $newTotalAllocated = $invoice->payments()->sum('amount');
                if ($newTotalAllocated >= $invoice->total_amount) {
                    $invoice->update(['status' => 'paid']);
                }

                $remainingAmount -= $allocateAmount;
            }
        }
    }

    /**
     * Reverse allocations when refunding a payment.
     */
    private function reverseAllocationsOnRefund(Payment $payment, float $refundAmount): void
    {
        $allocations = $payment->allocations()->with('invoice')->get();

        if ($allocations->isEmpty()) {
            return;
        }

        $totalAllocated = $allocations->sum('amount');
        $refundRatio = $refundAmount / $payment->amount;

        foreach ($allocations as $allocation) {
            $refundAllocationAmount = $allocation->amount * $refundRatio;

            // Create refund allocation record
            $allocation->refunds()->create([
                'amount' => $refundAllocationAmount,
                'refunded_at' => now(),
            ]);

            // Update invoice status if no longer fully paid
            $invoice = $allocation->invoice;
            $totalAllocatedAfterRefund = $invoice->payments()
                ->whereHas('allocations', function ($q) {
                    $q->whereDoesntHave('refunds');
                })
                ->sum('amount');

            if ($totalAllocatedAfterRefund < $invoice->total_amount && $invoice->status === 'paid') {
                $invoice->update(['status' => 'posted']);
            }
        }
    }
}
