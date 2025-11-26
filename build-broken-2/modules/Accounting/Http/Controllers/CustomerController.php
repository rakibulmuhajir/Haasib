<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompanyCurrency;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Http\Requests\StoreCustomerRequest;
use Modules\Accounting\Http\Requests\UpdateCustomerRequest;
use Modules\Accounting\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request): Response
    {
        $companyId = session('active_company_id');

        if (!$companyId) {
            return Inertia::render('Accounting/Customers/Index', [
                'customers' => [],
                'currencies' => [],
                'message' => 'Please select a company first',
            ]);
        }

        // Get customers with their preferred currency
        $customers = Customer::where('company_id', $companyId)
            ->with('preferredCurrency')
            ->select(['id', 'customer_number', 'name', 'email', 'status', 'preferred_currency_code'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        // Get company currencies for the form
        $currencies = $this->currencyService->getCompanyCurrencies($companyId)
            ->map(function ($currency) {
                return [
                    'code' => $currency->currency_code,
                    'name' => $currency->currency_name,
                    'symbol' => $currency->currency_symbol,
                    'display_name' => $currency->currency_code . ' - ' . $currency->currency_name,
                    'is_base' => $currency->is_base_currency,
                ];
            });

        $baseCurrency = $this->currencyService->getBaseCurrency($companyId);

        return Inertia::render('Customers/Customers', [
            'customers' => $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'customer_number' => $customer->customer_number,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'status' => $customer->status,
                ];
            }),
            'isMultiCurrencyEnabled' => $this->currencyService->isMultiCurrencyEnabled($companyId),
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency ? [
                'code' => $baseCurrency->currency_code,
                'name' => $baseCurrency->currency_name,
                'symbol' => $baseCurrency->currency_symbol,
            ] : null,
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $companyId = session('active_company_id');
        $userId = auth()->id();

        try {
            DB::beginTransaction();

            // Generate customer number
            $customerNumber = DB::select("SELECT acct.generate_customer_number(?) as number", [$companyId])[0]->number;

            // Get base currency if no preferred currency specified
            $preferredCurrency = $request->preferred_currency_code;
            if (!$preferredCurrency) {
                $baseCurrency = $this->currencyService->getBaseCurrency($companyId);
                $preferredCurrency = $baseCurrency?->currency_code;
            }

            $customer = Customer::create([
                'company_id' => $companyId,
                'customer_number' => $customerNumber,
                'name' => $request->name,
                'email' => $request->email,
                'preferred_currency_code' => $preferredCurrency,
                'created_by' => $userId,
            ]);

            DB::commit();

            // Flash success message
            session()->flash('success', 'Customer created successfully!');
            
            // Redirect back to customers page for Inertia requests
            return redirect()->route('accounting.customers');

        } catch (\Exception $e) {
            DB::rollback();
            
            // Flash error message for Inertia requests
            session()->flash('error', 'Failed to create customer: ' . $e->getMessage());
            
            // Redirect back with input for Inertia requests
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load('preferredCurrency');

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        try {
            $customer->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer->fresh()->load('preferredCurrency')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customers for select/autocomplete.
     */
    public function search(Request $request): JsonResponse
    {
        $companyId = session('active_company_id');
        $query = $request->get('q', '');

        $customers = Customer::where('company_id', $companyId)
            ->where('status', 'active')
            ->when($query, function ($q) use ($query) {
                $q->where(function ($subQuery) use ($query) {
                    $subQuery->where('name', 'ILIKE', "%{$query}%")
                             ->orWhere('email', 'ILIKE', "%{$query}%")
                             ->orWhere('customer_number', 'ILIKE', "%{$query}%");
                });
            })
            ->with('preferredCurrency')
            ->select(['id', 'customer_number', 'name', 'email', 'preferred_currency_code'])
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }
}