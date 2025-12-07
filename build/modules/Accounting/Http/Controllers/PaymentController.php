<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StorePaymentRequest;
use App\Modules\Accounting\Http\Requests\UpdatePaymentRequest;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Models\CompanyCurrency;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $query = Payment::where('company_id', $company->id)
            ->with('customer:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('payment_number', 'ilike', "%{$term}%")
                    ->orWhere('reference_number', 'ilike', "%{$term}%")
                    ->orWhereHas('customer', function ($subQ) use ($term) {
                        $subQ->where('name', 'ilike', "%{$term}%");
                    });
            });
        }

        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('payment_method') && $request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }

        $payments = $query->paginate(25)->withQueryString();

        return Inertia::render('accounting/payments/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'payments' => $payments,
            'filters' => [
                'search' => $request->search ?? '',
                'customer_id' => $request->customer_id ?? '',
                'payment_method' => $request->payment_method ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get unpaid invoices (balance > 0, not cancelled/void)
        $invoices = Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'viewed', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->orderBy('invoice_number')
            ->get(['id', 'customer_id', 'invoice_number', 'balance', 'currency']);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        return Inertia::render('accounting/payments/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'customers' => $customers,
            'invoices' => $invoices,
            'currencies' => $currencies,
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        // Transform validated data to match Action expected format
        $validated = $request->validated();

        if (empty($validated['invoice_id'])) {
            return redirect()
                ->back()
                ->withErrors(['invoice_id' => 'Please select an invoice to apply this payment to.'])
                ->withInput();
        }

        // Map payment method from FormRequest format to Action format
        $methodMap = ['cheque' => 'check'];
        $method = $validated['payment_method'];
        $method = $methodMap[$method] ?? $method;

        $params = [
            'invoice' => $validated['invoice_id'],
            'amount' => $validated['amount'],
            'method' => $method,
            'currency' => $validated['currency'] ?? null,
            'date' => $validated['payment_date'] ?? null,
            'reference' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        try {
            $result = $commandBus->dispatch('payment.create', $params, $request->user());

            return redirect()
                ->route('payments.show', ['company' => $company->slug, 'payment' => $result['data']['id']])
                ->with('success', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => $e->getMessage()])
                ->withInput();
        }
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $paymentId = $request->route('payment');
        $paymentRecord = Payment::where('company_id', $company->id)
            ->with(['customer', 'paymentAllocations'])
            ->findOrFail($paymentId);

        return Inertia::render('accounting/payments/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'payment' => $paymentRecord,
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $paymentId = $request->route('payment');
        $paymentRecord = Payment::where('company_id', $company->id)
            ->with(['customer', 'paymentAllocations'])
            ->findOrFail($paymentId);

        return Inertia::render('accounting/payments/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'payment' => $paymentRecord,
        ]);
    }

    public function update(UpdatePaymentRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $paymentId = $request->route('payment');
        $paymentRecord = Payment::where('company_id', $company->id)
            ->with('paymentAllocations')
            ->findOrFail($paymentId);

        $commandBus = app(CommandBus::class);

        // Transform validated data to match Action expected format
        $validated = $request->validated();

        // Get invoice from request or from existing allocation
        $invoiceId = $validated['invoice_id'] ?? $paymentRecord->paymentAllocations->first()?->invoice_id;
        if (empty($invoiceId)) {
            return redirect()
                ->back()
                ->withErrors(['invoice_id' => 'Please select an invoice to apply this payment to.'])
                ->withInput();
        }

        // Map payment method from FormRequest format to Action format
        $methodMap = ['cheque' => 'check'];
        $method = $validated['payment_method'] ?? $paymentRecord->payment_method;
        $method = $methodMap[$method] ?? $method;

        $params = [
            'id' => $paymentRecord->id,
            'invoice' => $invoiceId,
            'amount' => $validated['amount'] ?? $paymentRecord->amount,
            'method' => $method,
            'currency' => $validated['currency'] ?? $paymentRecord->currency,
            'date' => $validated['payment_date'] ?? null,
            'reference' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $result = $commandBus->dispatch('payment.update', $params, $request->user());

        return redirect()
            ->route('payments.show', ['company' => $company->slug, 'payment' => $paymentRecord->id])
            ->with('success', $result['message']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $paymentId = $request->route('payment');
        $paymentRecord = Payment::where('company_id', $company->id)
            ->findOrFail($paymentId);

        $commandBus = app(CommandBus::class);

        $result = $commandBus->dispatch('payment.delete', ['id' => $paymentRecord->id], $request->user());

        return redirect()
            ->route('payments.index', ['company' => $company->slug])
            ->with('success', $result['message']);
    }
}