<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoicing\AllocatePaymentRequest;
use App\Http\Requests\Invoicing\RefundPaymentRequest;
use App\Http\Requests\Invoicing\StorePaymentRequest;
use App\Http\Requests\Invoicing\UpdatePaymentRequest;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Filtering\FilterBuilder;
use App\Support\ServiceContextHelper;
use Brick\Money\Money;
use Illuminate\Http\Request;
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
        $query = Payment::with(['invoices.customer', 'allocations', 'currency'])
            ->where('company_id', $request->user()->current_company_id);

        // Apply normalized DSL filters if provided
        if ($request->filled('filters')) {
            $filters = $request->input('filters');
            if (is_string($filters)) {
                $decoded = json_decode($filters, true);
            } else {
                $decoded = is_array($filters) ? $filters : null;
            }
            if (is_array($decoded)) {
                $builder = new FilterBuilder;
                $fieldMap = [
                    'payment_number' => 'payment_number',
                    'payment_method' => 'payment_method',
                    'status' => 'status',
                    'amount' => 'amount',
                    'created_at' => 'created_at',
                    // Relation path: payments -> invoices -> customer.name
                    'customer_name' => [
                        'relation' => 'invoices.customer',
                        'column' => 'name',
                    ],
                ];
                $query = $builder->apply($query, $decoded, $fieldMap);
            }
        }

        $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('customer_id'), fn ($q) => $q->whereHas('invoices', fn ($subQ) => $subQ->where('customer_id', $request->customer_id)))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->payment_method))
            ->when($request->filled('date_from'), fn ($q) => $q->where('payment_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->where('payment_date', '<=', $request->date_to))
            // Amount range filters (from column menu)
            ->when($request->filled('amount_min'), fn ($q) => $q->where('amount', '>=', (float) $request->amount_min))
            ->when($request->filled('amount_max'), fn ($q) => $q->where('amount', '<=', (float) $request->amount_max))
            // Created_at date range filters (inclusive by date)
            ->when($request->filled('created_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->created_from))
            ->when($request->filled('created_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->created_to));

        $query->when($request->filled('search'), function ($q) use ($request) {
            $search = $request->search;
            $q->where(function ($subQuery) use ($search) {
                $subQuery->where('payment_number', 'like', "%{$search}%")
                    ->orWhere('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('invoices.customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        });

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');

        if (in_array($sortBy, ['created_at', 'payment_date', 'amount', 'status'])) {
            $query->orderBy($sortBy, $sortDirection);
        }

        $payments = $query->paginate($request->input('per_page', 15))
            ->withQueryString();

        // Enrich each payment with derived fields for the UI
        $payments->getCollection()->transform(function ($payment) {
            // Total allocated amount for this payment
            $payment->allocated_amount = (float) ($payment->allocations?->sum('allocated_amount') ?? 0);

            // Provide a customer object inferred from related invoices when direct relation is absent
            if (! $payment->relationLoaded('customer') || ! $payment->customer) {
                $firstInvoiceCustomer = optional($payment->invoices->first())->customer;
                if ($firstInvoiceCustomer) {
                    $payment->setRelation('customer', $firstInvoiceCustomer);
                }
            }

            return $payment;
        });

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
                'dsl' => $request->input('filters'),
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
                'payment_method' => $request->input('payment_method'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'amount_min' => $request->input('amount_min'),
                'amount_max' => $request->input('amount_max'),
                'created_from' => $request->input('created_from'),
                'created_to' => $request->input('created_to'),
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

        // Get all invoices with balance due > 0, but we'll include currency relationship
        $invoices = Invoice::where('company_id', $request->user()->current_company_id)
            ->whereIn('status', ['sent', 'posted'])
            ->where('balance_due', '>', 0)
            ->with(['currency']) // Load currency relationship
            ->orderBy('invoice_date')
            ->get(['invoice_id', 'invoice_number', 'customer_id', 'invoice_date', 'total_amount', 'balance_due', 'currency_id']);

        // If invoice_id is provided, filter to that specific invoice
        $selectedInvoice = null;
        if ($request->filled('invoice_id')) {
            $selectedInvoice = $invoices->firstWhere('invoice_id', $request->invoice_id);
        }

        // Get currencies
        $currencies = Currency::orderBy('code')->get();

        // Get the next payment number
        $nextPaymentNumber = $this->paymentService->generateNextPaymentNumber($request->user()->current_company_id);

        return Inertia::render('Invoicing/Payments/Create', [
            'customers' => $customers,
            'invoices' => $invoices,
            'selectedInvoice' => $selectedInvoice,
            'currencies' => $currencies,
            'nextPaymentNumber' => $nextPaymentNumber,
        ]);
    }

    /**
     * Store a newly created payment in storage.
     */
    public function store(StorePaymentRequest $request)
    {
        $validated = $request->validated();

        try {
            $customer = Customer::findOrFail($validated['customer_id']);
            $currency = Currency::findOrFail($validated['currency_id']);

            // Convert amount to Money object
            $amount = Money::ofMinor($validated['amount'], $currency->code);

            $context = ServiceContextHelper::fromRequest($request, $request->user()->current_company_id);

            $payment = $this->paymentService->createPayment(
                company: $request->user()->current_company,
                customer: $customer,
                amount: $amount,
                currency: $currency,
                paymentMethod: $validated['payment_method'],
                paymentDate: $validated['payment_date'],
                paymentNumber: $validated['payment_number'],
                paymentReference: $validated['reference_number'] ?? null,
                notes: $validated['notes'] ?? null,
                autoAllocate: $validated['auto_allocate'] ?? false,
                invoiceAllocations: $validated['invoice_allocations'] ?? [],
                context: $context
            );

            return redirect()
                ->route('payments.show', $payment)
                ->with('success', 'Payment created successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to create payment', [
                'error' => $e->getMessage(),
                'request' => $validated,
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
            ->where('balance_due', '>', 0)
            ->orderBy('invoice_date')
            ->get(['invoice_id', 'invoice_number', 'customer_id', 'invoice_date', 'total_amount', 'balance_due']);

        return Inertia::render('Invoicing/Payments/Edit', [
            'payment' => $payment,
            'customers' => $customers,
            'invoices' => $invoices,
        ]);
    }

    /**
     * Update the specified payment in storage.
     */
    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'Only pending payments can be updated.');
        }

        $validated = $request->validated();

        try {
            $customer = Customer::findOrFail($validated['customer_id']);

            $context = ServiceContextHelper::fromRequest($request, $payment->company_id);

            $updatedPayment = $this->paymentService->updatePayment(
                payment: $payment,
                customer: $customer,
                amount: $validated['amount'],
                currency: $validated['currency_id'],
                paymentMethod: $validated['payment_method'],
                paymentDate: $validated['payment_date'],
                paymentNumber: $validated['payment_number'],
                referenceNumber: $validated['reference_number'] ?? null,
                notes: $validated['notes'] ?? null,
                autoAllocate: $validated['auto_allocate'] ?? false,
                invoiceAllocations: $validated['invoice_allocations'] ?? [],
                idempotencyKey: $request->header('Idempotency-Key'),
                context: $context
            );

            return redirect()
                ->route('payments.show', $updatedPayment)
                ->with('success', 'Payment updated successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to update payment', [
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
            $context = ServiceContextHelper::fromRequest($request, $payment->company_id);
            $this->paymentService->deletePayment($payment, $context);

            return redirect()
                ->route('payments.index')
                ->with('success', 'Payment deleted successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to delete payment', [
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
    public function allocate(AllocatePaymentRequest $request, Payment $payment)
    {
        if ($payment->status === 'void' || $payment->status === 'refunded') {
            return back()->with('error', 'Cannot allocate void or refunded payments.');
        }

        $validated = $request->validated();

        try {
            $context = ServiceContextHelper::fromRequest($request, $payment->company_id);

            // Convert invoice allocations to expected format
            $allocations = array_map(function ($allocation) {
                return [
                    'invoice_id' => $allocation['invoice_id'],
                    'amount' => $allocation['amount'],
                ];
            }, $validated['invoice_allocations']);

            $this->paymentService->allocatePayment(
                payment: $payment,
                allocations: $allocations,
                notes: $validated['notes'] ?? null,
                context: $context
            );

            return back()->with('success', 'Payment allocated successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to allocate payment', [
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
            $context = ServiceContextHelper::fromRequest($request, $payment->company_id);
            $this->paymentService->autoAllocatePayment($payment, $context);

            return back()->with('success', 'Payment auto-allocated successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to auto-allocate payment', [
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
            $context = ServiceContextHelper::fromRequest($request, $payment->company_id);
            $this->paymentService->voidPayment($payment, 'Voided by user', $context);

            return back()->with('success', 'Payment voided successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to void payment', [
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
    public function refund(RefundPaymentRequest $request, Payment $payment)
    {
        if (! in_array($payment->status, ['allocated', 'partial'])) {
            return back()->with('error', 'Only allocated or partially allocated payments can be refunded.');
        }

        $validated = $request->validated();

        try {
            $context = ServiceContextHelper::fromRequest($request, $payment->company_id);

            // Convert refund amount to Money
            $currency = $payment->currency;
            $refundAmount = Money::ofMinor($validated['refund_amount'], $currency->code);

            $this->paymentService->refundPayment(
                payment: $payment,
                amount: $refundAmount,
                reason: $validated['refund_reason'] ?? 'Refunded by customer',
                context: $context
            );

            return back()->with('success', 'Payment refunded successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to refund payment', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
                'user_id' => $request->user()->id,
            ]);

            return back()->with('error', 'Failed to refund payment. '.$e->getMessage());
        }
    }
}
