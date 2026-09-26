<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StorePaymentRequest;
use App\Modules\Accounting\Http\Requests\UpdatePaymentRequest;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\FuelStation\Actions\AmanatMovementAction;
use App\Services\CommandBus;
use App\Services\CompanyCurrencyOptions;
use App\Services\CompanyLetterhead;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Whether this company/user combination may hold customer payments as amanat instead
     * of applying them to invoices. Mirrors HandleInertiaRequests' fuel-station check
     * (module enabled OR industry_code/industry === 'fuel_station'), plus the permission
     * AmanatMovementAction itself requires.
     */
    private function amanatAvailable($company, $user): bool
    {
        if (! $user) {
            return false;
        }

        $isFuelStation = $company->isModuleEnabled('fuel_station')
            || $company->industry_code === 'fuel_station'
            || $company->industry === 'fuel_station';

        if (! $isFuelStation) {
            return false;
        }

        $permission = (new AmanatMovementAction())->permission();

        return ! $permission || $user->hasCompanyPermission($permission);
    }

    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $showVoided = $request->boolean('show_voided', false);
        $query = ($showVoided ? Payment::withTrashed() : Payment::query())
            ->where('company_id', $company->id)
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
                'show_voided' => $showVoided,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get unpaid invoices (balance > 0, not cancelled/void)
        // Also include 'approved' status since some invoices may be approved but not sent
        $invoices = Invoice::where('company_id', $company->id)
            ->whereIn('status', ['approved', 'sent', 'viewed', 'partial', 'overdue'])
            ->where('balance', '>', 0)
            ->orderBy('invoice_date')
            ->orderBy('invoice_number')
            ->get(['id', 'customer_id', 'invoice_number', 'invoice_date', 'total_amount', 'balance', 'currency']);

        $currencies = app(CompanyCurrencyOptions::class)->forCompany($company);

        $depositAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        // Pre-selection from URL params (when coming from invoice page)
        $preselect = [
            'customer_id' => $request->query('customer_id'),
            'invoice_id' => $request->query('invoice_id'),
        ];

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
            'depositAccounts' => $depositAccounts,
            'preselect' => $preselect,
            'amanat' => $this->amanatAvailable($company, $request->user()) ? ['enabled' => true] : null,
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        // Transform validated data to match Action expected format
        $validated = $request->validated();

        if (($validated['received_as'] ?? 'invoices') === 'amanat') {
            return $this->storeAmanat($request, $company, $commandBus, $validated);
        }

        // Map payment method from FormRequest format to Action format. When the form
        // leaves it blank (the slimmed /payments form no longer asks), derive it from the
        // deposit account's own subtype - a cash account raises the drawer, a bank account
        // never does - exactly as DailyClosePaymentsReceivedService derives it for the
        // daily close's own "Customer payments & deposits" panel.
        $methodMap = ['cheque' => 'check'];
        $method = $validated['payment_method'] ?? null;
        if (!$method) {
            $depositAccount = Account::where('company_id', $company->id)->find($validated['deposit_account_id']);
            $method = ($depositAccount && $depositAccount->subtype === 'cash') ? 'cash' : 'bank_transfer';
        }
        $method = $methodMap[$method] ?? $method;

        // No invoice_id, invoice_ids or allocations at all means "on account" (an advance,
        // or a lump sum the buyer wants applied automatically) - Payment\CreateAction's
        // customer_id-only path handles that. invoice_id (a single pick) and invoice_ids (a
        // hand-picked set, auto-split oldest-first) and allocations (exact amounts per
        // invoice) are mutually exclusive ways to say more than that.
        $params = [
            'customer_id' => $validated['customer_id'],
            'invoice' => $validated['invoice_id'] ?? null,
            'invoice_ids' => $validated['invoice_ids'] ?? null,
            'allocations' => $validated['allocations'] ?? null,
            'amount' => $validated['amount'],
            'transaction_charge' => $validated['transaction_charge'] ?? 0,
            'method' => $method,
            'currency' => $validated['currency'] ?? null,
            'date' => $validated['payment_date'] ?? null,
            'reference' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'deposit_account_id' => $validated['deposit_account_id'],
            'ar_account_id' => $validated['ar_account_id'] ?? null,
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
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Record money received from a customer as amanat (an advance held for them, used up
     * against fuel later) instead of applying it to invoices. Reuses the FuelStation
     * module's own command — no journal, payment row or amanat row is written here.
     */
    private function storeAmanat(StorePaymentRequest $request, $company, CommandBus $commandBus, array $validated): RedirectResponse
    {
        $user = $request->user();

        if (! $this->amanatAvailable($company, $user)) {
            throw ValidationException::withMessages([
                'received_as' => 'Amanat is not available for this company.',
            ]);
        }

        try {
            $commandBus->dispatch('fuel.amanat.movement', [
                'customer_id' => $validated['customer_id'],
                'kind' => 'deposit',
                'business_date' => $validated['payment_date'] ?? null,
                'amount' => $validated['amount'],
                'payment_account_id' => $validated['deposit_account_id'],
                'reference' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], $user);

            $customer = Customer::where('company_id', $company->id)->findOrFail($validated['customer_id']);

            return redirect()
                ->route('fuel.amanat.show', ['company' => $company->slug, 'customer' => $customer->id])
                ->with('success', "Amanat received from {$customer->name}");
        } catch (ModelNotFoundException $e) {
            $field = $e->getModel() === Customer::class ? 'customer_id' : 'deposit_account_id';

            return redirect()
                ->back()
                ->withErrors([$field => $field === 'customer_id' ? 'The selected customer was not found.' : 'The selected deposit account was not found.'])
                ->withInput();
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['customer_id' => $e->getMessage()])
                ->withInput();
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $paymentId = $request->route('payment');
        $paymentRecord = Payment::where('company_id', $company->id)
            ->with(['customer', 'paymentAllocations.invoice:id,invoice_number,currency'])
            ->findOrFail($paymentId);

        return Inertia::render('accounting/payments/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'letterhead' => app(CompanyLetterhead::class)->forCompany($company),
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

        try {
            $result = $commandBus->dispatch('payment.delete', ['id' => $paymentRecord->id], $request->user());

            return redirect()
                ->route('payments.index', ['company' => $company->slug])
                ->with('success', $result['message']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
