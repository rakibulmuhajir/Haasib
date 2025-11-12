<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $customers = Customer::where('company_id', $company->id)
            ->orderBy('name')
            ->paginate(15);

        return response()->json($customers);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:customers,email',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'state' => $validated['state'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'country' => $validated['country'] ?? null,
                'tax_id' => $validated['tax_id'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'active',
            ]);

            return response()->json([
                'message' => 'Customer created successfully',
                'customer' => $customer,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $company = $request->user()->currentCompany();

        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($id);

        return response()->json($customer);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|nullable|email|max:255|unique:customers,email,'.$id,
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|required|in:active,inactive',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($id);

            $customer->update($validated);

            return response()->json([
                'message' => 'Customer updated successfully',
                'customer' => $customer,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($id);

            // Check if customer has invoices or payments
            $hasInvoices = Invoice::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->exists();

            $hasPayments = Payment::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->exists();

            if ($hasInvoices || $hasPayments) {
                return response()->json([
                    'message' => 'Cannot delete customer with associated invoices or payments',
                ], 422);
            }

            $customer->delete();

            return response()->json([
                'message' => 'Customer deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer invoices.
     */
    public function invoices(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($id);

            $invoices = Invoice::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($invoices);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get customer invoices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer payments.
     */
    public function payments(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($id);

            $payments = Payment::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($payments);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get customer payments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer statement.
     */
    public function statement(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($id);

            $startDate = $validated['start_date'] ?? now()->subDays(30)->format('Y-m-d');
            $endDate = $validated['end_date'] ?? now()->format('Y-m-d');

            $invoices = Invoice::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();

            $payments = Payment::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->get();

            $totalInvoiced = $invoices->sum('total_amount');
            $totalPaid = $payments->sum('amount');
            $balance = $totalInvoiced - $totalPaid;

            $statement = [
                'customer' => $customer,
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_invoiced' => $totalInvoiced,
                    'total_paid' => $totalPaid,
                    'balance' => $balance,
                ],
                'invoices' => $invoices,
                'payments' => $payments,
            ];

            return response()->json($statement);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get customer statement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get customer statistics.
     */
    public function statistics(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customer = Customer::where('company_id', $company->id)
                ->findOrFail($id);

            $totalInvoices = Invoice::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->count();

            $totalInvoiced = Invoice::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->sum('total_amount');

            $totalPayments = Payment::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->count();

            $totalPaid = Payment::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->sum('amount');

            $outstandingBalance = Invoice::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->where('status', 'posted')
                ->sum('total_amount') - $totalPaid;

            $overdueInvoices = Invoice::where('company_id', $company->id)
                ->where('customer_id', $id)
                ->where('status', 'posted')
                ->where('due_date', '<', now())
                ->count();

            $statistics = [
                'customer' => $customer,
                'invoices' => [
                    'count' => $totalInvoices,
                    'total_amount' => $totalInvoiced,
                ],
                'payments' => [
                    'count' => $totalPayments,
                    'total_amount' => $totalPaid,
                ],
                'balance' => [
                    'outstanding' => $outstandingBalance,
                    'overdue_invoices' => $overdueInvoices,
                ],
            ];

            return response()->json($statistics);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get customer statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk operations on customers.
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:delete,activate,deactivate,merge',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'required|string|exists:customers,id',
            'merge_into' => 'required_if:action,merge|string|exists:customers,id',
        ]);

        try {
            $company = $request->user()->currentCompany();

            $customers = Customer::where('company_id', $company->id)
                ->whereIn('id', $validated['customer_ids'])
                ->get();

            $count = 0;

            foreach ($customers as $customer) {
                if ($validated['action'] === 'delete') {
                    // Check if customer has invoices or payments
                    $hasInvoices = Invoice::where('company_id', $company->id)
                        ->where('customer_id', $customer->id)
                        ->exists();

                    $hasPayments = Payment::where('company_id', $company->id)
                        ->where('customer_id', $customer->id)
                        ->exists();

                    if (! $hasInvoices && ! $hasPayments) {
                        $customer->delete();
                        $count++;
                    }
                } elseif ($validated['action'] === 'activate') {
                    $customer->update(['status' => 'active']);
                    $count++;
                } elseif ($validated['action'] === 'deactivate') {
                    $customer->update(['status' => 'inactive']);
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

    /**
     * Search customers.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        try {
            $company = $request->user()->currentCompany();
            $limit = $validated['limit'] ?? 20;

            $customers = Customer::where('company_id', $company->id)
                ->where(function ($query) use ($validated) {
                    $query->where('name', 'ilike', '%'.$validated['query'].'%')
                        ->orWhere('email', 'ilike', '%'.$validated['query'].'%')
                        ->orWhere('phone', 'ilike', '%'.$validated['query'].'%');
                })
                ->orderBy('name')
                ->limit($limit)
                ->get();

            return response()->json($customers);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to search customers',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
