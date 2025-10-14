<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Payment\AllocatePaymentRequest;
use App\Http\Requests\Api\Payment\BulkPaymentRequest;
use App\Http\Requests\Api\Payment\StorePaymentRequest;
use App\Http\Requests\Api\Payment\UpdatePaymentRequest;
use App\Http\Responses\ApiResponder;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\CurrencyService;
use App\Services\PaymentService;
use App\Support\ServiceContextHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentApiController extends Controller
{
    use ApiResponder;

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

        return $this->ok(
            $payments->items(),
            null,
            [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
                'filters' => [
                    'search' => $request->search,
                    'status' => $request->status,
                    'customer_id' => $request->customer_id,
                    'payment_method' => $request->payment_method,
                    'date_from' => $request->date_from,
                    'date_to' => $request->date_to,
                    'currency_id' => $request->currency_id,
                ],
            ]
        );
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
            $context = ServiceContextHelper::fromRequest($request, $company->id);

            $payment = $this->paymentService->processIncomingPayment(
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
                idempotencyKey: $request->header('Idempotency-Key'),
                context: $context
            );

            return $this->ok($payment->load(['customer', 'currency', 'allocations.invoice']), 'Payment processed successfully', status: 201);

        } catch (\Exception $e) {
            Log::error('Failed to process payment', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'request_data' => $request->all(),
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to process payment', 500, ['message' => $e->getMessage()]);
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
            ->where('payment_id', $id)
            ->firstOrFail();

        return $this->ok(
            $payment,
            null,
            [
                'unallocated_amount' => $payment->getUnallocatedAmount()->getAmount()->toFloat(),
                'formatted_unallocated_amount' => $this->currencyService->formatMoney($payment->getUnallocatedAmount()),
                'allocation_summary' => $payment->getAllocationSummary(),
                'can_be_voided' => $payment->canBeVoided(),
                'can_be_refunded' => $payment->canBeRefunded(),
                'age_in_days' => $payment->getAgeInDays(),
            ]
        );
    }

    /**
     * Update the specified payment.
     */
    public function update(UpdatePaymentRequest $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();
            $context = ServiceContextHelper::fromRequest($request, $company->id);

            $updatedPayment = $this->paymentService->updatePayment(
                payment: $payment,
                paymentMethod: $request->payment_method,
                paymentReference: $request->payment_reference,
                paymentDate: $request->payment_date,
                notes: $request->notes,
                context: $context
            );

            return $this->ok(
                $updatedPayment->load(['customer', 'currency', 'allocations.invoice']),
                'Payment updated successfully'
            );

        } catch (\Exception $e) {
            Log::error('Failed to update payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to update payment', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();
            $context = ServiceContextHelper::fromRequest($request, $company->id);

            $this->paymentService->deletePayment($payment, $request->reason, $context);

            return $this->ok(null, 'Payment deleted successfully');

        } catch (\Exception $e) {
            Log::error('Failed to delete payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to delete payment', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Allocate payment to invoices.
     */
    public function allocate(AllocatePaymentRequest $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();
            $context = ServiceContextHelper::fromRequest($request, $company->id);

            $allocation = $this->paymentService->allocatePayment(
                payment: $payment,
                invoiceId: $request->invoice_id,
                amount: $request->amount,
                allocationDate: $request->allocation_date,
                notes: $request->notes,
                context: $context
            );

            return $this->ok($allocation->load(['payment', 'invoice']), 'Payment allocated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to allocate payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
                'request_data' => $request->all(),
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to allocate payment', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Auto-allocate payment to outstanding invoices.
     */
    public function autoAllocate(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->company;
            $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();
            $context = ServiceContextHelper::fromRequest($request, $company->id);

            $allocations = $this->paymentService->autoAllocatePayment($payment, $context);

            return $this->ok([
                'allocations' => $allocations->load(['payment', 'invoice']),
                'total_allocated' => $allocations->sum('amount'),
                'remaining_unallocated' => $payment->getUnallocatedAmount()->getAmount()->toFloat(),
            ], 'Payment auto-allocated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to auto-allocate payment', [
                'error' => $e->getMessage(),
                'payment_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to auto-allocate payment', 500, ['message' => $e->getMessage()]);
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
            $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();
            $context = ServiceContextHelper::fromRequest($request, $company->id);

            $voidedPayment = $this->paymentService->voidPayment($payment, $request->reason, $context);

            return $this->ok($voidedPayment, 'Payment voided successfully');

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to void payment', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * List allocations for a payment.
     */
    public function allocations(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->company;
        $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();

        $allocs = $payment->allocations()->with(['invoice'])->orderBy('created_at', 'desc')->get();

        return $this->ok($allocs);
    }

    /**
     * Void a single allocation.
     */
    public function voidAllocation(Request $request, string $paymentId, string $allocationId): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string']);

        $company = $request->user()->company;
        $payment = Payment::where('company_id', $company->id)->findOrFail($paymentId);
        $allocation = $payment->allocations()->where('allocation_id', $allocationId)->firstOrFail();

        $allocation->void($request->input('reason'));

        return $this->ok($allocation->fresh(['invoice', 'payment']), 'Allocation voided successfully');
    }

    /**
     * Refund a single allocation (partial or full).
     */
    public function refundAllocation(Request $request, string $paymentId, string $allocationId): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string',
        ]);

        $company = $request->user()->company;
        $payment = Payment::where('company_id', $company->id)->findOrFail($paymentId);
        $allocation = $payment->allocations()->where('allocation_id', $allocationId)->firstOrFail();

        $amount = \Brick\Money\Money::of($request->input('amount'), $payment->currency->code);
        $refund = $allocation->refund($amount, $request->input('reason'));

        return $this->ok($refund->load(['payment', 'invoice']), 'Allocation refunded successfully');
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
            $context = ServiceContextHelper::fromRequest($request, $company->id);

            $refund = $this->paymentService->refundPayment(
                payment: $payment,
                amount: $request->amount,
                reason: $request->reason,
                refundMethod: $request->refund_method,
                context: $context
            );

            return $this->ok($refund, 'Payment refunded successfully');

        } catch (\Exception $e) {
            return $this->fail('INTERNAL_ERROR', 'Failed to refund payment', 500, ['message' => $e->getMessage()]);
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
                        ->whereIn('payment_id', $request->payment_ids)
                        ->get();

                    foreach ($payments as $payment) {
                        try {
                            $context = ServiceContextHelper::fromRequest($request, $company->id);
                            $this->paymentService->deletePayment($payment, $request->reason, $context);
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
                            $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();
                            $context = ServiceContextHelper::fromRequest($request, $company->id);
                            $this->paymentService->voidPayment($payment, $request->reason, $context);
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
                            $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();
                            $context = ServiceContextHelper::fromRequest($request, $company->id);
                            $allocations = $this->paymentService->autoAllocatePayment($payment, $context);
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

            return $this->ok([
                'action' => $request->action,
                'results' => $results,
                'processed_count' => count($results),
                'success_count' => count(array_filter($results, fn ($r) => $r['success'])),
            ], 'Bulk operation completed');

        } catch (\Exception $e) {
            Log::error('Failed to perform bulk operation', [
                'error' => $e->getMessage(),
                'action' => $request->action,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Bulk operation failed', 500, ['message' => $e->getMessage()]);
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

        return $this->ok($statistics, null, [
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

        return $this->ok($summary);
    }

    /**
     * Get payment allocation suggestions.
     */
    public function allocationSuggestions(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->company;
        $payment = Payment::where('company_id', $company->id)->where('payment_id', $id)->firstOrFail();

        $suggestions = $this->paymentService->getAllocationSuggestions($payment);

        return $this->ok($suggestions);
    }
}
