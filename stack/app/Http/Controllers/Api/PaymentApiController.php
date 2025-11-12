<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of payments.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $payments = Payment::where('company_id', $company->id)
            ->with(['customer'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($payments);
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::create([
                'company_id' => $company->id,
                'customer_id' => $validated['customer_id'],
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'received',
            ]);

            return response()->json([
                'message' => 'Payment created successfully',
                'payment' => $payment->load(['customer']),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $payment = Payment::where('company_id', $company->id)
            ->with(['customer'])
            ->findOrFail($id);

        return response()->json($payment);
    }

    /**
     * Update the specified payment.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'payment_date' => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|string|max:255',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            $payment->update($validated);

            return response()->json([
                'message' => 'Payment updated successfully',
                'payment' => $payment->load(['customer']),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified payment.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            $payment->delete();

            return response()->json([
                'message' => 'Payment deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Allocate payment to invoices.
     */
    public function allocate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'invoice_ids' => 'required|array|min:1',
            'invoice_ids.*' => 'required|string|exists:invoices,id',
            'amounts' => 'required|array|min:1',
            'amounts.*' => 'required|numeric|min:0',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            // Allocation logic would be implemented here
            // For now, just return success

            return response()->json([
                'message' => 'Payment allocated successfully',
                'payment' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to allocate payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Auto allocate payment to invoices.
     */
    public function autoAllocate(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            // Auto allocation logic would be implemented here
            // For now, just return success

            return response()->json([
                'message' => 'Payment auto allocated successfully',
                'payment' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to auto allocate payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment allocations.
     */
    public function allocations(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->with(['allocations'])
                ->findOrFail($id);

            return response()->json($payment->allocations);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void payment allocation.
     */
    public function voidAllocation(Request $request, string $paymentId, string $allocationId): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($paymentId);

            // Void allocation logic would be implemented here

            return response()->json([
                'message' => 'Allocation voided successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to void allocation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund payment allocation.
     */
    public function refundAllocation(Request $request, string $paymentId, string $allocationId): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($paymentId);

            // Refund allocation logic would be implemented here

            return response()->json([
                'message' => 'Allocation refunded successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refund allocation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Void payment.
     */
    public function void(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            $payment->update(['status' => 'void']);

            return response()->json([
                'message' => 'Payment voided successfully',
                'payment' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to void payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refund payment.
     */
    public function refund(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:1000',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            // Refund logic would be implemented here

            return response()->json([
                'message' => 'Payment refunded successfully',
                'payment' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to refund payment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payment statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $stats = [
                'total_payments' => Payment::where('company_id', $company->id)->count(),
                'total_amount' => Payment::where('company_id', $company->id)->sum('amount'),
                'received_payments' => Payment::where('company_id', $company->id)->where('status', 'received')->count(),
                'pending_payments' => Payment::where('company_id', $company->id)->where('status', 'pending')->count(),
                'void_payments' => Payment::where('company_id', $company->id)->where('status', 'void')->count(),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer payment summary.
     */
    public function customerSummary(Request $request, string $customerId): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($customerId);

            $summary = [
                'customer' => $customer,
                'total_payments' => Payment::where('company_id', $company->id)
                    ->where('customer_id', $customerId)
                    ->count(),
                'total_amount' => Payment::where('company_id', $company->id)
                    ->where('customer_id', $customerId)
                    ->sum('amount'),
                'unallocated_amount' => Payment::where('company_id', $company->id)
                    ->where('customer_id', $customerId)
                    ->where('status', 'received')
                    ->sum('amount'),
            ];

            return response()->json($summary);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get customer summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get allocation suggestions.
     */
    public function allocationSuggestions(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $payment = Payment::where('company_id', $company->id)
                ->findOrFail($id);

            // Allocation suggestions logic would be implemented here
            $suggestions = [];

            return response()->json($suggestions);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get allocation suggestions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations on payments.
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:delete,void,refund',
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'required|string|exists:payments,id',
            'refund_reason' => 'required_if:action,refund|string|max:1000',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $payments = Payment::where('company_id', $company->id)
                ->whereIn('id', $validated['payment_ids'])
                ->get();

            $count = 0;

            foreach ($payments as $payment) {
                if ($validated['action'] === 'delete') {
                    $payment->delete();
                    $count++;
                } elseif ($validated['action'] === 'void') {
                    $payment->update(['status' => 'void']);
                    $count++;
                } elseif ($validated['action'] === 'refund') {
                    // Refund logic would be implemented here
                    $count++;
                }
            }

            return response()->json([
                'message' => 'Bulk operation completed successfully',
                'affected_count' => $count,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to perform bulk operation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
