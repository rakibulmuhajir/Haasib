<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payment\AllocatePaymentRequest;
use App\Http\Requests\Api\Payment\BulkPaymentRequest;
use App\Http\Requests\Api\Payment\StorePaymentRequest;
use App\Http\Requests\Api\Payment\UpdatePaymentRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\CurrencyService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentApiController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private CurrencyService $currencyService
    ) {}

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $query = Payment::where('company_id', $company->id)
            ->with(['customer', 'currency', 'allocations.invoice']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        $payments = $query->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
            ],
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'customer_id' => $request->customer_id,
                'payment_method' => $request->payment_method,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'currency_id' => $request->currency_id,
            ],
        ]);
    }

    /**
     * Store a newly created payment.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $customer = $company->customers()->findOrFail($request->customer_id);
            $currency = $request->currency_id ? $company->currencies()->findOrFail($request->currency_id) : null;

            $payment = $this->paymentService->processPayment(
                company: $company,
                customer: $customer,
                amount: $request->amount,
                paymentMethod: $request->payment_method,
                paymentReference: $request->payment_reference,
                paymentDate: $request->payment_date,
                currency: $currency,
                exchangeRate: $request->exchange_rate,
                notes: $request->notes,
                autoAllocate: $request->auto_allocate ?? false,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return response()->json([
                'success' => true,
                'data' => $payment->load(['customer', 'currency', 'allocations.invoice']),
                'message' => 'Payment processed successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to process payment', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to process payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->company;

        $payment = Payment::where('company_id', $company->id)
            ->with(['customer', 'currency', 'allocations.invoice', 'allocations.payment'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $payment,
            'metadata' => [
                'unallocated_amount' => $payment->getUnallocatedAmount()->getAmount()->toFloat(),
                'formatted_unallocated_amount' => $this->currencyService->formatMoney($payment->getUnallocatedAmount()),
                'allocation_summary' => $payment->getAllocationSummary(),
                'can_be_voided' => $payment->canBeVoided(),
                'can_be_refunded' => $payment->canBeRefunded(),
                'age_in_days' => $payment->getAgeInDays(),
            ],
        ]);
    }

    /**
     * Update the specified payment.
     */
    public function update(UpdatePaymentRequest $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->findOrFail($id);

            $updatedPayment = $this->paymentService->updatePayment(
                payment: $payment,
                paymentMethod: $request->payment_method,
                paymentReference: $request->payment_reference,
                paymentDate: $request->payment_date,
                notes: $request->notes
            );

            return response()->json([
                'success' => true,
                'data' => $updatedPayment->load(['customer', 'currency', 'allocations.invoice']),
                'message' => 'Payment updated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to update payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->findOrFail($id);

            $this->paymentService->deletePayment($payment, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to delete payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Allocate payment to invoices.
     */
    public function allocate(AllocatePaymentRequest $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->findOrFail($id);

            $allocation = $this->paymentService->allocatePayment(
                payment: $payment,
                invoiceId: $request->invoice_id,
                amount: $request->amount,
                allocationDate: $request->allocation_date,
                notes: $request->notes
            );

            return response()->json([
                'success' => true,
                'data' => $allocation->load(['payment', 'invoice']),
                'message' => 'Payment allocated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to allocate payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to allocate payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto-allocate payment to outstanding invoices.
     */
    public function autoAllocate(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->findOrFail($id);

            $allocations = $this->paymentService->autoAllocatePayment($payment);

            return response()->json([
                'success' => true,
                'data' => [
                    'allocations' => $allocations->load(['payment', 'invoice']),
                    'total_allocated' => $allocations->sum('amount'),
                    'remaining_unallocated' => $payment->getUnallocatedAmount()->getAmount()->toFloat(),
                ],
                'message' => 'Payment auto-allocated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to auto-allocate payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to auto-allocate payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void payment.
     */
    public function void(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'reason' => 'required|string|min:3',
            ]);

            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->findOrFail($id);

            $voidedPayment = $this->paymentService->voidPayment($payment, $request->reason);

            return response()->json([
                'success' => true,
                'data' => $voidedPayment,
                'message' => 'Payment voided successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to void payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund payment.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'reason' => 'required|string|min:3',
            ]);

            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->findOrFail($id);

            $refund = $this->paymentService->refundPayment(
                payment: $payment,
                amount: $request->amount,
                reason: $request->reason,
                refundMethod: $request->refund_method
            );

            return response()->json([
                'success' => true,
                'data' => $refund,
                'message' => 'Payment refunded successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to refund payment',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations on payments.
     */
    public function bulk(BulkPaymentRequest $request): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $results = [];

            switch ($request->action) {
                case 'delete':
                    $payments = Payment::where('company_id', $company->id)
                        ->whereIn('id', $request->payment_ids)
                        ->get();

                    foreach ($payments as $payment) {
                        try {
                            $this->paymentService->deletePayment($payment, $request->reason);
                            $results[] = ['id' => $payment->id, 'success' => true];
                        } catch (\Exception $e) {
                            $results[] = [
                                'id' => $payment->id,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                    break;

                case 'void':
                    foreach ($request->payment_ids as $id) {
                        try {
                            $payment = Payment::where('company_id', $company->id)->findOrFail($id);
                            $this->paymentService->voidPayment($payment, $request->reason);
                            $results[] = ['id' => $id, 'success' => true];
                        } catch (\Exception $e) {
                            $results[] = [
                                'id' => $id,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                    break;

                case 'auto_allocate':
                    foreach ($request->payment_ids as $id) {
                        try {
                            $payment = Payment::where('company_id', $company->id)->findOrFail($id);
                            $allocations = $this->paymentService->autoAllocatePayment($payment);
                            $results[] = [
                                'id' => $id,
                                'success' => true,
                                'allocations_count' => $allocations->count(),
                                'total_allocated' => $allocations->sum('amount'),
                            ];
                        } catch (\Exception $e) {
                            $results[] = [
                                'id' => $id,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                    break;

                default:
                    throw new \InvalidArgumentException('Invalid bulk action');
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'action' => $request->action,
                    'results' => $results,
                    'processed_count' => count($results),
                    'success_count' => count(array_filter($results, fn ($r) => $r['success'])),
                ],
                'message' => 'Bulk operation completed',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to perform bulk operation', [
                'error' => $e->getMessage(),
                'action' => $request->action,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Bulk operation failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        $startDate = $request->date_from;
        $endDate = $request->date_to;

        $statistics = $this->paymentService->getPaymentStatistics($company, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'filters' => [
                'date_from' => $startDate,
                'date_to' => $endDate,
            ],
        ]);
    }

    /**
     * Get customer payment summary.
     */
    public function customerSummary(Request $request, string $customerId): JsonResponse
    {
        $company = $request->user()->company;
        $customer = $company->customers()->findOrFail($customerId);

        $summary = $this->paymentService->getCustomerPaymentSummary($customer);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get payment allocation suggestions.
     */
    public function allocationSuggestions(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->company;
        $payment = Payment::where('company_id', $company->id)->findOrFail($id);

        $suggestions = $this->paymentService->getAllocationSuggestions($payment);

        return response()->json([
            'success' => true,
            'data' => $suggestions,
        ]);
    }
}
