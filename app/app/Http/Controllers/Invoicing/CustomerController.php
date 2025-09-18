<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\CustomerService;
use App\Support\Filtering\FilterBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $query = Customer::with(['currency', 'country'])
            ->where('company_id', $request->user()->current_company_id);

        // Apply normalized DSL filters if provided
        if ($request->filled('filters')) {
            $filters = $request->input('filters');
            $decoded = is_string($filters) ? json_decode($filters, true) : (is_array($filters) ? $filters : null);
            if (is_array($decoded)) {
                $builder = new FilterBuilder;
                $fieldMap = [
                    'name' => 'name',
                    'email' => 'email',
                    'phone' => 'phone',
                    'tax_number' => 'tax_number',
                    'created_at' => 'created_at',
                    'is_active' => 'is_active',
                    'country_name' => ['relation' => 'country', 'column' => 'name'],
                    'currency_code' => ['relation' => 'currency', 'column' => 'code'],
                    'outstanding_balance' => 'outstanding_balance',
                ];
                $query = $builder->apply($query, $decoded, $fieldMap);
            }
        }

        // Apply filters
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('country_id')) {
            $query->whereHas('country', function ($q) use ($request) {
                $q->where('id', $request->country_id);
            });
        }

        if ($request->filled('created_from')) {
            $query->where('created_at', '>=', $request->created_from);
        }

        if ($request->filled('created_to')) {
            $query->where('created_at', '<=', $request->created_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('tax_number', 'like', "%{$search}%")
                    ->orWhereJsonContains('billing_address', $search)
                    ->orWhereJsonContains('shipping_address', $search);
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['created_at', 'name', 'email', 'tax_number', 'is_active'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $customers = $query->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get available filters
        $countries = Country::orderBy('name')
            ->get(['id', 'name', 'code']);

        $statusOptions = [
            ['value' => '1', 'label' => 'Active'],
            ['value' => '0', 'label' => 'Inactive'],
        ];

        $customerTypeOptions = [
            ['value' => 'individual', 'label' => 'Individual'],
            ['value' => 'business', 'label' => 'Business'],
            ['value' => 'government', 'label' => 'Government'],
            ['value' => 'non_profit', 'label' => 'Non-Profit'],
        ];

        return Inertia::render('Invoicing/Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'dsl' => $request->input('filters'),
                'is_active' => $request->input('is_active'),
                'country_id' => $request->input('country_id'),
                'created_from' => $request->input('created_from'),
                'created_to' => $request->input('created_to'),
                'search' => $request->input('search'),
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
            'countries' => $countries,
            'statusOptions' => $statusOptions,
            'customerTypeOptions' => $customerTypeOptions,
        ]);
    }

    /**
     * Export customers as CSV using current filters.
     */
    public function export(Request $request)
    {
        $company = $request->user()->current_company;

        $rows = $this->customerService->exportCustomers(
            company: $company,
            search: $request->input('search'),
            status: $request->input('status'),
            customerType: $request->input('customer_type'),
            countryId: $request->input('country_id'),
            dateFrom: $request->input('created_from'),
            dateTo: $request->input('created_to')
        );

        $filename = 'customers-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $out = fopen('php://output', 'w');
            if (isset($rows[0])) {
                fputcsv($out, array_keys($rows[0]));
            }
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show the form for creating a new customer.
     */
    public function create(Request $request)
    {
        $countries = Country::orderBy('name')
            ->get(['id', 'name', 'code']);

        // Get the next customer number
        $nextCustomerNumber = $this->customerService->generateNextCustomerNumber($request->user()->current_company_id);

        return Inertia::render('Invoicing/Customers/Create', [
            'countries' => $countries,
            'nextCustomerNumber' => $nextCustomerNumber,
        ]);
    }

    /**
     * Store a newly created customer in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'customer_number' => 'required|string|max:50|unique:customers,customer_number,null,id,company_id,'.$request->user()->current_company_id,
            'customer_type' => 'required|string|in:individual,business,non_profit,government',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'currency_id' => 'nullable|exists:currencies,id',
            'tax_id' => 'nullable|string|max:50',
            'tax_exempt' => 'boolean',
            'payment_terms' => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:active,inactive,suspended',
            'notes' => 'nullable|string|max:2000',
            'primary_contact' => 'array',
            'primary_contact.first_name' => 'required_with:primary_contact|string|max:100',
            'primary_contact.last_name' => 'required_with:primary_contact|string|max:100',
            'primary_contact.email' => 'nullable|email|max:255',
            'primary_contact.phone' => 'nullable|string|max:50',
            'primary_contact.position' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $customer = $this->customerService->createCustomer(
                company: $request->user()->current_company,
                name: $request->name,
                customerType: $request->customer_type,
                email: $request->email,
                phone: $request->phone,
                website: $request->website,
                address: [
                    'address_line_1' => $request->address_line_1,
                    'address_line_2' => $request->address_line_2,
                    'city' => $request->city,
                    'state_province' => $request->state_province,
                    'postal_code' => $request->postal_code,
                    'country_id' => $request->country_id,
                ],
                currencyId: $request->currency_id,
                taxId: $request->tax_id,
                taxExempt: $request->boolean('tax_exempt'),
                paymentTerms: $request->payment_terms,
                creditLimit: $request->credit_limit,
                customerNumber: $request->customer_number,
                status: $request->status,
                notes: $request->notes,
                primaryContact: $request->primary_contact,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return redirect()
                ->route('customers.show', $customer)
                ->with('success', 'Customer created successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to create customer', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => $request->user()->id,
            ]);

            return back()
                ->with('error', 'Failed to create customer. '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $customer->load(['currency', 'country', 'contacts']);

        return Inertia::render('Invoicing/Customers/Show', [
            'customer' => $customer,
        ]);
    }

    /**
     * Show the form for editing the specified customer.
     */
    public function edit(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $customer->load(['currency', 'country', 'contacts']);

        $countries = Country::orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Invoicing/Customers/Edit', [
            'customer' => $customer,
            'countries' => $countries,
        ]);
    }

    /**
     * Update the specified customer in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'customer_number' => 'required|string|max:50|unique:customers,customer_number,'.$customer->id.',id,company_id,'.$request->user()->current_company_id,
            'customer_type' => 'required|string|in:individual,business,non_profit,government',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'currency_id' => 'nullable|exists:currencies,id',
            'tax_id' => 'nullable|string|max:50',
            'tax_exempt' => 'boolean',
            'payment_terms' => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'required|string|in:active,inactive,suspended',
            'notes' => 'nullable|string|max:2000',
            'primary_contact' => 'array',
            'primary_contact.first_name' => 'required_with:primary_contact|string|max:100',
            'primary_contact.last_name' => 'required_with:primary_contact|string|max:100',
            'primary_contact.email' => 'nullable|email|max:255',
            'primary_contact.phone' => 'nullable|string|max:50',
            'primary_contact.position' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $updatedCustomer = $this->customerService->updateCustomer(
                customer: $customer,
                name: $request->name,
                customerType: $request->customer_type,
                email: $request->email,
                phone: $request->phone,
                website: $request->website,
                address: [
                    'address_line_1' => $request->address_line_1,
                    'address_line_2' => $request->address_line_2,
                    'city' => $request->city,
                    'state_province' => $request->state_province,
                    'postal_code' => $request->postal_code,
                    'country_id' => $request->country_id,
                ],
                currencyId: $request->currency_id,
                taxId: $request->tax_id,
                taxExempt: $request->boolean('tax_exempt'),
                paymentTerms: $request->payment_terms,
                creditLimit: $request->credit_limit,
                customerNumber: $request->customer_number,
                status: $request->status,
                notes: $request->notes,
                primaryContact: $request->primary_contact,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return redirect()
                ->route('customers.show', $updatedCustomer)
                ->with('success', 'Customer updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update customer', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
            ]);

            return back()
                ->with('error', 'Failed to update customer. '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified customer from storage.
     */
    public function destroy(Request $request, Customer $customer)
    {
        $this->authorize('delete', $customer);

        // Check if customer has related records
        $hasInvoices = Invoice::where('customer_id', $customer->id)->exists();
        $hasPayments = Payment::where('customer_id', $customer->id)->exists();

        if ($hasInvoices || $hasPayments) {
            return back()->with('error', 'Cannot delete customer with related invoices or payments.');
        }

        try {
            $this->customerService->deleteCustomer($customer);

            return redirect()
                ->route('customers.index')
                ->with('success', 'Customer deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete customer', [
                'error' => $e->getMessage(),
                'customer_id' => $customer->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to delete customer. '.$e->getMessage());
        }
    }

    /**
     * Display customer invoices.
     */
    public function invoices(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $invoices = Invoice::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return Inertia::render('Invoicing/Customers/Invoices', [
            'customer' => $customer,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Display customer payments.
     */
    public function payments(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $payments = Payment::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return Inertia::render('Invoicing/Customers/Payments', [
            'customer' => $customer,
            'payments' => $payments,
        ]);
    }

    /**
     * Display customer statement.
     */
    public function statement(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $statementData = $this->customerService->generateCustomerStatement($customer);

        return Inertia::render('Invoicing/Customers/Statement', [
            'customer' => $customer,
            'statement' => $statementData,
        ]);
    }

    /**
     * Display customer statistics.
     */
    public function statistics(Request $request, Customer $customer)
    {
        $this->authorize('view', $customer);

        $statistics = $this->customerService->getCustomerStatistics($customer);

        return Inertia::render('Invoicing/Customers/Statistics', [
            'customer' => $customer,
            'statistics' => $statistics,
        ]);
    }

    /**
     * Bulk operations on customers: delete, disable, enable
     */
    public function bulk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:delete,disable,enable',
            'customer_ids' => 'required|array|min:1',
            'customer_ids.*' => 'integer|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $companyId = $request->user()->current_company_id;
        $action = $request->input('action');
        $ids = $request->input('customer_ids', []);

        $processed = 0;
        $failed = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                $customer = Customer::where('id', $id)->where('company_id', $companyId)->firstOrFail();

                if ($action === 'delete') {
                    // Ensure no invoices or payments and no outstanding balance
                    $hasInvoices = Invoice::where('customer_id', $customer->id)->exists();
                    $hasPayments = Payment::where('customer_id', $customer->id)->exists();
                    if ($hasInvoices || $hasPayments || ($customer->outstanding_balance ?? 0) > 0) {
                        throw new \RuntimeException('Customer has related records or outstanding balance');
                    }
                    $this->customerService->deleteCustomer($customer);
                } elseif ($action === 'disable') {
                    $customer->is_active = false;
                    $customer->save();
                } elseif ($action === 'enable') {
                    $customer->is_active = true;
                    $customer->save();
                }

                $processed++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['customer_id' => $id, 'error' => $e->getMessage()];
                Log::warning('Customer bulk action failed', [
                    'action' => $action,
                    'customer_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', "Bulk {$action} completed. Processed: {$processed}, Failed: {$failed}")
            ->with('bulk_result', compact('processed', 'failed', 'errors'));
    }
}
