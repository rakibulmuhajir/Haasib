<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Display a listing of payments.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['customer', 'invoices', 'allocations'])
            ->where('company_id', $request->user()->current_company_id);

        // Apply filters
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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['created_at', 'payment_date', 'amount', 'status'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $payments = $query->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Get available filters
        $customers = Customer::where('company_id', $request->user()->current_company_id)
            ->orderBy('name')
            ->get(['customer_id', 'name']);

        $statusOptions = [
            ['value' => 'pending', 'label' => 'Pending'],
            ['value' => 'allocated', 'label' => 'Allocated'],
            ['value' => 'partial', 'label' => 'Partially Allocated'],
            ['value' => 'refunded', 'label' => 'Refunded'],
            ['value' => 'void', 'label' => 'Void'],
        ];

        $paymentMethodOptions = [
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'check', 'label' => 'Check'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer'],
            ['value' => 'credit_card', 'label' => 'Credit Card'],
            ['value' => 'debit_card', 'label' => 'Debit Card'],
            ['value' => 'paypal', 'label' => 'PayPal'],
            ['value' => 'stripe', 'label' => 'Stripe'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return Inertia::render('Invoicing/Payments/Index', [
            'payments' => $payments,
            'filters' => [
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
                'payment_method' => $request->input('payment_method'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'search' => $request->input('search'),
                'sort_by' => $sortBy,
                'sort_direction' => $sortDirection,
            ],
            'customers' => $customers,
            'statusOptions' => $statusOptions,
            'paymentMethodOptions' => $paymentMethodOptions,
        ]);
    }

    /**
     * Show the form for creating a new payment.
     */
    public function create(Request $request)
    {
        $customers = Customer::where('company_id', $request->user()->current_company_id)
            ->orderBy('name')
            ->get(['customer_id', 'name', 'email', 'currency_id']);

        $invoices = Invoice::where('company_id', $request->user()->current_company_id)
            ->whereIn('status', ['sent', 'posted'])
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date')
            ->get(['invoice_id', 'invoice_number', 'customer_id', 'invoice_date', 'total_amount', 'balance_amount']);

        // Get the next payment number
        $nextPaymentNumber = $this->paymentService->generateNextPaymentNumber($request->user()->current_company_id);

        return Inertia::render('Invoicing/Payments/Create', [
            'customers' => $customers,
            'invoices' => $invoices,
            'nextPaymentNumber' => $nextPaymentNumber,
        ]);
    }

    /**
     * Store a newly created payment in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'payment_number' => 'required|string|max:50',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'required|exists:currencies,id',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'auto_allocate' => 'boolean',
            'invoice_allocations' => 'array',
            'invoice_allocations.*.invoice_id' => 'required|exists:invoices,id',
            'invoice_allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $customer = Customer::findOrFail($request->customer_id);

            $payment = $this->paymentService->createPayment(
                company: $request->user()->current_company,
                customer: $customer,
                amount: $request->amount,
                currency: $request->currency_id,
                paymentMethod: $request->payment_method,
                paymentDate: $request->payment_date,
                paymentNumber: $request->payment_number,
                referenceNumber: $request->reference_number,
                notes: $request->notes,
                autoAllocate: $request->boolean('auto_allocate'),
                invoiceAllocations: $request->invoice_allocations,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return redirect()
                ->route('payments.show', $payment)
                ->with('success', 'Payment created successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to create payment', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
                'user_id' => $request->user()->id,
            ]);

            return back()
                ->with('error', 'Failed to create payment. '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified payment.
     */
    public function show(Request $request, Payment $payment)
    {
        $this->authorize('view', $payment);

        $payment->load(['customer', 'currency', 'invoices', 'allocations.invoice']);

        return Inertia::render('Invoicing/Payments/Show', [
            'payment' => $payment,
        ]);
    }

    /**
     * Show the form for editing the specified payment.
     */
    public function edit(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be edited.');
        }

        $payment->load(['customer', 'currency', 'allocations.invoice']);

        $customers = Customer::where('company_id', $request->user()->current_company_id)
            ->orderBy('name')
            ->get(['customer_id', 'name', 'email', 'currency_id']);

        $invoices = Invoice::where('company_id', $request->user()->current_company_id)
            ->whereIn('status', ['sent', 'posted'])
            ->where('balance_amount', '>', 0)
            ->orderBy('invoice_date')
            ->get(['invoice_id', 'invoice_number', 'customer_id', 'invoice_date', 'total_amount', 'balance_amount']);

        return Inertia::render('Invoicing/Payments/Edit', [
            'payment' => $payment,
            'customers' => $customers,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Update the specified payment in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be updated.');
        }

        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'payment_number' => 'required|string|max:50',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency_id' => 'required|exists:currencies,id',
            'payment_method' => 'required|string|max:50',
            'reference_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'auto_allocate' => 'boolean',
            'invoice_allocations' => 'array',
            'invoice_allocations.*.invoice_id' => 'required|exists:invoices,id',
            'invoice_allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $customer = Customer::findOrFail($request->customer_id);

            $updatedPayment = $this->paymentService->updatePayment(
                payment: $payment,
                customer: $customer,
                amount: $request->amount,
                currency: $request->currency_id,
                paymentMethod: $request->payment_method,
                paymentDate: $request->payment_date,
                paymentNumber: $request->payment_number,
                referenceNumber: $request->reference_number,
                notes: $request->notes,
                autoAllocate: $request->boolean('auto_allocate'),
                invoiceAllocations: $request->invoice_allocations,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return redirect()
                ->route('payments.show', $updatedPayment)
                ->with('success', 'Payment updated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to update payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return back()
                ->with('error', 'Failed to update payment. '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified payment from storage.
     */
    public function destroy(Request $request, Payment $payment)
    {
        $this->authorize('delete', $payment);

        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be deleted.');
        }

        try {
            $this->paymentService->deletePayment($payment);

            return redirect()
                ->route('payments.index')
                ->with('success', 'Payment deleted successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to delete payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to delete payment. '.$e->getMessage());
        }
    }

    /**
     * Allocate payment to invoices.
     */
    public function allocate(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        if ($payment->status === 'void' || $payment->status === 'refunded') {
            return back()->with('error', 'Cannot allocate void or refunded payments.');
        }

        $validator = Validator::make($request->all(), [
            'invoice_allocations' => 'required|array|min:1',
            'invoice_allocations.*.invoice_id' => 'required|exists:invoices,id',
            'invoice_allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $this->paymentService->allocatePayment(
                payment: $payment,
                invoiceAllocations: $request->invoice_allocations,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return back()->with('success', 'Payment allocated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to allocate payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to allocate payment. '.$e->getMessage());
        }
    }

    /**
     * Auto-allocate payment to invoices.
     */
    public function autoAllocate(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        if ($payment->status === 'void' || $payment->status === 'refunded') {
            return back()->with('error', 'Cannot allocate void or refunded payments.');
        }

        try {
            $this->paymentService->autoAllocatePayment($payment);

            return back()->with('success', 'Payment auto-allocated successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to auto-allocate payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to auto-allocate payment. '.$e->getMessage());
        }
    }

    /**
     * Void payment.
     */
    public function void(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        if (! in_array($payment->status, ['pending', 'allocated', 'partial'])) {
            return back()->with('error', 'Only pending, allocated, or partially allocated payments can be voided.');
        }

        try {
            $this->paymentService->voidPayment($payment);

            return back()->with('success', 'Payment voided successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to void payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to void payment. '.$e->getMessage());
        }
    }

    /**
     * Refund payment.
     */
    public function refund(Request $request, Payment $payment)
    {
        $this->authorize('update', $payment);

        if (! in_array($payment->status, ['allocated', 'partial'])) {
            return back()->with('error', 'Only allocated or partially allocated payments can be refunded.');
        }

        $validator = Validator::make($request->all(), [
            'refund_amount' => 'required|numeric|min:0.01|max:'.$payment->allocated_amount,
            'refund_reason' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $this->paymentService->refundPayment(
                payment: $payment,
                refundAmount: $request->refund_amount,
                refundReason: $request->refund_reason,
                idempotencyKey: $request->header('Idempotency-Key')
            );

            return back()->with('success', 'Payment refunded successfully.');

        } catch (\Exception $e) {
            Log::error('Failed to refund payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to refund payment. '.$e->getMessage());
        }
    }
}
