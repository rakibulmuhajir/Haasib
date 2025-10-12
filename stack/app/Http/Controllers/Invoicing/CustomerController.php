<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentCompany = $request->attributes->get('company');

        $customers = Customer::query()
            ->where('company_id', $currentCompany->id)
            ->with(['invoices', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'can' => [
                'create' => $user->hasPermissionTo('customers.create'),
                'export' => $user->hasPermissionTo('customers.export'),
                'update' => $user->hasPermissionTo('customers.update'),
                'delete' => $user->hasPermissionTo('customers.delete'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $currentCompany = $request->attributes->get('company');

        $customer = Customer::create([
            ...$validated,
            'company_id' => $currentCompany->id,
        ]);

        return response()->json([
            'message' => 'Customer created successfully',
            'customer' => $customer,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        $customer->load(['invoices', 'payments']);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomerAccess($request, $customer);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_id' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer updated successfully',
            'customer' => $customer,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomerAccess($request, $customer);

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }

    /**
     * Export customers to CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $customers = Customer::query()
            ->where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $csv = "Name,Email,Phone,Address,City,Country,Tax ID,Created At\n";

        foreach ($customers as $customer) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s"%s',
                $customer->name,
                $customer->email ?? '',
                $customer->phone ?? '',
                $customer->address ?? '',
                $customer->city ?? '',
                $customer->country ?? '',
                $customer->tax_id ?? '',
                $customer->created_at->format('Y-m-d H:i:s'),
                "\n"
            );
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="customers.csv"');
    }

    /**
     * Get customer invoices.
     */
    public function invoices(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        $invoices = $customer->invoices()
            ->with(['lineItems'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Customers/Invoices', [
            'customer' => $customer,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Get customer payments.
     */
    public function payments(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        $payments = $customer->payments()
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Customers/Payments', [
            'customer' => $customer,
            'payments' => $payments,
        ]);
    }

    /**
     * Get customer statement.
     */
    public function statement(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        $invoices = $customer->invoices()
            ->where('status', '!=', 'draft')
            ->orderBy('issue_date', 'desc')
            ->get();

        $payments = $customer->payments()
            ->orderBy('payment_date', 'desc')
            ->get();

        $openingBalance = 0;
        $totalInvoiced = $invoices->sum('total');
        $totalPaid = $payments->sum('amount');
        $currentBalance = $openingBalance + $totalInvoiced - $totalPaid;

        return Inertia::render('Customers/Statement', [
            'customer' => $customer,
            'invoices' => $invoices,
            'payments' => $payments,
            'summary' => [
                'opening_balance' => $openingBalance,
                'total_invoiced' => $totalInvoiced,
                'total_paid' => $totalPaid,
                'current_balance' => $currentBalance,
            ],
        ]);
    }

    /**
     * Get customer statistics.
     */
    public function statistics(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        $stats = [
            'total_invoices' => $customer->invoices()->count(),
            'total_amount' => $customer->invoices()->sum('total'),
            'paid_amount' => $customer->payments()->sum('amount'),
            'outstanding_amount' => $customer->invoices()->where('status', '!=', 'paid')->sum('total') - $customer->payments()->sum('amount'),
            'total_payments' => $customer->payments()->count(),
            'average_invoice_amount' => $customer->invoices()->avg('total'),
        ];

        return Inertia::render('Customers/Statistics', [
            'customer' => $customer,
            'statistics' => $stats,
        ]);
    }

    /**
     * Handle bulk operations on customers.
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,export',
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'required|uuid|exists:invoicing.customers,id',
        ]);

        $currentCompany = $request->attributes->get('company');

        $customers = Customer::whereIn('id', $validated['customer_ids'])
            ->where('company_id', $currentCompany->id)
            ->get();

        $processedCount = 0;

        switch ($validated['action']) {
            case 'delete':
                foreach ($customers as $customer) {
                    $customer->delete();
                    $processedCount++;
                }
                break;

            case 'export':
                $csv = "Name,Email,Phone,Address,City,Country,Tax ID,Created At\n";

                foreach ($customers as $customer) {
                    $csv .= sprintf(
                        '"%s","%s","%s","%s","%s","%s","%s","%s"%s',
                        $customer->name,
                        $customer->email ?? '',
                        $customer->phone ?? '',
                        $customer->address ?? '',
                        $customer->city ?? '',
                        $customer->country ?? '',
                        $customer->tax_id ?? '',
                        $customer->created_at->format('Y-m-d H:i:s'),
                        "\n"
                    );
                }

                return response($csv)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', 'attachment; filename="customers_export.csv"');
        }

        return response()->json([
            'message' => "Successfully processed {$processedCount} customers",
            'processed' => $processedCount,
            'requested' => count($validated['customer_ids']),
        ]);
    }

    /**
     * Authorize that user can access the customer in the current company context.
     */
    private function authorizeCustomerAccess(Request $request, Customer $customer): void
    {
        $currentCompany = $request->attributes->get('company');

        if ($customer->company_id !== $currentCompany->id) {
            abort(403, 'You do not have access to this customer');
        }
    }
}
