<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Http\Requests\StoreInvoiceRequest;
use Modules\Accounting\Models\Customer;
use Modules\Accounting\Models\Invoice;

class InvoiceController extends Controller
{
    public function __construct(
        private CurrencyService $currencyService
    ) {}
    /**
     * Display a listing of invoices.
     */
    public function index(Request $request): Response
    {
        $companyId = session('active_company_id');

        $invoices = Invoice::where('company_id', $companyId)
            ->with(['customer', 'currency'])
            ->select(['id', 'invoice_number', 'customer_id', 'total_amount', 'currency_code', 'status', 'due_date', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name' => $invoice->customer?->name,
                    'total_amount' => $invoice->total_amount,
                    'currency_code' => $invoice->currency_code,
                    'currency_symbol' => $invoice->currency?->currency_symbol ?? '$',
                    'status' => $invoice->status,
                    'due_date' => optional($invoice->due_date)->toDateString(),
                ];
            });

        return Inertia::render('invoicing/Invoices', [
            'invoices' => $invoices,
        ]);
    }

    /**
     * Show the form for creating a new invoice.
     */
    public function create(Request $request): Response
    {
        $companyId = session('active_company_id');

        // Get customers for dropdown
        $customers = Customer::where('company_id', $companyId)
            ->with('preferredCurrency')
            ->select(['id', 'customer_number', 'name', 'email', 'preferred_currency_code'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'display_name' => $customer->customer_number . ' - ' . $customer->name,
                    'name' => $customer->name,
                    'customer_number' => $customer->customer_number,
                    'email' => $customer->email,
                    'preferred_currency_code' => $customer->preferred_currency_code,
                ];
            });

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

        return Inertia::render('invoicing/CreateInvoice', [
            'customers' => $customers,
            'currencies' => $currencies,
            'baseCurrency' => $baseCurrency ? [
                'code' => $baseCurrency->currency_code,
                'name' => $baseCurrency->currency_name,
                'symbol' => $baseCurrency->currency_symbol,
            ] : null,
            'isMultiCurrencyEnabled' => $this->currencyService->isMultiCurrencyEnabled($companyId),
        ]);
    }

    /**
     * Store a newly created invoice.
     */
    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        try {
            $companyId = session('active_company_id');
            $userId = auth()->id();
            $validated = $request->validated();

            DB::beginTransaction();

            // Generate invoice number
            $invoiceNumber = DB::select("SELECT acct.generate_invoice_number(?) as number", [$companyId])[0]->number;

            // Calculate base currency total if not base currency
            $baseCurrencyTotal = $validated['total_amount'];
            if ($validated['currency_code'] !== $this->currencyService->getBaseCurrency($companyId)?->currency_code) {
                $baseCurrencyTotal = $validated['total_amount'] * $validated['exchange_rate'];
            }

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'invoice_number' => $invoiceNumber,
                'customer_id' => $validated['customer_id'],
                'issue_date' => $validated['issue_date'],
                'due_date' => $validated['due_date'],
                'currency_code' => $validated['currency_code'],
                'exchange_rate' => $validated['exchange_rate'],
                'subtotal_amount' => $validated['subtotal'],
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'shipping_amount' => $validated['shipping_amount'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'base_currency_total' => $baseCurrencyTotal,
                'balance_due' => $validated['total_amount'],
                'payment_status' => 'unpaid',
                'status' => 'draft',
                'created_by_user_id' => $userId,
                'notes' => $validated['notes'] ?? null,
            ]);

            // TODO: Create line items in a separate table when that's implemented
            // For now, store in notes field or create a simple JSON field

            DB::commit();

            // Flash success message
            session()->flash('success', 'Invoice created successfully!');

            return redirect()->route('accounting.invoices');

        } catch (\Exception $e) {
            DB::rollback();
            
            // Flash error message
            session()->flash('error', 'Failed to create invoice: ' . $e->getMessage());
            
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['customer', 'currency']);

        return response()->json([
            'success' => true,
            'data' => $invoice
        ]);
    }

    /**
     * Update the specified invoice.
     */
    public function update(Request $request, Invoice $invoice): JsonResponse
    {
        try {
            $invoice->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Invoice updated successfully',
                'data' => $invoice->fresh()->load('customer')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified invoice.
     */
    public function destroy(Invoice $invoice): JsonResponse
    {
        try {
            $invoice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Invoice deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete invoice',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}