<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Requests\Api\Customer\BulkCustomerRequest;
use App\Http\Requests\Api\Customer\StoreCustomerRequest;
use App\Http\Requests\Api\Customer\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerApiController extends Controller
{
    use ApiResponder;
    /**
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $request->attributes->get('company');

        $query = Customer::where('company_id', $company->id)
            ->with(['currency', 'contacts']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        if ($request->filled('currency_id')) {
            $query->where('currency_id', $request->currency_id);
        }

        $customers = $query->orderBy($request->sort_by ?? 'name', $request->sort_order ?? 'asc')
            ->paginate($request->per_page ?? 15);

        return $this->ok(
            $customers->items(),
            null,
            [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
                'filters' => [
                    'search' => $request->search,
                    'status' => $request->status,
                    'customer_type' => $request->customer_type,
                    'currency_id' => $request->currency_id,
                ],
            ]
        );
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');

            $customer = new Customer([
                'company_id' => $company->id,
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'country' => $request->country,
                'postal_code' => $request->postal_code,
                'customer_code' => $request->customer_code,
                'customer_type' => $request->customer_type,
                'tax_id' => $request->tax_id,
                'registration_number' => $request->registration_number,
                'website' => $request->website,
                'payment_terms' => $request->payment_terms,
                'credit_limit' => $request->credit_limit,
                'currency_id' => $request->currency_id,
                'notes' => $request->notes,
                'status' => $request->status ?? 'active',
            ]);

            $customer->save();

            return $this->ok($customer->load(['currency', 'contacts']), 'Customer created successfully', status: 201);

        } catch (\Exception $e) {
            Log::error('Failed to create customer', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'request_data' => $request->all(),
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to create customer', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $company = $request->attributes->get('company');

        $customer = Customer::where('company_id', $company->id)
            ->with(['currency', 'contacts', 'invoices', 'payments'])
            ->findOrFail($id);

        return $this->ok($customer, null, [
            'invoice_count' => $customer->invoices()->count(),
            'payment_count' => $customer->payments()->count(),
            'total_invoiced' => $customer->invoices()->sum('total_amount'),
            'total_paid' => $customer->payments()->where('status', 'completed')->sum('amount'),
            'outstanding_balance' => $customer->getOutstandingBalance(),
            'payment_status' => $customer->getPaymentStatus(),
            'customer_age' => $customer->getAgeInDays(),
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $customer = Customer::where('company_id', $company->id)->findOrFail($id);

            $customer->update($request->validated());

            return $this->ok($customer->load(['currency', 'contacts']), 'Customer updated successfully');

        } catch (\Exception $e) {
            Log::error('Failed to update customer', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to update customer', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $customer = Customer::where('company_id', $company->id)->findOrFail($id);

            if ($customer->invoices()->exists() || $customer->payments()->exists()) {
                return $this->fail('BUSINESS_RULE', 'Cannot delete customer with existing invoices or payments', 400, ['message' => 'Please archive this customer instead']);
            }

            $customer->delete();

            return $this->ok(null, 'Customer deleted successfully');

        } catch (\Exception $e) {
            Log::error('Failed to delete customer', [
                'error' => $e->getMessage(),
                'customer_id' => $id,
                'user_id' => $request->user()->id,
            ]);

            return $this->fail('INTERNAL_ERROR', 'Failed to delete customer', 500, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Get customer invoices.
     */
    public function invoices(Request $request, string $id): JsonResponse
    {
        $company = $request->attributes->get('company');
        $customer = $company->customers()->findOrFail($id);

        $query = $customer->invoices()
            ->with(['currency', 'items']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('invoice_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('invoice_date', '<=', $request->date_to);
        }

        $invoices = $query->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->ok($invoices->items(), null, [
            'current_page' => $invoices->currentPage(),
            'per_page' => $invoices->perPage(),
            'total' => $invoices->total(),
            'last_page' => $invoices->lastPage(),
        ]);
    }

    /**
     * Get customer payments.
     */
    public function payments(Request $request, string $id): JsonResponse
    {
        $company = $request->attributes->get('company');
        $customer = $company->customers()->findOrFail($id);

        $query = $customer->payments()
            ->with(['currency', 'allocations.invoice']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        $payments = $query->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        return $this->ok($payments->items(), null, [
            'current_page' => $payments->currentPage(),
            'per_page' => $payments->perPage(),
            'total' => $payments->total(),
            'last_page' => $payments->lastPage(),
        ]);
    }

    /**
     * Get customer statement.
     */
    public function statement(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $company = $request->attributes->get('company');
        $customer = $company->customers()->findOrFail($id);

        $invoices = $customer->invoices()
            ->when($request->date_from, fn ($q) => $q->where('invoice_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->where('invoice_date', '<=', $request->date_to))
            ->with(['items', 'paymentAllocations.payment'])
            ->get();

        $payments = $customer->payments()
            ->when($request->date_from, fn ($q) => $q->where('payment_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->where('payment_date', '<=', $request->date_to))
            ->with(['allocations.invoice'])
            ->get();

        $statement = [
            'customer' => $customer->only(['id', 'name', 'email', 'customer_code']),
            'period' => [
                'from' => $request->date_from,
                'to' => $request->date_to,
            ],
            'invoices' => $invoices,
            'payments' => $payments,
            'summary' => [
                'total_invoiced' => $invoices->sum('total_amount'),
                'total_paid' => $payments->where('status', 'completed')->sum('amount'),
                'outstanding_balance' => $customer->getOutstandingBalance(),
                'overdue_invoices' => $invoices->where('status', 'overdue')->count(),
                'overdue_amount' => $invoices->where('status', 'overdue')->sum('balance_due'),
            ],
        ];

        return $this->ok($statement);
    }

    /**
     * Get customer statistics.
     */
    public function statistics(Request $request, string $id): JsonResponse
    {
        $company = $request->attributes->get('company');
        $customer = $company->customers()->findOrFail($id);

        $stats = [
            'invoice_stats' => [
                'total_invoices' => $customer->invoices()->count(),
                'total_amount' => $customer->invoices()->sum('total_amount'),
                'paid_invoices' => $customer->invoices()->where('payment_status', 'paid')->count(),
                'partial_invoices' => $customer->invoices()->where('payment_status', 'partial')->count(),
                'unpaid_invoices' => $customer->invoices()->where('payment_status', 'unpaid')->count(),
                'overdue_invoices' => $customer->invoices()->where('status', 'overdue')->count(),
            ],
            'payment_stats' => [
                'total_payments' => $customer->payments()->count(),
                'total_paid' => $customer->payments()->where('status', 'completed')->sum('amount'),
                'average_payment_amount' => $customer->payments()->where('status', 'completed')->avg('amount'),
                'last_payment_date' => $customer->payments()->where('status', 'completed')->max('payment_date'),
            ],
            'aging_analysis' => $customer->getAgingAnalysis(),
            'payment_history' => $customer->getPaymentHistory(),
        ];

        return $this->ok($stats);
    }

    /**
     * Bulk operations on customers.
     */
    public function bulk(BulkCustomerRequest $request): JsonResponse
    {
        try {
            $company = $request->attributes->get('company');
            $results = [];

            switch ($request->action) {
                case 'delete':
                    $customers = Customer::where('company_id', $company->id)
                        ->whereIn('id', $request->customer_ids)
                        ->get();

                    foreach ($customers as $customer) {
                        try {
                            if (! $customer->invoices()->exists() && ! $customer->payments()->exists()) {
                                $customer->delete();
                                $results[] = ['id' => $customer->id, 'success' => true];
                            } else {
                                $results[] = [
                                    'id' => $customer->id,
                                    'success' => false,
                                    'error' => 'Customer has existing invoices or payments',
                                ];
                            }
                        } catch (\Exception $e) {
                            $results[] = [
                                'id' => $customer->id,
                                'success' => false,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                    break;

                case 'activate':
                case 'deactivate':
                    $status = $request->action === 'activate' ? 'active' : 'inactive';
                    Customer::where('company_id', $company->id)
                        ->whereIn('id', $request->customer_ids)
                        ->update(['status' => $status]);

                    foreach ($request->customer_ids as $id) {
                        $results[] = ['id' => $id, 'success' => true];
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
     * Search customers.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $company = $request->user()->company;
        $limit = $request->limit ?? 10;

        $customers = Customer::where('company_id', $company->id)
            ->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->query}%")
                    ->orWhere('email', 'like', "%{$request->query}%")
                    ->orWhere('customer_code', 'like', "%{$request->query}%");
            })
            ->with(['currency'])
            ->limit($limit)
            ->get();

        return $this->ok($customers, null, [
            'query' => $request->query,
            'limit' => $limit,
            'total_results' => $customers->count(),
        ]);
    }
}
