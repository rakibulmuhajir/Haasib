<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Domain\Customers\Actions\ChangeCustomerStatusAction;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerAction;
use Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAction;
use Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAction;
use Modules\Accounting\Domain\Customers\Services\CustomerQueryService;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerQueryService $customerQueryService,
        private CreateCustomerAction $createCustomerAction,
        private UpdateCustomerAction $updateCustomerAction,
        private DeleteCustomerAction $deleteCustomerAction,
        private ChangeCustomerStatusAction $changeCustomerStatusAction
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $currentCompany = $request->attributes->get('company');

        // Build filters from request
        $filters = [
            'status' => $request->get('status'),
            'search' => $request->get('search'),
            'currency' => $request->get('currency'),
        ];

        $customers = $this->customerQueryService->getCustomers(
            $currentCompany,
            array_filter($filters),
            $request->get('per_page', 15)
        );

        $statistics = $this->customerQueryService->getCustomerStatistics($currentCompany);

        return Inertia::render('Accounting/Customers/Index', [
            'customers' => $customers,
            'filters' => $filters,
            'statistics' => $statistics,
            'can' => [
                'create' => $user->hasPermissionTo('accounting.customers.create'),
                'export' => $user->hasPermissionTo('accounting.customers.export'),
                'update' => $user->hasPermissionTo('accounting.customers.update'),
                'delete' => $user->hasPermissionTo('accounting.customers.delete'),
                'manage_contacts' => $user->hasPermissionTo('accounting.customers.manage_contacts'),
                'manage_credit' => $user->hasPermissionTo('accounting.customers.manage_credit'),
                'generate_statements' => $user->hasPermissionTo('accounting.customers.generate_statements'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        // Get countries from database if available, otherwise use static data
        try {
            $countries = DB::table('countries')->orderBy('name')->get() ?: collect([
                (object) ['id' => 'US', 'name' => 'United States'],
                (object) ['id' => 'CA', 'name' => 'Canada'],
                (object) ['id' => 'GB', 'name' => 'United Kingdom'],
                (object) ['id' => 'AU', 'name' => 'Australia'],
                (object) ['id' => 'DE', 'name' => 'Germany'],
                (object) ['id' => 'FR', 'name' => 'France'],
                (object) ['id' => 'JP', 'name' => 'Japan'],
                (object) ['id' => 'CN', 'name' => 'China'],
                (object) ['id' => 'IN', 'name' => 'India'],
                (object) ['id' => 'BR', 'name' => 'Brazil'],
            ]);
        } catch (\Exception $e) {
            $countries = collect([
                (object) ['id' => 'US', 'name' => 'United States'],
                (object) ['id' => 'CA', 'name' => 'Canada'],
                (object) ['id' => 'GB', 'name' => 'United Kingdom'],
                (object) ['id' => 'AU', 'name' => 'Australia'],
                (object) ['id' => 'DE', 'name' => 'Germany'],
                (object) ['id' => 'FR', 'name' => 'France'],
                (object) ['id' => 'JP', 'name' => 'Japan'],
                (object) ['id' => 'CN', 'name' => 'China'],
                (object) ['id' => 'IN', 'name' => 'India'],
                (object) ['id' => 'BR', 'name' => 'Brazil'],
            ]);
        }

        // Get currencies from database if available, otherwise use static data
        try {
            $currencies = DB::table('currencies')->orderBy('code')->get() ?: collect([
                (object) ['id' => 'USD', 'code' => 'USD'],
                (object) ['id' => 'EUR', 'code' => 'EUR'],
                (object) ['id' => 'GBP', 'code' => 'GBP'],
                (object) ['id' => 'CAD', 'code' => 'CAD'],
                (object) ['id' => 'AUD', 'code' => 'AUD'],
                (object) ['id' => 'JPY', 'code' => 'JPY'],
                (object) ['id' => 'CNY', 'code' => 'CNY'],
                (object) ['id' => 'INR', 'code' => 'INR'],
                (object) ['id' => 'BRL', 'code' => 'BRL'],
            ]);
        } catch (\Exception $e) {
            $currencies = collect([
                (object) ['id' => 'USD', 'code' => 'USD'],
                (object) ['id' => 'EUR', 'code' => 'EUR'],
                (object) ['id' => 'GBP', 'code' => 'GBP'],
                (object) ['id' => 'CAD', 'code' => 'CAD'],
                (object) ['id' => 'AUD', 'code' => 'AUD'],
                (object) ['id' => 'JPY', 'code' => 'JPY'],
                (object) ['id' => 'CNY', 'code' => 'CNY'],
                (object) ['id' => 'INR', 'code' => 'INR'],
                (object) ['id' => 'BRL', 'code' => 'BRL'],
            ]);
        }

        return Inertia::render('Accounting/Customers/Create', [
            'countries' => $countries,
            'availableCurrencies' => $currencies,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'customer_type' => 'required|string|max:50',
            'address_line_1' => 'nullable|string|max:255',
            'country_id' => 'nullable|string',
            'currency_id' => 'nullable|string',
            'contact' => 'nullable|string|max:255',
            'credit_limit' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive,blocked',
        ]);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        // Map form fields to the expected fields for CreateCustomerAction
        $customerData = [
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
            'currency' => $this->getCurrencyCode($validated['currency_id'] ?? 'USD'),
            'credit_limit' => $validated['credit_limit'] ?? null,
            'email' => null,
            'phone' => null,
            'address' => $validated['address_line_1'] ?? null,
            'country' => $this->getCountryName($validated['country_id'] ?? null),
        ];

        // Determine if contact is email or phone
        if (! empty($validated['contact'])) {
            if (filter_var($validated['contact'], FILTER_VALIDATE_EMAIL)) {
                $customerData['email'] = $validated['contact'];
            } else {
                $customerData['phone'] = $validated['contact'];
            }
        }

        try {
            \Log::info('Creating customer with data:', $customerData);
            $customer = $this->createCustomerAction->execute(
                $currentCompany,
                $customerData,
                $user
            );
            \Log::info('Customer created successfully:', ['id' => $customer->id, 'name' => $customer->name]);

            return redirect()
                ->to('/accounting/customers')
                ->with('success', 'Customer created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation failed:', $e->errors());

            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            \Log::error('Customer creation failed:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return redirect()
                ->back()
                ->with('error', 'Failed to create customer: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        $customer = $this->customerQueryService->getCustomerDetails($currentCompany, $customer->id);

        if (! $customer) {
            abort(404, 'Customer not found');
        }

        // Get credit limit information if user has permission - DISABLED due to type mismatch issues
        // TODO: Fix CustomerCreditService to work with App\Models\Customer or convert to domain model properly
        $creditData = null;
        
        // Temporarily disabled credit information to prevent TypeError
        /*
        if ($user->hasPermissionTo('accounting.customers.manage_credit')) {
            try {
                $creditService = app(\Modules\Accounting\Domain\Customers\Services\CustomerCreditService::class);
                // We need the domain customer model for the credit service
                $domainCustomer = $this->customerQueryService->getCustomerDetails($currentCompany, $customer->id);
                $creditLimit = $creditService->getCurrentCreditLimit($domainCustomer);
                $currentExposure = $creditService->getCurrentExposure($domainCustomer);

                $creditData = [
                    'credit_limit' => $creditLimit,
                    'current_exposure' => $currentExposure,
                    'available_credit' => $creditLimit ? max(0, $creditLimit - $currentExposure) : null,
                    'utilization_percentage' => $creditLimit ? round(($currentExposure / $creditLimit) * 100, 1) : 0,
                ];
            } catch (\Exception $e) {
                \Log::warning('Failed to get credit information for customer', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage()
                ]);
                // Continue without credit data if service fails
            }
        }
        */

        return Inertia::render('Accounting/Customers/Show', [
            'customer' => $customer,
            'creditData' => $creditData,
            'can' => [
                'update' => $user->hasPermissionTo('accounting.customers.update'),
                'delete' => $user->hasPermissionTo('accounting.customers.delete'),
                'manage_contacts' => $user->hasPermissionTo('accounting.customers.manage_contacts'),
                'manage_credit' => $user->hasPermissionTo('accounting.customers.manage_credit'),
                'generate_statements' => $user->hasPermissionTo('accounting.customers.generate_statements'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, Customer $customer): Response
    {
        $this->authorizeCustomerAccess($request, $customer);

        return Inertia::render('Accounting/Customers/Edit', [
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
            'name' => 'sometimes|required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'customer_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'default_currency' => 'sometimes|required|string|size:3',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked',
        ]);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        try {
            $customer = $this->updateCustomerAction->execute(
                $currentCompany,
                $customer->id,
                $validated,
                $user
            );

            return response()->json([
                'message' => 'Customer updated successfully',
                'customer' => $customer,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Customer $customer): JsonResponse
    {
        $this->authorizeCustomerAccess($request, $customer);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        try {
            $this->deleteCustomerAction->execute(
                $currentCompany,
                $customer->id,
                $user
            );

            return response()->json([
                'message' => 'Customer deleted successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Cannot delete customer',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
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
            'customer_ids.*' => 'required|uuid|exists:pgsql.acct.customers,id',
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
     * Get currency code from currency ID or return the code directly.
     */
    private function getCurrencyCode(string $currencyIdOrCode): string
    {
        // If it's already a 3-letter code, return it
        if (strlen($currencyIdOrCode) === 3 && ctype_alpha($currencyIdOrCode)) {
            return strtoupper($currencyIdOrCode);
        }

        // Try to get from database if it's an ID
        try {
            $currency = DB::table('currencies')->where('id', $currencyIdOrCode)->first();
            if ($currency) {
                return $currency->code;
            }
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        // Default to USD
        return 'USD';
    }

    /**
     * Get country name from country ID or return the name directly.
     */
    private function getCountryName(?string $countryIdOrName): ?string
    {
        if (empty($countryIdOrName)) {
            return null;
        }

        // If it's not a UUID, assume it's already a name
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $countryIdOrName)) {
            return $countryIdOrName;
        }

        // Try to get from database if it's an ID
        try {
            $country = DB::table('countries')->where('id', $countryIdOrName)->first();
            if ($country) {
                return $country->name;
            }
        } catch (\Exception $e) {
            // Ignore if table doesn't exist
        }

        return null;
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
