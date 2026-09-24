<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Payment;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Credit collections: a customer payment recorded from the fuel station's own screen.
 *
 * Records through the same `payment.create` command-bus action the Accounting Payments
 * screen uses (App\Modules\Accounting\Actions\Payment\CreateAction) - one Payment, one
 * journal entry, allocated to the customer's open invoices oldest-first, any remainder
 * left on account. The daily-close portal principle: this screen is a thin front end onto
 * the core action, never a reimplementation of it.
 *
 * There is no field on acct.payments distinguishing "recorded from the fuel Collections
 * screen" from any other customer payment, so index()/show() below list the company's
 * core payments generally (same as Accounting's own Payments screen would), scoped by the
 * date range / customer filters this page has always offered.
 */
class CollectionController extends Controller
{
    /** Reverse of the method mapping applied on store(), for display only. */
    private const METHOD_DISPLAY_MAP = [
        'check' => 'cheque',
        'bank_transfer' => 'bank',
    ];

    /**
     * List credit collections.
     */
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Date filters
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $customerId = $request->input('customer_id', 'all');

        $query = Payment::where('company_id', $company->id)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->with('customer:id,name')
            ->orderByDesc('payment_date')
            ->orderByDesc('created_at');

        if ($customerId !== 'all') {
            $query->where('customer_id', $customerId);
        }

        $collections = $query->get()->map(fn (Payment $payment) => $this->presentPayment($payment));

        // Get customers for filter dropdown
        $customers = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_number as code']);

        // Calculate stats
        $stats = [
            'total_collections' => $collections->count(),
            'total_amount' => $collections->sum('amount'),
            'cash_amount' => $collections->where('payment_method', 'cash')->sum('amount'),
            'bank_amount' => $collections->whereIn('payment_method', ['bank', 'transfer', 'cheque'])->sum('amount'),
        ];

        return Inertia::render('FuelStation/Collections/Index', [
            'collections' => $collections,
            'customers' => $customers,
            'stats' => $stats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'customer_id' => $customerId,
            ],
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Show create collection form.
     */
    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Get all active customers (for credit collections)
        $customers = DB::table('acct.customers')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->select(['id', 'name', 'customer_number as code', DB::raw('0 as current_balance')])
            ->get();

        // Bank/transfer/cheque collections deposit into one of these; cash collections
        // deposit into the station's cash drawer automatically (see store()).
        $depositAccounts = Account::where('company_id', $company->id)
            ->whereIn('subtype', ['bank', 'cash'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);

        // Pre-select customer if provided
        $selectedCustomerId = $request->input('customer_id');

        return Inertia::render('FuelStation/Collections/Create', [
            'customers' => $customers,
            'depositAccounts' => $depositAccounts,
            'selectedCustomerId' => $selectedCustomerId,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    /**
     * Store a new collection.
     */
    public function store(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,bank,transfer,cheque'],
            'deposit_account_id' => ['nullable', 'uuid', 'required_unless:payment_method,cash'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'collection_date' => ['required', 'date'],
        ]);

        // Map the page's four payment methods onto the core action's vocabulary and onto
        // the deposit account it settles into. Cash always lands in the station's own cash
        // drawer (the same account DailyCloseService/AmanatService resolve for cash);
        // everything else needs the GL account the user picked.
        $methodMap = ['cash' => 'cash', 'cheque' => 'check', 'bank' => 'bank_transfer', 'transfer' => 'bank_transfer'];
        $method = $methodMap[$validated['payment_method']];

        if ($validated['payment_method'] === 'cash') {
            $depositAccountId = app(DailyCloseService::class)->cashAccountId($company->id);
            if (! $depositAccountId) {
                return redirect()->back()->withErrors(['payment_method' => 'No cash account is configured for this station.'])->withInput();
            }
        } else {
            $depositAccountId = $validated['deposit_account_id'];
        }

        $params = [
            'customer_id' => $validated['customer_id'],
            'amount' => $validated['amount'],
            'method' => $method,
            'date' => $validated['collection_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'deposit_account_id' => $depositAccountId,
        ];

        try {
            $result = app(CommandBus::class)->dispatch('payment.create', $params, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('fuel.collections.show', ['company' => $company->slug, 'collection' => $result['data']['id']])
            ->with('success', $result['message'] ?? 'Collection recorded successfully.');
    }

    /**
     * Show collection details.
     */
    public function show(Request $request, string $company, string $collection): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        $payment = Payment::where('company_id', $companyModel->id)
            ->with('customer:id,name')
            ->findOrFail($collection);

        return Inertia::render('FuelStation/Collections/Show', [
            'collection' => $this->presentPayment($payment) + [
                'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
            ],
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }

    private function presentPayment(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'date' => $payment->payment_date->format('Y-m-d'),
            'reference' => $payment->reference_number,
            'customer_id' => $payment->customer_id,
            'customer_name' => $payment->customer?->name ?? 'Unknown',
            'payment_method' => self::METHOD_DISPLAY_MAP[$payment->payment_method] ?? $payment->payment_method,
            'amount' => (float) $payment->amount,
            'notes' => $payment->notes,
            'status' => 'posted',
        ];
    }
}
