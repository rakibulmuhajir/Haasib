<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Acct\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     */
    public function index(Request $request): Response
    {
        $company = $request->user()->currentCompany();
        
        $payments = Payment::where('company_id', $company->id)
            ->with(['customer'])
            ->orderBy('payment_date', 'desc')
            ->paginate(15);

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
        ]);
    }

    /**
     * Show the form for creating a new payment.
     */
    public function create(Request $request): Response
    {
        try {
            $company = $request->user()->currentCompany();
            
            $customers = Customer::where('company_id', $company->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            $unpaidInvoices = Invoice::where('company_id', $company->id)
                ->where('status', '!=', 'paid')
                ->with(['customer'])
                ->orderBy('due_date', 'asc')
                ->get();

            return Inertia::render('Payments/Create', [
                'customers' => $customers,
                'unpaidInvoices' => $unpaidInvoices,
            ]);
        } catch (\Exception $e) {
            \Log::error('PaymentController create error: ' . $e->getMessage());
            
            // Fallback with empty data
            return Inertia::render('Payments/Create', [
                'customers' => collect([]),
                'unpaidInvoices' => collect([]),
            ]);
        }
    }

    /**
     * Store a newly created payment.
     */
    public function store(Request $request): RedirectResponse
    {
        \Log::info('Payment store request data:', $request->all());
        
        $validated = $request->validate([
            'customer_id' => 'required|exists:acct.customers,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        \Log::info('Payment validation passed:', $validated);

        try {
            $company = $request->user()->currentCompany();
            
            // Create the payment
            $payment = Payment::create([
                'company_id' => $company->id,
                'customer_id' => $validated['customer_id'],
                'payment_number' => 'PAY-' . date('Y') . '-' . str_pad(Payment::where('company_id', $company->id)->count() + 1, 4, '0', STR_PAD_LEFT),
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'currency' => $company->currency_code ?? 'USD',
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'created_by_user_id' => $request->user()->id,
            ]);

            return redirect()->route('payments.index')
                ->with('success', "Payment of {$validated['amount']} created successfully!");
                
        } catch (\Exception $e) {
            \Log::error('Payment store error: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create payment: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, Payment $payment): Response
    {
        $payment->load(['customer', 'allocations.invoice']);
        
        return Inertia::render('Payments/Show', [
            'payment' => $payment,
        ]);
    }

    /**
     * Show the form for editing the specified payment.
     */
    public function edit(Request $request, Payment $payment): Response
    {
        $company = $request->user()->currentCompany();
        
        $customers = Customer::where('company_id', $company->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Payments/Edit', [
            'payment' => $payment,
            'customers' => $customers,
        ]);
    }
}