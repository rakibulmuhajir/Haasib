<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreCustomerRequest;
use App\Modules\Accounting\Http\Requests\UpdateCustomerRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Account;
use App\Models\CompanyCurrency;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $now = now()->toDateString();

        $query = Customer::where('company_id', $company->id)
            ->select('*')
            ->selectSub(function ($q) use ($company) {
                $q->from('acct.invoices as inv')
                    ->selectRaw('COALESCE(SUM(inv.balance), 0)')
                    ->whereColumn('inv.customer_id', 'acct.customers.id')
                    ->where('inv.company_id', $company->id)
                    ->whereNotIn('inv.status', ['paid', 'void', 'cancelled'])
                    ->where('inv.balance', '>', 0);
            }, 'outstanding_balance')
            ->selectSub(function ($q) use ($company, $now) {
                $q->from('acct.invoices as inv')
                    ->selectRaw('COALESCE(SUM(inv.balance), 0)')
                    ->whereColumn('inv.customer_id', 'acct.customers.id')
                    ->where('inv.company_id', $company->id)
                    ->whereNotIn('inv.status', ['paid', 'void', 'cancelled'])
                    ->where('inv.balance', '>', 0)
                    ->where('inv.due_date', '<', $now);
            }, 'overdue_balance')
            ->selectSub(function ($q) use ($company) {
                $q->from('acct.invoices as inv')
                    ->selectRaw('MAX(inv.invoice_date)')
                    ->whereColumn('inv.customer_id', 'acct.customers.id')
                    ->where('inv.company_id', $company->id);
            }, 'last_invoice_date')
            ->selectSub(function ($q) use ($company) {
                $q->from('acct.payments as pay')
                    ->selectRaw('MAX(pay.payment_date)')
                    ->whereColumn('pay.customer_id', 'acct.customers.id')
                    ->where('pay.company_id', $company->id);
            }, 'last_payment_date');

        if ($request->has('search') && $request->search) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', "%{$term}%")
                    ->orWhere('email', 'ilike', "%{$term}%")
                    ->orWhere('phone', 'ilike', "%{$term}%")
                    ->orWhere('customer_number', 'ilike', "%{$term}%");
            });
        }

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($request->boolean('with_overdue')) {
            $query->whereExists(function ($sub) use ($company) {
                $sub->from('acct.invoices as inv')
                    ->selectRaw('1')
                    ->whereColumn('inv.customer_id', 'acct.customers.id')
                    ->where('inv.company_id', $company->id)
                    ->where('inv.balance', '>', 0)
                    ->whereNotIn('inv.status', ['paid', 'void', 'cancelled'])
                    ->where('inv.due_date', '<', now()->toDateString());
            });
        }

        if ($request->boolean('with_outstanding')) {
            $query->whereExists(function ($sub) use ($company) {
                $sub->from('acct.invoices as inv')
                    ->selectRaw('1')
                    ->whereColumn('inv.customer_id', 'acct.customers.id')
                    ->where('inv.company_id', $company->id)
                    ->where('inv.balance', '>', 0)
                    ->whereNotIn('inv.status', ['paid', 'void', 'cancelled']);
            });
        }

        $sortBy = $request->get('sort_by', 'name');
        $sortDir = $request->get('sort_dir', 'asc');
        $sortable = [
            'name' => 'name',
            'outstanding' => 'outstanding_balance',
            'overdue' => 'overdue_balance',
            'last_invoice' => 'last_invoice_date',
            'last_payment' => 'last_payment_date',
        ];
        $sortColumn = $sortable[$sortBy] ?? 'name';
        $sortDirection = $sortDir === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $sortDirection);

        // Aggregate financials per customer to avoid N+1 in the grid
        $invoiceAggregates = Invoice::where('company_id', $company->id)
            ->selectRaw('customer_id, SUM(balance) AS open_balance, COUNT(*) AS invoice_count')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $creditTotals = CreditNote::where('company_id', $company->id)
            ->where('status', '!=', 'void')
            ->selectRaw('customer_id, SUM(amount) AS total_credit, COUNT(*) AS credit_count')
            ->groupBy('customer_id')
            ->get()
            ->keyBy('customer_id');

        $creditApplied = DB::table('acct.credit_note_applications as cna')
            ->join('acct.credit_notes as cn', 'cn.id', '=', 'cna.credit_note_id')
            ->where('cn.company_id', $company->id)
            ->selectRaw('cn.customer_id, SUM(cna.amount_applied) AS total_applied')
            ->groupBy('cn.customer_id')
            ->get()
            ->pluck('total_applied', 'customer_id');

        $paymentsReceived = Payment::where('company_id', $company->id)
            ->selectRaw('customer_id, SUM(COALESCE(base_amount, amount)) AS total_received')
            ->groupBy('customer_id')
            ->get()
            ->pluck('total_received', 'customer_id');

        $customers = $query->paginate(25)->withQueryString();
        $customers->through(function (Customer $customer) use ($invoiceAggregates, $creditTotals, $creditApplied, $paymentsReceived) {
            $invoiceSummary = $invoiceAggregates->get($customer->id);
            $creditSummary = $creditTotals->get($customer->id);

            $applied = (float) ($creditApplied[$customer->id] ?? 0);
            $totalCredit = (float) ($creditSummary->total_credit ?? 0);
            $availableCredit = max(0, $totalCredit - $applied);

            return array_merge($customer->toArray(), [
                'open_balance' => (float) ($invoiceSummary->open_balance ?? 0),
                'outstanding_balance' => (float) ($customer->getAttribute('outstanding_balance') ?? $invoiceSummary->open_balance ?? 0),
                'overdue_balance' => (float) ($customer->getAttribute('overdue_balance') ?? 0),
                'invoice_count' => (int) ($invoiceSummary->invoice_count ?? 0),
                'available_credit' => $availableCredit,
                'credit_note_count' => (int) ($creditSummary->credit_count ?? 0),
                'payments_received' => (float) ($paymentsReceived[$customer->id] ?? 0),
                'last_invoice_date' => $customer->getAttribute('last_invoice_date'),
                'last_payment_date' => $customer->getAttribute('last_payment_date'),
            ]);
        });

        return Inertia::render('accounting/customers/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'customers' => $customers,
            'filters' => [
                'search' => $request->search ?? '',
                'include_inactive' => $request->boolean('include_inactive'),
                'with_overdue' => $request->boolean('with_overdue'),
                'with_outstanding' => $request->boolean('with_outstanding'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDirection,
            ],
        ]);
    }

    public function create(): Response
    {
        $company = CompanyContext::getCompany();

        return Inertia::render('accounting/customers/Create', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();
        $commandBus = app(CommandBus::class);

        $result = $commandBus->dispatch('customer.create', $request->validated(), $request->user());

        return redirect()
            ->route('customers.show', ['company' => $company->slug, 'customer' => $result['data']['id']])
            ->with('success', $result['message']);
    }

    public function show(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $customerId = $request->route('customer');
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $invoiceSummary = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->selectRaw('COALESCE(SUM(balance), 0) AS open_balance')
            ->selectRaw('COALESCE(SUM(total_amount), 0) AS total_billed')
            ->selectRaw('COUNT(*) AS invoice_count')
            ->first();

        $creditSummary = CreditNote::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'void')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_credit')
            ->selectRaw('COUNT(*) AS credit_count')
            ->first();

        $creditApplied = DB::table('acct.credit_note_applications as cna')
            ->join('acct.credit_notes as cn', 'cn.id', '=', 'cna.credit_note_id')
            ->where('cn.company_id', $company->id)
            ->where('cn.customer_id', $customer->id)
            ->sum('cna.amount_applied');

        $availableCredit = max(0, (float) ($creditSummary->total_credit ?? 0) - (float) $creditApplied);

        $paymentsTotal = Payment::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->sum(DB::raw('COALESCE(base_amount, amount)'));

        $overdue = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('balance', '>', 0)
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->sum('balance');

        $paidYtd = Payment::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->whereYear('payment_date', now()->year)
            ->sum(DB::raw('COALESCE(base_amount, amount)'));

        $invoicedYtd = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->whereYear('invoice_date', now()->year)
            ->sum(DB::raw('COALESCE(base_amount, total_amount)'));

        $avgDaysToPay = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->whereNotNull('paid_at')
            ->where('status', 'paid')
            ->selectRaw("AVG(EXTRACT(EPOCH FROM (paid_at - invoice_date)) / 86400) as avg_days")
            ->value('avg_days');

        // AR Aging for this specific customer
        $now = now()->toDateString();
        $aging = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['paid', 'void', 'cancelled'])
            ->selectRaw("COALESCE(SUM(CASE WHEN due_date >= ? THEN balance ELSE 0 END), 0) AS current", [$now])
            ->selectRaw("COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN balance ELSE 0 END), 0) AS bucket_1_30", [$now, now()->subDays(30)->toDateString()])
            ->selectRaw("COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN balance ELSE 0 END), 0) AS bucket_31_60", [now()->subDays(30)->toDateString(), now()->subDays(60)->toDateString()])
            ->selectRaw("COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN balance ELSE 0 END), 0) AS bucket_61_90", [now()->subDays(60)->toDateString(), now()->subDays(90)->toDateString()])
            ->selectRaw("COALESCE(SUM(CASE WHEN due_date < ? THEN balance ELSE 0 END), 0) AS bucket_90_plus", [now()->subDays(90)->toDateString()])
            ->first();

        $invoices = Invoice::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('invoice_date')
            ->limit(25)
            ->get([
                'id',
                'invoice_number',
                'invoice_date',
                'due_date',
                'total_amount',
                'paid_amount',
                'balance',
                'status',
            ]);

        $payments = Payment::where('company_id', $company->id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('payment_date')
            ->with(['paymentAllocations.invoice:id,invoice_number'])
            ->limit(25)
            ->get([
                'id',
                'payment_number',
                'payment_date',
                'amount',
                'currency',
                'payment_method',
                'reference_number',
                'notes',
            ]);

        // Currencies for editing
        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        return Inertia::render('accounting/customers/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'customer' => $customer,
            'summary' => [
                'open_balance' => (float) ($invoiceSummary->open_balance ?? 0),
                'invoice_count' => (int) ($invoiceSummary->invoice_count ?? 0),
                'total_billed' => (float) ($invoiceSummary->total_billed ?? 0),
                'available_credit' => $availableCredit,
                'credit_note_count' => (int) ($creditSummary->credit_count ?? 0),
                'payments_received' => (float) $paymentsTotal,
                'base_currency' => $company->base_currency,
                'overdue_balance' => (float) $overdue,
                'paid_ytd' => (float) $paidYtd,
                'invoiced_ytd' => (float) $invoicedYtd,
                'avg_days_to_pay' => $avgDaysToPay ? round($avgDaysToPay, 1) : null,
                'aging' => [
                    'current' => (float) ($aging->current ?? 0),
                    'bucket_1_30' => (float) ($aging->bucket_1_30 ?? 0),
                    'bucket_31_60' => (float) ($aging->bucket_31_60 ?? 0),
                    'bucket_61_90' => (float) ($aging->bucket_61_90 ?? 0),
                    'bucket_90_plus' => (float) ($aging->bucket_90_plus ?? 0),
                ],
            ],
            'invoices' => $invoices,
            'payments' => $payments,
            'currencies' => $currencies,
            'canEdit' => true, // TODO: Check actual permissions
        ]);
    }

    public function edit(Request $request): Response
    {
        $company = CompanyContext::getCompany();

        $customerId = $request->route('customer');
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $currencies = CompanyCurrency::where('company_id', $company->id)
            ->orderByDesc('is_base')
            ->orderBy('currency_code')
            ->get(['currency_code', 'is_base']);

        $arAccounts = Account::where('company_id', $company->id)
            ->where('subtype', 'accounts_receivable')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('accounting/customers/Edit', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            'customer' => $customer,
            'currencies' => $currencies,
            'arAccounts' => $arAccounts,
        ]);
    }

    public function update(UpdateCustomerRequest $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $customerId = $request->route('customer');
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $commandBus = app(CommandBus::class);

        $params = array_merge($request->validated(), ['id' => $customer->id]);
        $result = $commandBus->dispatch('customer.update', $params, $request->user());

        return redirect()
            ->route('customers.show', ['company' => $company->slug, 'customer' => $customer->id])
            ->with('success', $result['message']);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = CompanyContext::getCompany();

        $customerId = $request->route('customer');
        $customer = Customer::where('company_id', $company->id)
            ->findOrFail($customerId);

        $commandBus = app(CommandBus::class);

        $result = $commandBus->dispatch('customer.delete', ['id' => $customer->id], $request->user());

        return redirect()
            ->route('customers.index', ['company' => $company->slug])
            ->with('success', $result['message']);
    }
}
